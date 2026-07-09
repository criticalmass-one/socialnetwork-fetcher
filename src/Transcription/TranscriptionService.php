<?php declare(strict_types=1);

namespace App\Transcription;

use App\Entity\Item;
use App\Entity\Profile;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Orchestrates video transcription and its status lifecycle
 * (null → pending → running → completed/failed), mirroring
 * {@see \App\MediaDownloader\MediaDownloadService}. The heavy lifting is done by
 * {@see WhisperTranscriber}; this class owns queueing and persistence.
 */
class TranscriptionService
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly WhisperTranscriber $whisperTranscriber,
        private readonly EntityManagerInterface $entityManager,
        private readonly ItemRepository $itemRepository,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Transcribe a single item's downloaded video. Items without a video are
     * silently skipped (transcriptStatus reset to null). The transcript and the
     * resulting status are persisted.
     */
    public function transcribe(Item $item): void
    {
        $profile = $item->getProfile();
        $videoPath = $item->getVideoPath();

        if (!$profile || $videoPath === null) {
            $item->setTranscriptStatus(null);
            $this->entityManager->flush();

            return;
        }

        $item->setTranscriptStatus('running');
        $item->setTranscriptError(null);
        $this->entityManager->flush();

        try {
            $transcript = $this->whisperTranscriber->transcribe($videoPath, $profile->getId(), $item->getId());

            $item->setTranscript($transcript);
            $item->setTranscriptStatus('completed');
            $item->setTranscriptError(null);
        } catch (\Throwable $e) {
            $this->logger->error('Transcription failed for item {itemId}: {message}', [
                'itemId' => $item->getId(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $item->setTranscriptStatus('failed');
            $item->setTranscriptError($e->getMessage());
        }

        $this->entityManager->flush();
    }

    /**
     * Queue a single item for (re)transcription. The actual work is performed
     * out-of-band by `app:transcribe --pending`, so this returns immediately.
     */
    public function queueItem(Item $item): void
    {
        $item->setTranscriptStatus('pending');
        $item->setTranscriptError(null);
        $this->entityManager->flush();
    }

    /**
     * Queue a profile's video items for (re)transcription. By default this covers
     * items with a video that have no transcript yet or whose last attempt failed.
     * With $force = true, every item with a video is re-queued. Returns the number
     * of items queued.
     *
     * @return int
     */
    public function queueProfile(Profile $profile, bool $force = false): int
    {
        $items = $force
            ? array_filter($this->itemRepository->findBy(['profile' => $profile]), fn (Item $i) => $i->hasVideo())
            : $this->itemRepository->findTranscribableForProfile($profile);

        foreach ($items as $item) {
            $item->setTranscriptStatus('pending');
            $item->setTranscriptError(null);
        }

        $this->entityManager->flush();

        return count($items);
    }

    /**
     * Transcribe all items currently queued (transcriptStatus = 'pending').
     * Returns the number of items processed.
     */
    public function transcribePendingItems(?int $limit = null): int
    {
        $items = $this->itemRepository->findBy(
            ['transcriptStatus' => 'pending'],
            ['id' => 'ASC'],
            $limit,
        );

        foreach ($items as $item) {
            $this->transcribe($item);
        }

        return count($items);
    }
}
