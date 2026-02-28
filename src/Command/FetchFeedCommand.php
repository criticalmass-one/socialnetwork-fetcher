<?php declare(strict_types=1);

namespace App\Command;

use App\FeedFetcher\FeedFetcher;
use App\FeedFetcher\FetchInfo;
use App\FeedFetcher\FetchResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FetchFeedCommand extends Command
{
    protected FeedFetcher $feedFetcher;

    public function __construct(FeedFetcher $feedFetcher)
    {
        $this->feedFetcher = $feedFetcher;

        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this
            ->setName('feeds:fetch')
            ->setDescription('Fetch feeds')
            ->addArgument('networks', InputArgument::IS_ARRAY)
            ->addOption('fromDateTime', 'f', InputOption::VALUE_REQUIRED)
            ->addOption('untilDateTime', 'u', InputOption::VALUE_REQUIRED)
            ->addOption('includeOldItems', 'i', InputOption::VALUE_NONE)
            ->addOption('count', 'c', InputOption::VALUE_REQUIRED)
            ->addOption('citySlug', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fetchInfo = new FetchInfo();

        if ($input->hasArgument('networks')) {
            foreach ($input->getArgument('networks') as $networkIdentifier) {
                $fetchInfo->addNetwork($networkIdentifier);
            }
        }

        if ($input->hasOption('citySlug') && !empty($input->getOption('citySlug'))) {
            $fetchInfo->setCitySlug($input->getOption('citySlug'));
        }

        if ($input->getOption('count')) {
            $fetchInfo->setCount((int)$input->getOption('count'));
        }

        if ($input->getOption('fromDateTime')) {
            $fetchInfo->setFromDateTime(new \DateTime($input->getOption('fromDateTime')));
        }

        if ($input->getOption('untilDateTime')) {
            $fetchInfo->setUntilDateTime(new \DateTime($input->getOption('untilDateTime')));
        }

        if ($input->getOption('includeOldItems')) {
            $fetchInfo->setIncludeOldItems(true);
        }

        $callback = function (FetchResult $fetchResult) use ($io): void {
            $io->success(sprintf(
                'Fetched %d items from profile %s, %d returned 200, %d returned 4xx, %d returned 5xx.',
                $fetchResult->getCounterFetched(),
                $fetchResult->getSocialNetworkProfile()->getIdentifier(),
                $fetchResult->getCounterPushed200(),
                $fetchResult->getCounterPushed4xx(),
                $fetchResult->getCounterPushed5xx()
            ));
        };

        $this->feedFetcher
            ->fetch($fetchInfo, $callback)
            ->persist();

        return Command::SUCCESS;
    }
}
