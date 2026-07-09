<?php declare(strict_types=1);

namespace App\Command;

use App\Repository\ItemRepository;
use App\Repository\ProfileRepository;
use App\Transcription\TranscriptionService;
use App\Transcription\WhisperTranscriber;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TranscribeCommand extends Command
{
    public function __construct(
        private readonly TranscriptionService $transcriptionService,
        private readonly WhisperTranscriber $whisperTranscriber,
        private readonly ProfileRepository $profileRepository,
        private readonly ItemRepository $itemRepository,
    ) {
        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this
            ->setName('app:transcribe')
            ->setDescription('Transcribe downloaded videos for feed items via whisper.cpp')
            ->addOption('profile-id', null, InputOption::VALUE_REQUIRED, 'Transcribe videos for a specific profile ID')
            ->addOption('item-id', null, InputOption::VALUE_REQUIRED, 'Transcribe the video of a specific item ID')
            ->addOption('pending', null, InputOption::VALUE_NONE, 'Process items queued for transcription (transcriptStatus=pending)')
            ->addOption('retry-failed', null, InputOption::VALUE_NONE, 'Retry previously failed transcriptions')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->whisperTranscriber->isAvailable()) {
            $io->error('whisper.cpp and/or ffmpeg are not available. Set WHISPER_CLI_PATH / WHISPER_MODEL_PATH and install ffmpeg.');

            return Command::FAILURE;
        }

        if ($input->getOption('pending')) {
            $count = $this->transcriptionService->transcribePendingItems();
            $io->success(sprintf('Processed %d pending item(s).', $count));

            return Command::SUCCESS;
        }

        $itemId = $input->getOption('item-id');

        if ($itemId) {
            $item = $this->itemRepository->find((int) $itemId);

            if (!$item) {
                $io->error(sprintf('Item with ID %d not found.', $itemId));

                return Command::FAILURE;
            }

            $this->transcriptionService->transcribe($item);
            $io->success(sprintf('Transcribed item #%d (status: %s).', $item->getId(), $item->getTranscriptStatus() ?? 'skipped'));

            return Command::SUCCESS;
        }

        $profileId = $input->getOption('profile-id');
        $retryFailed = (bool) $input->getOption('retry-failed');

        if ($profileId) {
            $profile = $this->profileRepository->find((int) $profileId);

            if (!$profile) {
                $io->error(sprintf('Profile with ID %d not found.', $profileId));

                return Command::FAILURE;
            }

            $profiles = [$profile];
        } else {
            $profiles = $this->profileRepository->findBy(['deleted' => false]);
            $profiles = array_filter($profiles, fn ($p) => $p->isTranscribeVideos());
        }

        if (empty($profiles)) {
            $io->info('No profiles with video transcription enabled found.');

            return Command::SUCCESS;
        }

        $totalItems = 0;

        foreach ($profiles as $profile) {
            $io->section(sprintf('Profile #%d: %s', $profile->getId(), $profile->getDisplayName()));

            $items = $retryFailed
                ? array_filter(
                    $this->itemRepository->findBy(['profile' => $profile, 'transcriptStatus' => 'failed']),
                    fn ($i) => $i->hasVideo(),
                )
                : $this->itemRepository->findTranscribableForProfile($profile);

            if (empty($items)) {
                $io->text('No videos to transcribe.');

                continue;
            }

            $io->text(sprintf('Transcribing %d video(s)...', count($items)));
            $io->progressStart(count($items));

            foreach ($items as $item) {
                $this->transcriptionService->transcribe($item);
                $io->progressAdvance();
                $totalItems++;
            }

            $io->progressFinish();
        }

        $io->success(sprintf('Processed %d video(s) across %d profile(s).', $totalItems, count($profiles)));

        return Command::SUCCESS;
    }
}
