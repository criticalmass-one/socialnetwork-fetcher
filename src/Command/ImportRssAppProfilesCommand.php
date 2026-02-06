<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Network;
use App\Entity\Profile;
use App\Repository\NetworkRepository;
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
    name: 'app:import-rssapp-profiles',
    description: 'Import RSS.app feed IDs into existing profiles by matching source URLs',
)]
class ImportRssAppProfilesCommand extends Command
{
    public function __construct(
        private readonly RssAppInterface $rssApp,
        private readonly ProfileRepository $profileRepository,
        private readonly NetworkRepository $networkRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, was importiert würde')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->info('Lade Feeds von RSS.app...');
        $feeds = $this->rssApp->listFeeds();
        $io->info(sprintf('%d Feeds geladen.', count($feeds)));

        $profileMap = [];
        $maxId = 0;
        foreach ($this->profileRepository->findAll() as $profile) {
            $normalized = $this->normalizeUrl($profile->getIdentifier());
            $profileMap[$normalized] = $profile;
            $maxId = max($maxId, $profile->getId());
        }

        $networks = $this->networkRepository->findAll();

        $linked = 0;
        $created = 0;
        $alreadyLinked = 0;
        $skipped = 0;

        foreach ($feeds as $feed) {
            $sourceUrl = $feed['source_url'] ?? null;
            $feedId = $feed['id'] ?? null;

            if (!$sourceUrl || !$feedId) {
                $skipped++;
                continue;
            }

            $normalized = $this->normalizeUrl($sourceUrl);

            if (isset($profileMap[$normalized])) {
                $profile = $profileMap[$normalized];
                $additionalData = $profile->getAdditionalData() ?? [];

                if (($additionalData['rss_feed_id'] ?? null) === $feedId) {
                    $alreadyLinked++;
                    continue;
                }

                $additionalData['rss_feed_id'] = $feedId;

                if (!$dryRun) {
                    $profile->setAdditionalData($additionalData);
                }

                $linked++;
                $io->text(sprintf('Feed %s → Profil #%d (%s)', $feedId, $profile->getId(), $profile->getIdentifier()));

                continue;
            }

            $network = $this->detectNetwork($sourceUrl, $networks);

            if (!$network) {
                $io->warning(sprintf('Kein Netzwerk erkannt für %s, überspringe Feed %s', $sourceUrl, $feedId));
                $skipped++;
                continue;
            }

            $maxId++;
            $profile = new Profile();
            $profile->setId($maxId);
            $profile->setIdentifier($sourceUrl);
            $profile->setNetwork($network);
            $profile->setCreatedAt(new \DateTimeImmutable());
            $profile->setAdditionalData(['rss_feed_id' => $feedId]);

            if (!$dryRun) {
                $this->entityManager->persist($profile);
            }

            $profileMap[$normalized] = $profile;
            $created++;
            $io->text(sprintf('Neues Profil #%d (%s, %s) mit Feed %s', $maxId, $network->getName(), $sourceUrl, $feedId));
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%s%d verknüpft, %d neu erstellt, %d bereits verknüpft, %d übersprungen.',
            $dryRun ? '[Dry-Run] ' : '',
            $linked,
            $created,
            $alreadyLinked,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    /** @param Network[] $networks */
    private function detectNetwork(string $url, array $networks): ?Network
    {
        foreach ($networks as $network) {
            if ($network->isValidProfileUrl($url)) {
                return $network;
            }
        }

        return null;
    }

    private function normalizeUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = rtrim($url, '/');

        $url = preg_replace('#^https?://(www\.)?#', '', $url);

        return $url;
    }
}
