<?php declare(strict_types=1);

namespace App\Tests\MediaDownloader;

use App\Http\FetchedResource;
use App\Http\SafeHttpFetcher;
use App\MediaDownloader\PhotoDownloader;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;

class PhotoDownloaderTest extends TestCase
{
    public function testDownloadWritesFileAndReturnsPath(): void
    {
        $fetcher = $this->createMock(SafeHttpFetcher::class);
        $fetcher->expects($this->once())
            ->method('get')
            ->with('https://example.com/photo.jpg', $this->anything())
            ->willReturn($this->resource('https://example.com/photo.jpg', 'image/jpeg', 'fake-image-data'));

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('write')
            ->with('42/100/photo_0.jpg', 'fake-image-data');

        $downloader = new PhotoDownloader($fetcher, $storage);
        $path = $downloader->download('https://example.com/photo.jpg', 42, 100, 0);

        $this->assertSame('42/100/photo_0.jpg', $path);
    }

    public function testDownloadWithIndexCreatesNumberedFile(): void
    {
        $fetcher = $this->fetcherReturning('https://example.com/photo.jpg', 'image/jpeg', 'fake-image-data');

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('write')
            ->with('42/100/photo_2.jpg', 'fake-image-data');

        $downloader = new PhotoDownloader($fetcher, $storage);
        $path = $downloader->download('https://example.com/photo.jpg', 42, 100, 2);

        $this->assertSame('42/100/photo_2.jpg', $path);
    }

    public function testDownloadResolvesExtensionFromContentType(): void
    {
        $fetcher = $this->fetcherReturning('https://example.com/image', 'image/png', 'png-data');

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('write')
            ->with('1/2/photo_0.png', 'png-data');

        $downloader = new PhotoDownloader($fetcher, $storage);
        $path = $downloader->download('https://example.com/image', 1, 2, 0);

        $this->assertSame('1/2/photo_0.png', $path);
    }

    public function testDownloadResolvesExtensionFromUrl(): void
    {
        $fetcher = $this->fetcherReturning('https://example.com/image.webp', 'application/octet-stream', 'webp-data');

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('write')
            ->with('1/2/photo_0.webp', 'webp-data');

        $downloader = new PhotoDownloader($fetcher, $storage);
        $path = $downloader->download('https://example.com/image.webp', 1, 2, 0);

        $this->assertSame('1/2/photo_0.webp', $path);
    }

    public function testDownloadDefaultsToJpg(): void
    {
        $fetcher = $this->fetcherReturning('https://example.com/image', 'application/octet-stream', 'data');

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('write')
            ->with('1/2/photo_0.jpg', 'data');

        $downloader = new PhotoDownloader($fetcher, $storage);
        $path = $downloader->download('https://example.com/image', 1, 2);

        $this->assertSame('1/2/photo_0.jpg', $path);
    }

    private function fetcherReturning(string $url, string $contentType, string $content): SafeHttpFetcher
    {
        $fetcher = $this->createMock(SafeHttpFetcher::class);
        $fetcher->method('get')->willReturn($this->resource($url, $contentType, $content));

        return $fetcher;
    }

    private function resource(string $url, string $contentType, string $content): FetchedResource
    {
        return new FetchedResource($url, 200, $content, ['content-type' => [$contentType]]);
    }
}
