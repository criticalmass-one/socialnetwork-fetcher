<?php declare(strict_types=1);

namespace App\MediaDownloader;

use Symfony\Component\Process\Process;

class YtDlpPhotoDownloader
{
    public function __construct(
        private readonly string $mediaDirectory,
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

        $files = glob(sprintf('%s/photo_*.*', $outputDir));

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
