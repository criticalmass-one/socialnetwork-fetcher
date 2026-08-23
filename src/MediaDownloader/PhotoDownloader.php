<?php declare(strict_types=1);

namespace App\MediaDownloader;

use App\Http\SafeHttpFetcher;
use League\Flysystem\FilesystemOperator;

class PhotoDownloader
{
    /** Photos, not disk images. */
    private const MAX_BYTES = 32 * 1024 * 1024;

    public function __construct(
        private readonly SafeHttpFetcher $httpFetcher,
        private readonly FilesystemOperator $defaultStorage,
    ) {
    }

    public function download(string $url, int $profileId, int $itemId, int $index = 0): string
    {
        // $url is lifted out of a third-party payload (RSS.app description
        // HTML, Bluesky/Mastodon attachments) — guard it before fetching.
        $resource = $this->httpFetcher->get($url, self::MAX_BYTES);
        $contentType = $resource->header('content-type') ?? '';
        $content = $resource->content;

        $extension = $this->resolveExtension($contentType, $url);
        $path = sprintf('%d/%d/photo_%d.%s', $profileId, $itemId, $index, $extension);

        $this->defaultStorage->write($path, $content);

        return $path;
    }

    private function resolveExtension(string $contentType, string $url): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
        ];

        foreach ($map as $mime => $ext) {
            if (str_contains($contentType, $mime)) {
                return $ext;
            }
        }

        // Try from URL
        $urlPath = parse_url($url, PHP_URL_PATH);

        if ($urlPath) {
            $ext = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
                return $ext === 'jpeg' ? 'jpg' : $ext;
            }
        }

        return 'jpg';
    }
}
