<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Item;
use App\MediaDownloader\MediaDownloadService;
use App\Repository\ItemRepository;
use App\Repository\ProfileRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DownloadMediaCommand extends Command
{
    public function __construct(
        private readonly MediaDownloadService $mediaDownloadService,
        private readonly ProfileRepository $profileRepository,
        private readonly ItemRepository $itemRepository,
    ) {
        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this
            ->setName('app:download-media')
            ->setDescription('Download media (photos/videos) for feed items')
            ->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Download media for a specific profile ID')
            ->addOption('retry-failed', null, InputOption::VALUE_NONE, 'Retry previously failed downloads')
            ->addOption('photos-only', null, InputOption::VALUE_NONE, 'Download only photos')
            ->addOption('videos-only', null, InputOption::VALUE_NONE, 'Download only videos')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $profileId = $input->getOption('profile');
        $retryFailed = $input->getOption('retry-failed');
        $photosOnly = $input->getOption('photos-only');
        $videosOnly = $input->getOption('videos-only');

        $downloadPhotos = !$videosOnly;
        $downloadVideos = !$photosOnly;

        if ($profileId) {
            $profile = $this->profileRepository->find((int) $profileId);

            if (!$profile) {
                $io->error(sprintf('Profile with ID %d not found.', $profileId));

                return Command::FAILURE;
            }

            $profiles = [$profile];
        } else {
            $profiles = $this->profileRepository->findBy(['deleted' => false]);

            // Filter to profiles that have savePhotos or saveVideos enabled
            $profiles = array_filter($profiles, fn ($p) => $p->isSavePhotos() || $p->isSaveVideos());
        }

        if (empty($profiles)) {
            $io->info('No profiles with media download enabled found.');

            return Command::SUCCESS;
        }

        $totalItems = 0;

        foreach ($profiles as $profile) {
            $io->section(sprintf('Profile #%d: %s', $profile->getId(), $profile->getDisplayName()));

            $criteria = ['profile' => $profile];

            if ($retryFailed) {
                $criteria['mediaStatus'] = 'failed';
            } else {
                $criteria['mediaStatus'] = null;
            }

            $items = $this->itemRepository->findBy($criteria);

            if (empty($items)) {
                $io->text('No items to process.');

                continue;
            }

            $io->text(sprintf('Processing %d items...', count($items)));
            $io->progressStart(count($items));

            $photo = $profileId ? $downloadPhotos : ($profile->isSavePhotos() && $downloadPhotos);
            $video = $profileId ? $downloadVideos : ($profile->isSaveVideos() && $downloadVideos);

            foreach ($items as $item) {
                $this->mediaDownloadService->downloadMedia($item, $photo, $video);
                $io->progressAdvance();
                $totalItems++;
            }

            $io->progressFinish();
        }

        $io->success(sprintf('Processed %d items across %d profiles.', $totalItems, count($profiles)));

        return Command::SUCCESS;
    }
}
