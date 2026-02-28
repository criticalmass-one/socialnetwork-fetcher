<?php declare(strict_types=1);

namespace App\Command;

use App\FeedFetcher\FeedFetcherInterface;
use App\NetworkFeedFetcher\NetworkFeedFetcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NetworkListCommand extends Command
{
    public function __construct(
        private readonly FeedFetcherInterface $feedFetcher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('network:list')
            ->setDescription('List all registered networks and their fetcher status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];

        /** @var NetworkFeedFetcherInterface $fetcher */
        foreach ($this->feedFetcher->getNetworkFetcherList() as $fetcher) {
            $rows[] = [
                $fetcher->getNetworkIdentifier(),
                get_class($fetcher),
            ];
        }

        usort($rows, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $io->table(
            ['Network', 'Fetcher'],
            $rows
        );

        return Command::SUCCESS;
    }
}
