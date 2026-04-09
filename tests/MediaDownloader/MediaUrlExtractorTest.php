<?php declare(strict_types=1);

namespace App\Tests\MediaDownloader;

use App\Entity\Item;
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
}
