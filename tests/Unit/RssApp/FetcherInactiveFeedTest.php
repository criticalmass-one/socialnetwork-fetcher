<?php declare(strict_types=1);

namespace App\Tests\Unit\RssApp;

use App\FeedFetcher\FetchInfo;
use App\Model\Profile;
use App\NetworkFeedFetcher\Instagram\InstagramFeedFetcher;
use App\RssApp\RssAppInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FetcherInactiveFeedTest extends TestCase
{
    private const ITEM = [
        'url' => 'https://www.instagram.com/p/abc',
        'title' => 'Titel',
        'description_text' => 'Text',
        'date_published' => '2026-10-22T15:30:07.000Z',
    ];

    public function testInactiveFeedMarksProfileAsFailedButKeepsItems(): void
    {
        $profile = $this->makeProfile();
        $fetcher = new InstagramFeedFetcher(new NullLogger(), $this->rssAppReturning([
            'id' => 'feed-1',
            'is_active' => false,
            'items' => [self::ITEM],
        ]));

        $items = $fetcher->fetch($profile, new FetchInfo());

        self::assertCount(1, $items, 'already known items are still returned (deduplicated on persist)');
        self::assertNotNull($profile->getLastFetchFailureDateTime());
        self::assertStringContainsString('deaktiviert', (string) $profile->getLastFetchFailureError());
        self::assertStringContainsString('feed-1', (string) $profile->getLastFetchFailureError());
    }

    public function testActiveFeedDoesNotMarkProfileAsFailed(): void
    {
        $profile = $this->makeProfile();
        $fetcher = new InstagramFeedFetcher(new NullLogger(), $this->rssAppReturning([
            'id' => 'feed-1',
            'is_active' => true,
            'items' => [self::ITEM],
        ]));

        $items = $fetcher->fetch($profile, new FetchInfo());

        self::assertCount(1, $items);
        self::assertNull($profile->getLastFetchFailureDateTime());
        self::assertNull($profile->getLastFetchFailureError());
    }

    public function testMissingIsActiveFieldIsTreatedAsActive(): void
    {
        $profile = $this->makeProfile();
        $fetcher = new InstagramFeedFetcher(new NullLogger(), $this->rssAppReturning([
            'id' => 'feed-1',
            'items' => [],
        ]));

        $fetcher->fetch($profile, new FetchInfo());

        self::assertNull($profile->getLastFetchFailureError());
    }

    public function testRepeatedFailureWithSameMessageIsDetectedAgain(): void
    {
        // FeedFetcher detects a fetcher-side failure by the failure timestamp
        // changing. A profile that already carries the identical error from the
        // previous run must still get a fresh timestamp.
        $profile = $this->makeProfile();
        $fetcher = new InstagramFeedFetcher(new NullLogger(), $this->rssAppReturning([
            'id' => 'feed-1',
            'is_active' => false,
            'items' => [],
        ]));

        $fetcher->fetch($profile, new FetchInfo());
        $firstError = $profile->getLastFetchFailureError();
        $firstDateTime = $profile->getLastFetchFailureDateTime();

        $fetcher->fetch($profile, new FetchInfo());

        self::assertSame($firstError, $profile->getLastFetchFailureError());
        self::assertNotSame($firstDateTime, $profile->getLastFetchFailureDateTime());
    }

    private function makeProfile(): Profile
    {
        $profile = new Profile();
        $profile
            ->setId(2179)
            ->setIdentifier('https://www.instagram.com/torsten.franz.lg/')
            ->setNetwork('instagram_profile');
        $profile->setRssAppFeedId('feed-1');

        return $profile;
    }

    private function rssAppReturning(array $feed): RssAppInterface
    {
        $rssApp = $this->createStub(RssAppInterface::class);
        $rssApp->method('feedExists')->willReturn(true);
        $rssApp->method('getFeed')->willReturn($feed);

        return $rssApp;
    }
}
