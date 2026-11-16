<?php declare(strict_types=1);

namespace App\Tests\Unit\ScrapeCreators;

use App\Model\Profile;
use App\ScrapeCreators\PostConverter;
use PHPUnit\Framework\TestCase;

class PostConverterTest extends TestCase
{
    public function testInstagramPermalinkMatchesTheRssAppForm(): void
    {
        // RSS.app stored https://www.instagram.com/p/<code> without a trailing
        // slash, also for reels. The same form keeps old items from doubling.
        $item = PostConverter::convertInstagram($this->makeProfile(), self::photoPost());

        self::assertNotNull($item);
        self::assertSame('https://www.instagram.com/p/DfnONvgDTop', $item->getUniqueIdentifier());
        self::assertSame('https://www.instagram.com/p/DfnONvgDTop', $item->getPermalink());
        self::assertSame(42, $item->getProfileId());
        self::assertSame("Erste Zeile\nzweite Zeile", $item->getText());
        self::assertSame('Erste Zeile zweite Zeile', $item->getTitle());
        self::assertSame('2026-11-11 19:22:31', $item->getDateTime()->format('Y-m-d H:i:s'));
    }

    public function testThreadsPermalinkMatchesTheRssAppForm(): void
    {
        $item = PostConverter::convertThreads($this->makeProfile(), self::photoPost());

        self::assertNotNull($item);
        self::assertSame('https://threads.net/@edcriticalmass/post/DfnONvgDTop', $item->getUniqueIdentifier());
    }

    public function testRawKeepsOnlyTheLargestVersionOfEachPicture(): void
    {
        $raw = json_decode(PostConverter::convertInstagram($this->makeProfile(), self::photoPost())->getRaw(), true);

        self::assertSame('scrapecreators', $raw['provider']);
        self::assertSame('instagram', $raw['network']);
        self::assertSame('edcriticalmass', $raw['owner']);
        self::assertSame([['type' => 'image', 'url' => 'https://cdn.example/large.jpg']], $raw['media']);
        self::assertSame('https://cdn.example/large.jpg', $raw['thumbnail']);
    }

    public function testCarouselWithVideoListsEveryEntryInOrder(): void
    {
        $post = self::photoPost();
        $post['carousel_media'] = [
            ['image_versions2' => ['candidates' => [['url' => 'https://cdn.example/a.jpg', 'width' => 10, 'height' => 10]]]],
            [
                'image_versions2' => ['candidates' => [['url' => 'https://cdn.example/cover.jpg', 'width' => 10, 'height' => 10]]],
                'video_versions' => [
                    ['url' => 'https://cdn.example/small.mp4', 'width' => 100, 'height' => 100],
                    ['url' => 'https://cdn.example/big.mp4', 'width' => 720, 'height' => 1280],
                ],
            ],
        ];

        $raw = json_decode(PostConverter::convertInstagram($this->makeProfile(), $post)->getRaw(), true);

        self::assertSame([
            ['type' => 'image', 'url' => 'https://cdn.example/a.jpg'],
            ['type' => 'video', 'url' => 'https://cdn.example/big.mp4', 'thumbnail' => 'https://cdn.example/cover.jpg'],
        ], $raw['media']);
    }

    public function testPostWithoutCaptionGetsEmptyText(): void
    {
        $post = self::photoPost();
        $post['caption'] = null;

        $item = PostConverter::convertInstagram($this->makeProfile(), $post);

        self::assertSame('', $item->getText());
        self::assertSame('', $item->getTitle());
    }

    public function testLongCaptionIsShortenedForTheTitleOnly(): void
    {
        $post = self::photoPost();
        $post['caption'] = ['text' => str_repeat('ä', 200)];

        $item = PostConverter::convertInstagram($this->makeProfile(), $post);

        self::assertSame(str_repeat('ä', 120) . '...', $item->getTitle());
        self::assertSame(200, mb_strlen($item->getText()));
    }

    public function testPostWithoutCodeOrDateIsSkipped(): void
    {
        $withoutCode = self::photoPost();
        unset($withoutCode['code']);
        $withoutDate = self::photoPost();
        unset($withoutDate['taken_at']);
        $withoutUser = self::photoPost();
        unset($withoutUser['user']);

        self::assertNull(PostConverter::convertInstagram($this->makeProfile(), $withoutCode));
        self::assertNull(PostConverter::convertInstagram($this->makeProfile(), $withoutDate));
        self::assertNull(PostConverter::convertThreads($this->makeProfile(), $withoutUser));
    }

    public static function photoPost(): array
    {
        return [
            'pk' => '4028250908714154537',
            'code' => 'DfnONvgDTop',
            'taken_at' => 1794424951,
            'user' => ['username' => 'edcriticalmass', 'full_name' => 'Critical Mass Edinburgh'],
            'caption' => ['text' => "Erste Zeile\nzweite Zeile"],
            'like_count' => 3,
            'image_versions2' => ['candidates' => [
                ['url' => 'https://cdn.example/small.jpg', 'width' => 320, 'height' => 240],
                ['url' => 'https://cdn.example/large.jpg', 'width' => 1600, 'height' => 1200],
            ]],
            'video_versions' => null,
            'carousel_media' => null,
        ];
    }

    private function makeProfile(): Profile
    {
        return (new Profile())
            ->setId(42)
            ->setNetwork('instagram_profile')
            ->setIdentifier('https://www.instagram.com/edcriticalmass/');
    }
}
