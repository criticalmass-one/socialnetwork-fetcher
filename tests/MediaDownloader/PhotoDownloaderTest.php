<?php declare(strict_types=1);

namespace App\Tests\MediaDownloader;

use App\MediaDownloader\PhotoDownloader;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class PhotoDownloaderTest extends TestCase
{
    public function testDownloadWritesFileAndReturnsPath(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn(['content-type' => ['image/jpeg']]);
        $response->method('getContent')->willReturn('fake-image-data');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->with('GET', 'https://example.com/photo.jpg')
            ->willReturn($response);

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('write')
            ->with('42/100/photo_0.jpg', 'fake-image-data');

        $downloader = new PhotoDownloader($httpClient, $storage);
        $path = $downloader->download('https://example.com/photo.jpg', 42, 100, 0);

        $this->assertSame('42/100/photo_0.jpg', $path);
    }

    public function testDownloadWithIndexCreatesNumberedFile(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn(['content-type' => ['image/jpeg']]);
        $response->method('getContent')->willReturn('fake-image-data');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('write')
            ->with('42/100/photo_2.jpg', 'fake-image-data');

        $downloader = new PhotoDownloader($httpClient, $storage);
        $path = $downloader->download('https://example.com/photo.jpg', 42, 100, 2);

        $this->assertSame('42/100/photo_2.jpg', $path);
    }

    public function testDownloadResolvesExtensionFromContentType(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn(['content-type' => ['image/png']]);
        $response->method('getContent')->willReturn('png-data');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('write')
            ->with('1/2/photo_0.png', 'png-data');

        $downloader = new PhotoDownloader($httpClient, $storage);
        $path = $downloader->download('https://example.com/image', 1, 2, 0);

        $this->assertSame('1/2/photo_0.png', $path);
    }

    public function testDownloadResolvesExtensionFromUrl(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn(['content-type' => ['application/octet-stream']]);
        $response->method('getContent')->willReturn('webp-data');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('write')
            ->with('1/2/photo_0.webp', 'webp-data');

        $downloader = new PhotoDownloader($httpClient, $storage);
        $path = $downloader->download('https://example.com/image.webp', 1, 2, 0);

        $this->assertSame('1/2/photo_0.webp', $path);
    }

    public function testDownloadDefaultsToJpg(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn(['content-type' => ['application/octet-stream']]);
        $response->method('getContent')->willReturn('data');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('write')
            ->with('1/2/photo_0.jpg', 'data');

        $downloader = new PhotoDownloader($httpClient, $storage);
        $path = $downloader->download('https://example.com/image', 1, 2);

        $this->assertSame('1/2/photo_0.jpg', $path);
    }
}
