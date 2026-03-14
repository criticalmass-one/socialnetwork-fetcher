<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Item;
use App\Entity\Profile;
use App\Repository\ItemRepository;
use App\Repository\ProfileRepository;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import-items',
    description: 'Import social network feed items from criticalmass.in API',
)]
class ImportItemsCommand extends Command
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProfileRepository $profileRepository,
        private readonly ItemRepository $itemRepository,
        private readonly string $criticalmassHostname,
        #[Autowire(service: 'doctrine.debug_data_holder')]
        private readonly ?DebugDataHolder $debugDataHolder = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, was importiert würde')
            ->addOption('network', null, InputOption::VALUE_REQUIRED, 'Nur ein bestimmtes Netzwerk importieren')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $networkFilter = $input->getOption('network');

        $baseUrl = sprintf('https://%s', $this->criticalmassHostname);

        // 1. Load local profiles — extract data before clearing EntityManager
        $profiles = $this->profileRepository->findAll();
        $io->info(sprintf('%d lokale Profile geladen.', count($profiles)));

        $profileInfos = [];
        foreach ($profiles as $profile) {
            $networkIdentifier = $profile->getNetwork()->getIdentifier();
            if ($networkFilter && $networkIdentifier !== $networkFilter) {
                continue;
            }
            $profileInfos[] = ['id' => $profile->getId(), 'network' => $networkIdentifier];
        }

        $this->entityManager->clear();

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $batchCount = 0;
        $profilesProcessed = 0;

        $progressBar = $io->createProgressBar(count($profileInfos));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $progressBar->setMessage('Starte...');
        $progressBar->start();

        foreach ($profileInfos as $profileInfo) {
            $profileId = $profileInfo['id'];
            $progressBar->setMessage(sprintf('#%d %s', $profileId, $profileInfo['network']));
            $profilesProcessed++;

            try {
                $apiItems = $this->loadFeedItemsForProfile($baseUrl, $profileId);
            } catch (\Throwable $e) {
                $io->warning(sprintf('Profil #%d: Fehler beim Laden: %s', $profileId, $e->getMessage()));
                $progressBar->advance();
                continue;
            }

            if (empty($apiItems)) {
                $progressBar->advance();
                continue;
            }

            $profileRef = $this->entityManager->getReference(Profile::class, $profileId);

            foreach ($apiItems as $data) {
                $text = $data['text'] ?? null;
                if ($text === null || $text === '') {
                    $totalSkipped++;
                    continue;
                }

                $uniqueId = $data['unique_identifier'] ?? null;
                if (!$uniqueId) {
                    $totalSkipped++;
                    continue;
                }

                $existing = $this->itemRepository->findOneByProfileAndUniqueIdentifier($profileRef, $uniqueId);

                if ($existing) {
                    $this->updateItem($existing, $data);
                    $totalUpdated++;
                } else {
                    $item = $this->createItem($profileRef, $data);
                    if (!$dryRun) {
                        $this->entityManager->persist($item);
                    }
                    $totalCreated++;
                }

                $batchCount++;
                if (!$dryRun && $batchCount >= self::BATCH_SIZE) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                    $this->debugDataHolder?->reset();
                    $batchCount = 0;
                }
            }

            $progressBar->advance();
        }

        $progressBar->setMessage('Fertig.');
        $progressBar->finish();
        $io->newLine(2);

        if (!$dryRun) {
            $this->entityManager->flush();
            $this->debugDataHolder?->reset();
        }

        $io->success(sprintf(
            '%s%d Profile verarbeitet: %d Items erstellt, %d aktualisiert, %d übersprungen.',
            $dryRun ? '[Dry-Run] ' : '',
            $profilesProcessed,
            $totalCreated,
            $totalUpdated,
            $totalSkipped,
        ));

        return Command::SUCCESS;
    }

    private function loadFeedItemsForProfile(string $baseUrl, int $profileId): array
    {
        $allItems = [];
        $page = 0;
        $size = 1000;

        do {
            $url = sprintf('%s/api/socialnetwork-feeditems?profileId=%d&page=%d&size=%d', $baseUrl, $profileId, $page, $size);
            $responseData = $this->httpClient->request('GET', $url, ['timeout' => 30, 'max_duration' => 120])->toArray();
            $items = $responseData['data'] ?? [];
            $totalPages = $responseData['meta']['totalPages'] ?? 1;

            $allItems = array_merge($allItems, $items);
            $page++;
        } while ($page < $totalPages);

        return $allItems;
    }

    private function createItem(Profile $profile, array $data): Item
    {
        $item = new Item();
        $item->setProfile($profile);
        $item->setUniqueIdentifier($data['unique_identifier']);
        $item->setPermalink($data['permalink'] ?? null);
        $item->setTitle($data['title'] ?? null);
        $item->setText($data['text']);
        $item->setDateTime((new \DateTimeImmutable())->setTimestamp((int) $data['date_time']));
        $item->setHidden($data['hidden'] ?? false);
        $item->setDeleted($data['deleted'] ?? false);
        $item->setRaw($data['raw'] ?? null);

        if (!empty($data['created_at'])) {
            $item->setCreatedAt((new \DateTimeImmutable())->setTimestamp((int) $data['created_at']));
        }

        return $item;
    }

    private function updateItem(Item $item, array $data): void
    {
        $item->setPermalink($data['permalink'] ?? null);
        $item->setTitle($data['title'] ?? null);
        $item->setText($data['text']);
        $item->setHidden($data['hidden'] ?? false);
        $item->setDeleted($data['deleted'] ?? false);
        $item->setRaw($data['raw'] ?? null);
    }
}
