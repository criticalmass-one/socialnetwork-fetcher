<?php declare(strict_types=1);

namespace App\Command;

use App\Repository\ProfileRepository;
use App\RssApp\RssAppInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rssapp:sync-feed-ids',
    description: 'Prüft RSS.app-Profile und speichert gefundene Feed-IDs in der Datenbank',
)]
class SyncRssAppFeedIdsCommand extends Command
{
    private const RSS_APP_NETWORKS = ['instagram_profile', 'facebook_profile', 'thread'];

    public function __construct(
        private readonly RssAppInterface $rssApp,
        private readonly ProfileRepository $profileRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, nichts speichern')
            ->addOption('network', null, InputOption::VALUE_REQUIRED, 'Nur ein bestimmtes Netzwerk prüfen (z.B. instagram_profile)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Auch Profile mit vorhandener Feed-ID erneut prüfen')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $networkFilter = $input->getOption('network');

        if ($networkFilter !== null && !in_array($networkFilter, self::RSS_APP_NETWORKS, true)) {
            $io->error(sprintf('Ungültiges Netzwerk "%s". Erlaubt: %s', $networkFilter, implode(', ', self::RSS_APP_NETWORKS)));
            return Command::FAILURE;
        }

        $io->info('Lade alle Feeds von RSS.app…');
        $feeds = $this->rssApp->listFeeds();
        $io->info(sprintf('%d Feeds geladen.', count($feeds)));

        $feedsByUrl = [];
        foreach ($feeds as $feed) {
            $sourceUrl = $feed['source_url'] ?? null;
            if ($sourceUrl !== null) {
                $feedsByUrl[$this->normalizeUrl($sourceUrl)] = $feed;
            }
        }

        $profiles = $this->profileRepository->findAll();

        $found = 0;
        $updated = 0;
        $alreadyLinked = 0;
        $notFound = 0;
        $skipped = 0;

        $tableRows = [];

        foreach ($profiles as $profile) {
            $networkIdentifier = $profile->getNetwork()?->getIdentifier();

            if (!in_array($networkIdentifier, self::RSS_APP_NETWORKS, true)) {
                continue;
            }

            if ($networkFilter !== null && $networkIdentifier !== $networkFilter) {
                continue;
            }

            $additionalData = $profile->getAdditionalData() ?? [];
            $existingFeedId = $additionalData['rss_feed_id'] ?? null;

            if ($existingFeedId !== null && !$force) {
                $alreadyLinked++;
                if ($output->isVerbose()) {
                    $tableRows[] = [$profile->getId(), $networkIdentifier, $profile->getIdentifier(), $existingFeedId, 'bereits vorhanden'];
                }
                continue;
            }

            $normalizedIdentifier = $this->normalizeUrl($profile->getIdentifier());
            $matchedFeed = $feedsByUrl[$normalizedIdentifier] ?? null;

            if ($matchedFeed === null) {
                $notFound++;
                if ($output->isVerbose()) {
                    $tableRows[] = [$profile->getId(), $networkIdentifier, $profile->getIdentifier(), '-', 'nicht gefunden'];
                }
                continue;
            }

            $feedId = $matchedFeed['id'];
            $found++;

            if ($existingFeedId === $feedId) {
                $alreadyLinked++;
                if ($output->isVerbose()) {
                    $tableRows[] = [$profile->getId(), $networkIdentifier, $profile->getIdentifier(), $feedId, 'bereits korrekt'];
                }
                continue;
            }

            $additionalData['rss_feed_id'] = $feedId;

            if (!$dryRun) {
                $profile->setAdditionalData($additionalData);
            }

            $updated++;
            $tableRows[] = [
                $profile->getId(),
                $networkIdentifier,
                $profile->getIdentifier(),
                $feedId,
                $dryRun ? 'würde gespeichert' : 'gespeichert',
            ];
        }

        if ($tableRows !== []) {
            $io->table(['Profil-ID', 'Netzwerk', 'Identifier', 'Feed-ID', 'Status'], $tableRows);
        }

        if (!$dryRun && $updated > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%s%d aktualisiert, %d bereits verknüpft, %d in RSS.app gefunden, %d nicht gefunden.',
            $dryRun ? '[Dry-Run] ' : '',
            $updated,
            $alreadyLinked,
            $found,
            $notFound,
        ));

        return Command::SUCCESS;
    }

    private function normalizeUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = rtrim($url, '/');
        $url = preg_replace('#^https?://(www\.)?#', '', $url);

        return $url;
    }
}
