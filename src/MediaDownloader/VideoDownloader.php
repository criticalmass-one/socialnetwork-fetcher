<?php declare(strict_types=1);

namespace App\MediaDownloader;

use Symfony\Component\Process\Process;

class VideoDownloader
{
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'mkv', 'm4v', 'avi'];

    public function __construct(
        private readonly string $mediaDirectory,
        private readonly string $ytDlpCookiesFile = '',
    ) {
    }

    public function download(string $url, int $profileId, int $itemId): string
    {
        $outputDir = sprintf('%s/%d/%d', $this->mediaDirectory, $profileId, $itemId);

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputTemplate = sprintf('%s/video.%%(ext)s', $outputDir);

        $process = new Process([
            'yt-dlp',
            '--no-playlist',
            ...$this->cookieArgs(),
            '--max-filesize', '100M',
            '-o', $outputTemplate,
            $url,
        ]);

        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->removeDirectoryIfEmpty($outputDir);

            throw new \RuntimeException(sprintf('yt-dlp failed: %s', $process->getErrorOutput() ?: $process->getOutput()));
        }

        // Find the actual video file — yt-dlp also writes sidecars (.info.json,
        // .description, .live_chat.json, .vtt, …) which would otherwise match.
        $absolutePath = $this->findVideoFile($outputDir);

        if ($absolutePath === null) {
            $this->removeDirectoryIfEmpty($outputDir);

            throw new \RuntimeException('yt-dlp produced no video output file');
        }

        return ltrim(str_replace($this->mediaDirectory, '', $absolutePath), '/');
    }

    private function findVideoFile(string $outputDir): ?string
    {
        $found = glob(sprintf('%s/video.*', $outputDir)) ?: [];

        foreach ($found as $path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extension, self::VIDEO_EXTENSIONS, true)) {
                continue;
            }
            if (!is_file($path) || filesize($path) === 0) {
                continue;
            }
            return $path;
        }

        return null;
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

    private function removeDirectoryIfEmpty(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);

        if ($entries !== false && count($entries) === 2) {
            @rmdir($dir);
        }
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
