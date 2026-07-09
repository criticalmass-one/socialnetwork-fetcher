<?php declare(strict_types=1);

namespace App\Transcription;

use Symfony\Component\Process\Process;

/**
 * Low-level video transcription via whisper.cpp. Extracts the audio track from a
 * downloaded video with ffmpeg, then runs the whisper.cpp CLI on it. Mirrors
 * {@see \App\MediaDownloader\VideoDownloader} in structure: file paths are
 * relative to the configured media directory, availability is probed via the
 * external binaries, and failures surface as RuntimeExceptions.
 */
class WhisperTranscriber
{
    public function __construct(
        private readonly string $mediaDirectory,
        private readonly string $whisperCliPath = 'whisper-cli',
        private readonly string $whisperModelPath = '',
        private readonly string $whisperLanguage = 'auto',
    ) {
    }

    /**
     * Transcribe the video stored at $videoRelativePath (relative to the media
     * directory) and return the transcript text. Intermediate audio/text files
     * are written next to the video and cleaned up afterwards.
     */
    public function transcribe(string $videoRelativePath, int $profileId, int $itemId): string
    {
        $videoPath = sprintf('%s/%s', $this->mediaDirectory, $videoRelativePath);

        if (!is_file($videoPath)) {
            throw new \RuntimeException(sprintf('Video file not found: %s', $videoRelativePath));
        }

        $workDir = sprintf('%s/%d/%d', $this->mediaDirectory, $profileId, $itemId);

        if (!is_dir($workDir)) {
            mkdir($workDir, 0755, true);
        }

        $audioPath = $workDir . '/audio.wav';
        $outputPrefix = $workDir . '/transcript';
        $textPath = $outputPrefix . '.txt';

        try {
            $this->extractAudio($videoPath, $audioPath);
            $this->runWhisper($audioPath, $outputPrefix);

            if (!is_file($textPath)) {
                throw new \RuntimeException('whisper.cpp produced no transcript output file');
            }

            return trim((string) file_get_contents($textPath));
        } finally {
            @unlink($audioPath);
            @unlink($textPath);
        }
    }

    private function extractAudio(string $videoPath, string $audioPath): void
    {
        // whisper.cpp expects 16 kHz mono PCM WAV.
        $process = new Process([
            'ffmpeg',
            '-y',
            '-i', $videoPath,
            '-vn',
            '-ar', '16000',
            '-ac', '1',
            '-c:a', 'pcm_s16le',
            $audioPath,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'ffmpeg audio extraction failed: %s',
                $process->getErrorOutput() ?: $process->getOutput(),
            ));
        }
    }

    private function runWhisper(string $audioPath, string $outputPrefix): void
    {
        $process = new Process([
            $this->whisperCliPath,
            '-m', $this->whisperModelPath,
            '-f', $audioPath,
            '-l', $this->whisperLanguage !== '' ? $this->whisperLanguage : 'auto',
            '-nt',
            '-otxt',
            '-of', $outputPrefix,
        ]);
        $process->setTimeout(1800);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'whisper.cpp failed: %s',
                $process->getErrorOutput() ?: $process->getOutput(),
            ));
        }
    }

    /**
     * True only when the whisper.cpp CLI, the model file and ffmpeg are all
     * present, so callers can gracefully skip transcription otherwise.
     */
    public function isAvailable(): bool
    {
        return $this->whisperModelPath !== ''
            && is_file($this->whisperModelPath)
            && $this->binaryRuns([$this->whisperCliPath, '--help'])
            && $this->binaryRuns(['ffmpeg', '-version']);
    }

    /** @param list<string> $command */
    private function binaryRuns(array $command): bool
    {
        try {
            $process = new Process($command);
            $process->setTimeout(10);
            $process->run();

            // Exit code 127 means "command not found"; any other outcome means
            // the binary exists and could be invoked.
            return $process->getExitCode() !== 127;
        } catch (\Throwable) {
            return false;
        }
    }
}
