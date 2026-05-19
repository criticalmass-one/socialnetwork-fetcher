<?php declare(strict_types=1);

namespace App\MediaDownloader;

use App\Entity\Item;
use App\Entity\Profile;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MediaDownloadService
{
    private const YTDLP_PHOTO_NETWORKS = [
        'instagram_profile',
        'instagram_photo',
        'threads_profile',
        'threads_post',
        'facebook_page',
    ];

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly MediaUrlExtractor $mediaUrlExtractor,
        private readonly PhotoDownloader $photoDownloader,
        private readonly VideoDownloader $videoDownloader,
        private readonly YtDlpPhotoDownloader $ytDlpPhotoDownloader,
        private readonly EntityManagerInterface $entityManager,
        private readonly ItemRepository $itemRepository,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function downloadMedia(Item $item, bool $photo = true, bool $video = true): void
    {
        $profile = $item->getProfile();

        if (!$profile) {
            return;
        }

        $item->setMediaStatus('downloading');
        $item->setMediaError(null);
        $this->entityManager->flush();

        $errors = [];
        $photoCount = 0;
        $videoDownloaded = false;

        if ($photo) {
            try {
                $paths = $this->downloadPhotos($item, $profile);
                if (!empty($paths)) {
                    $item->setPhotoPaths($paths);
                    $photoCount = count($paths);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Photo download failed for item {itemId}: {message}', [
                    'itemId' => $item->getId(),
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
                $errors[] = 'Photo: ' . $e->getMessage();
            }
        }

        if ($video) {
            try {
                $videoUrl = $this->mediaUrlExtractor->extractVideoUrl($item);

                if ($videoUrl && $this->videoDownloader->isAvailable()) {
                    $path = $this->videoDownloader->download($videoUrl, $profile->getId(), $item->getId());
                    $item->setVideoPath($path);
                    $videoDownloaded = true;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Video download failed for item {itemId}: {message}', [
                    'itemId' => $item->getId(),
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
                $errors[] = 'Video: ' . $e->getMessage();
            }
        }

        // Photo-only posts (e.g. Instagram /p/…) typically fail the video step. If
        // we successfully grabbed at least one photo, treat the run as completed
        // and keep the video failure only as a soft warning in mediaError.
        $videoOnlyFailure = $photoCount > 0 && !$videoDownloaded && $this->isVideoOnlyFailure($errors);

        if (!empty($errors) && !$videoOnlyFailure) {
            $item->setMediaStatus('failed');
            $item->setMediaError(implode("\n", $errors));
        } else {
            $item->setMediaStatus('completed');
            $item->setMediaError($videoOnlyFailure ? implode("\n", $errors) : null);
        }

        $this->entityManager->flush();
    }

    /** @param list<string> $errors */
    private function isVideoOnlyFailure(array $errors): bool
    {
        foreach ($errors as $error) {
            if (!str_starts_with($error, 'Video:')) {
                return false;
            }
        }

        return $errors !== [];
    }

    /**
     * @return list<string>
     */
    private function downloadPhotos(Item $item, Profile $profile): array
    {
        $networkIdentifier = $profile->getNetwork()?->getIdentifier();
        $permalink = $item->getPermalink();

        // Use yt-dlp for networks where it can extract carousel/original photos
        if ($permalink
            && $networkIdentifier
            && in_array($networkIdentifier, self::YTDLP_PHOTO_NETWORKS, true)
            && $this->ytDlpPhotoDownloader->isAvailable()
        ) {
            $paths = $this->ytDlpPhotoDownloader->download($permalink, $profile->getId(), $item->getId());

            if (!empty($paths)) {
                return $paths;
            }
        }

        // Fallback: extract URLs from raw data and download directly
        $photoUrls = $this->mediaUrlExtractor->extractPhotoUrls($item);
        $paths = [];

        foreach ($photoUrls as $index => $url) {
            $paths[] = $this->photoDownloader->download($url, $profile->getId(), $item->getId(), $index);
        }

        return $paths;
    }

    public function downloadNewItemsForProfile(Profile $profile): void
    {
        $items = $this->itemRepository->findBy([
            'profile' => $profile,
            'mediaStatus' => null,
        ]);

        foreach ($items as $item) {
            $this->downloadMedia($item, $profile->isSavePhotos(), $profile->isSaveVideos());
        }
    }
}
