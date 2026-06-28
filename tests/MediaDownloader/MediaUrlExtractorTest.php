<?php declare(strict_types=1);

namespace App\Tests\MediaDownloader;

use App\Entity\Item;
use App\Entity\Network;
use App\Entity\Profile;
use App\MediaDownloader\MediaUrlExtractor;
use PHPUnit\Framework\TestCase;

class MediaUrlExtractorTest extends TestCase
{
    private MediaUrlExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new MediaUrlExtractor();
    }

    public function testExtractPhotoUrlsFromRssAppFormat(): void
    {
        $item = new Item();
        $item->setRaw(json_encode([
            'thumbnail' => 'https://example.com/photo.jpg',
            'title' => 'Test',
        ]));

        $this->assertSame(['https://example.com/photo.jpg'], $this->extractor->extractPhotoUrls($item));
    }

    public function testExtractPhotoUrlsFromBlueskyMultipleImages(): void
    {
        $item = new Item();
        $item->setRaw(json_encode([
            'embed' => [
                'images' => [
                    ['fullsize' => 'https://bsky.example.com/image1.jpg', 'thumb' => 'https://bsky.example.com/thumb1.jpg'],
                    ['fullsize' => 'https://bsky.example.com/image2.jpg', 'thumb' => 'https://bsky.example.com/thumb2.jpg'],
                    ['fullsize' => 'https://bsky.example.com/image3.jpg'],
                ],
            ],
        ]));

        $this->assertSame([
            'https://bsky.example.com/image1.jpg',
            'https://bsky.example.com/image2.jpg',
            'https://bsky.example.com/image3.jpg',
        ], $this->extractor->extractPhotoUrls($item));
    }

    public function testExtractPhotoUrlsFromBlueskyThumbFallback(): void
    {
        $item = new Item();
        $item->setRaw(json_encode([
            'embed' => [
                'images' => [
                    ['thumb' => 'https://bsky.example.com/thumb.jpg'],
                ],
            ],
        ]));

        $this->assertSame(['https://bsky.example.com/thumb.jpg'], $this->extractor->extractPhotoUrls($item));
    }

    public function testExtractPhotoUrlsFromMastodonMultipleAttachments(): void
    {
        $item = new Item();
        $item->setRaw(json_encode([
            'media_attachments' => [
                ['type' => 'image', 'url' => 'https://mastodon.example.com/media/photo1.png'],
                ['type' => 'video', 'url' => 'https://mastodon.example.com/media/video.mp4'],
                ['type' => 'image', 'url' => 'https://mastodon.example.com/media/photo2.png'],
            ],
        ]));

        $this->assertSame([
            'https://mastodon.example.com/media/photo1.png',
            'https://mastodon.example.com/media/photo2.png',
        ], $this->extractor->extractPhotoUrls($item));
    }

    public function testExtractPhotoUrlsFromRssAppHtmlSingleImage(): void
    {
        $item = new Item();
        $item->setRaw(json_encode([
            'description_html' => '<p>Caption</p><img src="https://scontent.cdninstagram.com/v/original_photo.jpg?query=1" />',
            'thumbnail' => 'https://example.com/low_res_thumb.jpg',
        ]));

        $urls = $this->extractor->extractPhotoUrls($item);
        $this->assertSame(['https://scontent.cdninstagram.com/v/original_photo.jpg?query=1'], $urls);
    }

    public function testExtractPhotoUrlsFromRssAppHtmlCarousel(): void
    {
        $item = new Item();
        $item->setRaw(json_encode([
            'description_html' => '<img src="https://cdn.example.com/photo1.jpg" /><img src="https://cdn.example.com/photo2.jpg" /><img src="https://cdn.example.com/photo3.jpg" />',
            'thumbnail' => 'https://example.com/thumb.jpg',
        ]));

        $urls = $this->extractor->extractPhotoUrls($item);
        $this->assertCount(3, $urls);
        $this->assertSame('https://cdn.example.com/photo1.jpg', $urls[0]);
        $this->assertSame('https://cdn.example.com/photo2.jpg', $urls[1]);
        $this->assertSame('https://cdn.example.com/photo3.jpg', $urls[2]);
    }

    public function testExtractPhotoUrlsFromRssAppHtmlDeduplicates(): void
    {
        $item = new Item();
        $item->setRaw(json_encode([
            'description_html' => '<img src="https://cdn.example.com/photo.jpg" /><img src="https://cdn.example.com/photo.jpg" />',
        ]));

        $urls = $this->extractor->extractPhotoUrls($item);
        $this->assertCount(1, $urls);
    }

    public function testExtractPhotoUrlsFromRssAppHtmlSkipsRelativeUrls(): void
    {
        $item = new Item();
        $item->setRaw(json_encode([
            'description_html' => '<img src="/local/image.jpg" /><img src="https://cdn.example.com/photo.jpg" />',
        ]));

        $urls = $this->extractor->extractPhotoUrls($item);
        $this->assertSame(['https://cdn.example.com/photo.jpg'], $urls);
    }

    public function testExtractPhotoUrlsFallsBackToThumbnailWhenNoHtmlImages(): void
    {
        $item = new Item();
        $item->setRaw(json_encode([
            'description_html' => '<p>Text only, no images</p>',
            'thumbnail' => 'https://example.com/thumb.jpg',
        ]));

        $this->assertSame(['https://example.com/thumb.jpg'], $this->extractor->extractPhotoUrls($item));
    }

    public function testExtractPhotoUrlsReturnsEmptyForNoRaw(): void
    {
        $item = new Item();

        $this->assertSame([], $this->extractor->extractPhotoUrls($item));
    }

    public function testExtractPhotoUrlsReturnsEmptyForEmptyJson(): void
    {
        $item = new Item();
        $item->setRaw(json_encode(['title' => 'No image here']));

        $this->assertSame([], $this->extractor->extractPhotoUrls($item));
    }

    public function testExtractPhotoUrlsReturnsEmptyForInvalidJson(): void
    {
        $item = new Item();
        $item->setRaw('not json');

        $this->assertSame([], $this->extractor->extractPhotoUrls($item));
    }

    public function testExtractPhotoUrlsSkipsEmptyThumbnail(): void
    {
        $item = new Item();
        $item->setRaw(json_encode(['thumbnail' => '']));

        $this->assertSame([], $this->extractor->extractPhotoUrls($item));
    }

    public function testExtractVideoUrlReturnsPermalink(): void
    {
        $item = new Item();
        $item->setPermalink('https://instagram.com/p/abc123');

        $this->assertSame('https://instagram.com/p/abc123', $this->extractor->extractVideoUrl($item));
    }

    public function testExtractVideoUrlReturnsNullWithoutPermalink(): void
    {
        $item = new Item();

        $this->assertNull($this->extractor->extractVideoUrl($item));
    }

    private function createItemForNetwork(string $networkIdentifier): Item
    {
        $network = new Network();
        $network->setIdentifier($networkIdentifier);

        $profile = new Profile();
        $profile->setNetwork($network);

        $item = new Item();
        $item->setProfile($profile);
        $item->setPermalink('https://example.com/post/1');

        return $item;
    }

    public function testExtractVideoUrlReturnsPermalinkForMastodonVideoPost(): void
    {
        $item = $this->createItemForNetwork('mastodon');
        $item->setRaw(json_encode([
            'media_attachments' => [
                ['type' => 'image', 'url' => 'https://mastodon.example.com/media/photo.png'],
                ['type' => 'video', 'url' => 'https://mastodon.example.com/media/video.mp4'],
            ],
        ]));

        $this->assertSame('https://example.com/post/1', $this->extractor->extractVideoUrl($item));
    }

    public function testExtractVideoUrlReturnsPermalinkForMastodonGifv(): void
    {
        $item = $this->createItemForNetwork('mastodon');
        $item->setRaw(json_encode([
            'media_attachments' => [
                ['type' => 'gifv', 'url' => 'https://mastodon.example.com/media/animation.mp4'],
            ],
        ]));

        $this->assertSame('https://example.com/post/1', $this->extractor->extractVideoUrl($item));
    }

    public function testExtractVideoUrlReturnsNullForMastodonPhotoOnlyPost(): void
    {
        $item = $this->createItemForNetwork('mastodon');
        $item->setRaw(json_encode([
            'media_attachments' => [
                ['type' => 'image', 'url' => 'https://mastodon.example.com/media/photo.png'],
            ],
        ]));

        $this->assertNull($this->extractor->extractVideoUrl($item));
    }

    public function testExtractVideoUrlReturnsNullForMastodonTextPost(): void
    {
        $item = $this->createItemForNetwork('mastodon');
        $item->setRaw(json_encode([
            'media_attachments' => [],
        ]));

        $this->assertNull($this->extractor->extractVideoUrl($item));
    }

    public function testExtractVideoUrlReturnsNullForMastodonPostWithoutRaw(): void
    {
        $item = $this->createItemForNetwork('mastodon');

        $this->assertNull($this->extractor->extractVideoUrl($item));
    }

    public function testExtractVideoUrlReturnsPermalinkForBlueskyVideoEmbed(): void
    {
        $item = $this->createItemForNetwork('bluesky_profile');
        $item->setRaw(json_encode([
            'post' => [
                'embed' => [
                    '$type' => 'app.bsky.embed.video#view',
                    'playlist' => 'https://video.bsky.app/watch/abc/playlist.m3u8',
                ],
            ],
        ]));

        $this->assertSame('https://example.com/post/1', $this->extractor->extractVideoUrl($item));
    }

    public function testExtractVideoUrlReturnsPermalinkForBlueskyRecordWithMediaVideo(): void
    {
        $item = $this->createItemForNetwork('bluesky_profile');
        $item->setRaw(json_encode([
            'post' => [
                'embed' => [
                    '$type' => 'app.bsky.embed.recordWithMedia#view',
                    'media' => [
                        '$type' => 'app.bsky.embed.video#view',
                        'playlist' => 'https://video.bsky.app/watch/abc/playlist.m3u8',
                    ],
                ],
            ],
        ]));

        $this->assertSame('https://example.com/post/1', $this->extractor->extractVideoUrl($item));
    }

    public function testExtractVideoUrlReturnsNullForBlueskyImageEmbed(): void
    {
        $item = $this->createItemForNetwork('bluesky_profile');
        $item->setRaw(json_encode([
            'post' => [
                'embed' => [
                    '$type' => 'app.bsky.embed.images#view',
                    'images' => [
                        ['fullsize' => 'https://bsky.example.com/image.jpg'],
                    ],
                ],
            ],
        ]));

        $this->assertNull($this->extractor->extractVideoUrl($item));
    }

    public function testExtractVideoUrlReturnsNullForBlueskyTextPost(): void
    {
        $item = $this->createItemForNetwork('bluesky_profile');
        $item->setRaw(json_encode([
            'post' => [
                'record' => ['text' => 'Just text, no media'],
            ],
        ]));

        $this->assertNull($this->extractor->extractVideoUrl($item));
    }

    public function testExtractVideoUrlReturnsPermalinkForNonDetectableNetwork(): void
    {
        $item = $this->createItemForNetwork('instagram_profile');

        $this->assertSame('https://example.com/post/1', $this->extractor->extractVideoUrl($item));
    }

    public function testExtractPhotoUrlsFromBlueskyPostNestedEmbed(): void
    {
        $item = new Item();
        $item->setRaw(json_encode([
            'post' => [
                'embed' => [
                    'images' => [
                        ['fullsize' => 'https://bsky.example.com/image1.jpg'],
                        ['thumb' => 'https://bsky.example.com/thumb2.jpg'],
                    ],
                ],
            ],
        ]));

        $this->assertSame([
            'https://bsky.example.com/image1.jpg',
            'https://bsky.example.com/thumb2.jpg',
        ], $this->extractor->extractPhotoUrls($item));
    }
}
