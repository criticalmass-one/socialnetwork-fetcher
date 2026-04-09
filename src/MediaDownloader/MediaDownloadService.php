<?php declare(strict_types=1);

namespace App\MediaDownloader;

use App\Entity\Item;
use App\Entity\Profile;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class MediaDownloadService
{
    private const YTDLP_PHOTO_NETWORKS = ['instagram_profile', 'threads_profile', 'facebook_page'];

    public function __construct(
        private readonly MediaUrlExtractor $mediaUrlExtractor,
        private readonly PhotoDownloader $photoDownloader,
        private readonly VideoDownloader $videoDownloader,
        private readonly YtDlpPhotoDownloader $ytDlpPhotoDownloader,
        private readonly EntityManagerInterface $entityManager,
        private readonly ItemRepository $itemRepository,
    ) {
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

        if ($photo) {
            try {
                $paths = $this->downloadPhotos($item, $profile);
                if (!empty($paths)) {
                    $item->setPhotoPaths($paths);
                }
            } catch (\Exception $e) {
                $errors[] = 'Photo: ' . $e->getMessage();
            }
        }

        if ($video) {
            try {
                $videoUrl = $this->mediaUrlExtractor->extractVideoUrl($item);

                if ($videoUrl && $this->videoDownloader->isAvailable()) {
                    $path = $this->videoDownloader->download($videoUrl, $profile->getId(), $item->getId());
                    $item->setVideoPath($path);
                }
            } catch (\Exception $e) {
                $errors[] = 'Video: ' . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            $item->setMediaStatus('failed');
            $item->setMediaError(implode("\n", $errors));
        } else {
            $item->setMediaStatus('completed');
        }

        $this->entityManager->flush();
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
