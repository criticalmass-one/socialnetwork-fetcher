<?php declare(strict_types=1);

namespace App\MediaDownloader;

use Symfony\Component\Process\Process;

class VideoDownloader
{
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'mkv', 'm4v', 'avi'];

    public function __construct(
        private readonly string $mediaDirectory,
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
            '--max-filesize', '100M',
            '-o', $outputTemplate,
            $url,
        ]);

        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('yt-dlp failed: %s', $process->getErrorOutput() ?: $process->getOutput()));
        }

        // Find the actual video file — yt-dlp also writes sidecars (.info.json,
        // .description, .live_chat.json, .vtt, …) which would otherwise match.
        $absolutePath = $this->findVideoFile($outputDir);

        if ($absolutePath === null) {
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
