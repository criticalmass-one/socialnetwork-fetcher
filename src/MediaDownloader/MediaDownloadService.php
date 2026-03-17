<?php declare(strict_types=1);

namespace App\MediaDownloader;

use App\Entity\Item;
use App\Entity\Profile;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class MediaDownloadService
{
    public function __construct(
        private readonly MediaUrlExtractor $mediaUrlExtractor,
        private readonly PhotoDownloader $photoDownloader,
        private readonly VideoDownloader $videoDownloader,
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
                $photoUrls = $this->mediaUrlExtractor->extractPhotoUrls($item);

                if (!empty($photoUrls)) {
                    $paths = [];

                    foreach ($photoUrls as $index => $url) {
                        $paths[] = $this->photoDownloader->download($url, $profile->getId(), $item->getId(), $index);
                    }

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
