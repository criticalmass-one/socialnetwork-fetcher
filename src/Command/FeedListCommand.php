<?php declare(strict_types=1);

namespace App\Command;

use App\FeedFetcher\FeedFetcher;
use App\Model\SocialNetworkProfile;
use App\NetworkFeedFetcher\NetworkFeedFetcherInterface;
use App\ProfileFetcher\ProfileFetcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FeedListCommand extends Command
{
    public function __construct(
        private readonly ProfileFetcherInterface $profileFetcher,
        private readonly FeedFetcher $feedFetcher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('feed:list')
            ->setDescription('List all feeds/profiles')
            ->addArgument('networks', InputArgument::IS_ARRAY, 'Filter by network identifiers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $networks = $input->getArgument('networks');

        if (0 === count($networks)) {
            /** @var NetworkFeedFetcherInterface $fetcher */
            foreach ($this->feedFetcher->getNetworkFetcherList() as $fetcher) {
                $networks[] = $fetcher->getNetworkIdentifier();
            }
        }

        $profiles = $this->profileFetcher->fetchByNetworkIdentifiers($networks);

        if (0 === count($profiles)) {
            $io->warning('No feeds found.');
            return Command::SUCCESS;
        }

        $rows = [];

        /** @var SocialNetworkProfile $profile */
        foreach ($profiles as $profile) {
            $rows[] = [
                $profile->getId(),
                $profile->getNetwork(),
                $profile->getIdentifier(),
                $profile->getAutoFetch() ? 'yes' : 'no',
                $profile->getLastFetchSuccessDateTime()?->format('Y-m-d H:i') ?? '-',
                $profile->getLastFetchFailureDateTime()?->format('Y-m-d H:i') ?? '-',
            ];
        }

        usort($rows, fn (array $a, array $b) => $a[1] <=> $b[1] ?: $a[2] <=> $b[2]);

        $io->table(
            ['ID', 'Network', 'Identifier', 'Auto-Fetch', 'Last Success', 'Last Failure'],
            $rows
        );

        $io->info(sprintf('%d feeds found.', count($rows)));

        return Command::SUCCESS;
    }
}
