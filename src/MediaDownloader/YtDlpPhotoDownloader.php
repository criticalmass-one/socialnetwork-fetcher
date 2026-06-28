<?php declare(strict_types=1);

namespace App\MediaDownloader;

use Symfony\Component\Process\Process;

class YtDlpPhotoDownloader
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    public function __construct(
        private readonly string $mediaDirectory,
        private readonly string $ytDlpCookiesFile = '',
    ) {
    }

    /**
     * @return list<string> Relative paths of downloaded photos
     */
    public function download(string $url, int $profileId, int $itemId): array
    {
        $outputDir = sprintf('%s/%d/%d', $this->mediaDirectory, $profileId, $itemId);

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputTemplate = sprintf('%s/photo_%%(autonumber)s.%%(ext)s', $outputDir);

        $process = new Process([
            'yt-dlp',
            '--no-playlist',
            ...$this->cookieArgs(),
            '--write-thumbnail',
            '--skip-download',
            '--convert-thumbnails', 'jpg',
            '-o', $outputTemplate,
            $url,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('yt-dlp photo extraction failed: %s', $process->getErrorOutput() ?: $process->getOutput()));
        }

        $files = $this->collectImageFiles($outputDir);

        if (empty($files)) {
            return [];
        }

        sort($files);

        $paths = [];

        foreach ($files as $file) {
            $paths[] = ltrim(str_replace($this->mediaDirectory, '', $file), '/');
        }

        return $paths;
    }

    /** @return list<string> */
    private function collectImageFiles(string $outputDir): array
    {
        // yt-dlp may write sidecar files (.info.json, .description, .live_chat.json, …).
        // Only return real image files, and skip zero-byte writes that occasionally
        // happen when a thumbnail extraction partially fails.
        $found = glob(sprintf('%s/photo_*.*', $outputDir)) ?: [];

        $images = [];
        foreach ($found as $path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                continue;
            }
            if (!is_file($path) || filesize($path) === 0) {
                continue;
            }
            $images[] = $path;
        }

        return $images;
    }

    /**
     * yt-dlp cookie arguments for sites that require a login (e.g. Instagram).
     * Only applied when a non-empty cookies file is configured and present.
     *
     * @return list<string>
     */
    private function cookieArgs(): array
    {
        $file = $this->ytDlpCookiesFile;

        if ($file !== '' && is_file($file) && filesize($file) > 0) {
            return ['--cookies', $file];
        }

        return [];
    }

    public function isAvailable(): bool
    {
        $process = new Process(['yt-dlp', '--version']);
        $process->setTimeout(5);

        try {
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception) {
            return false;
        }
    }
}
