<?php declare(strict_types=1);

namespace App\Command;

use App\FeedFetcher\FeedFetcherInterface;
use App\FeedFetcher\FetchInfo;
use App\FeedFetcher\FetchResult;
use App\Repository\NetworkRepository;
use Cron\CronExpression;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

#[AsCommand(
    name: 'app:fetch-scheduled',
    description: 'Fetch feeds for networks whose cron expression matches the current time',
)]
class ScheduledFetchCommand extends Command
{
    public function __construct(
        private readonly NetworkRepository $networkRepository,
        private readonly FeedFetcherInterface $feedFetcher,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, welche Netzwerke abgerufen würden')
            ->addOption('count', 'c', InputOption::VALUE_REQUIRED, 'Anzahl Items pro Profil', '50')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $count = (int) $input->getOption('count');
        $now = new \DateTimeImmutable();

        $networks = $this->networkRepository->findAll();
        $dueNetworks = [];

        foreach ($networks as $network) {
            $expression = $network->getCronExpression();

            if ($expression === null || $expression === '') {
                continue;
            }

            $cron = new CronExpression($expression);

            if ($cron->isDue($now)) {
                $dueNetworks[] = $network->getIdentifier();
            }
        }

        if ($dueNetworks === []) {
            $io->info(sprintf('[%s] Keine Netzwerke fällig.', $now->format('H:i')));
            return Command::SUCCESS;
        }

        $io->info(sprintf('[%s] Fällige Netzwerke: %s', $now->format('H:i'), implode(', ', $dueNetworks)));

        if ($dryRun) {
            $io->success(sprintf('[Dry-Run] Würde %d Netzwerke fetchen.', count($dueNetworks)));
            return Command::SUCCESS;
        }

        // Ein Netzwerk, dessen letzter Lauf noch nicht fertig ist, wird übersprungen,
        // damit nicht zwei Läufe dieselben Profile gleichzeitig abrufen.
        /** @var LockInterface[] $locks */
        $locks = [];

        foreach ($dueNetworks as $networkIdentifier) {
            $lock = $this->lockFactory->createLock(sprintf('fetch-scheduled-%s', $networkIdentifier), null);

            if (!$lock->acquire()) {
                $io->warning(sprintf('%s: vorheriger Lauf noch aktiv, übersprungen.', $networkIdentifier));
                continue;
            }

            $locks[$networkIdentifier] = $lock;
        }

        if ($locks === []) {
            return Command::SUCCESS;
        }

        $fetchInfo = new FetchInfo();
        $fetchInfo->setCount($count);

        foreach (array_keys($locks) as $networkIdentifier) {
            $fetchInfo->addNetwork($networkIdentifier);
        }

        $callback = function (FetchResult $fetchResult) use ($io): void {
            $io->writeln(sprintf(
                '  <info>%s</info>: %d Items',
                $fetchResult->getProfile()->getIdentifier(),
                $fetchResult->getCounterFetched(),
            ));
        };

        try {
            $this->feedFetcher
                ->fetch($fetchInfo, $callback)
                ->persist();
        } finally {
            foreach ($locks as $lock) {
                $lock->release();
            }
        }

        $io->success('Fertig.');

        return Command::SUCCESS;
    }
}
