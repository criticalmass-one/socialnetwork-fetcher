<?php declare(strict_types=1);

namespace App\MediaDownloader;

use Symfony\Component\Process\Process;

class VideoDownloader
{
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

        // Find the output file
        $files = glob(sprintf('%s/video.*', $outputDir));

        if (empty($files)) {
            throw new \RuntimeException('yt-dlp produced no output file');
        }

        // Return relative path
        $absolutePath = $files[0];

        return ltrim(str_replace($this->mediaDirectory, '', $absolutePath), '/');
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
