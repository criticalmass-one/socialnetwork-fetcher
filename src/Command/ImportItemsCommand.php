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
            ->addOption('city', null, InputOption::VALUE_REQUIRED, 'Nur eine bestimmte Stadt importieren (Slug)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $cityFilter = $input->getOption('city');

        $baseUrl = sprintf('https://%s', $this->criticalmassHostname);

        // 1. Load all cities → id-to-slug map
        $io->info('Lade Städte...');
        $cityMap = $this->loadCityMap($baseUrl);
        $io->info(sprintf('%d Städte geladen.', count($cityMap)));

        // 2. Load all API profiles → group by city_id
        $io->info('Lade Profile von der API...');
        $apiProfiles = $this->httpClient->request('GET', $baseUrl . '/api/socialnetwork-profiles?size=10000')->toArray();
        $io->info(sprintf('%d Profile geladen.', count($apiProfiles)));

        $profilesByCityAndNetwork = [];
        foreach ($apiProfiles as $ap) {
            $cityId = $ap['city_id'] ?? null;
            $network = $ap['network'] ?? null;
            if ($cityId && $network) {
                $profilesByCityAndNetwork[$cityId][$network][] = $ap;
            }
        }

        // 3. Collect local profile IDs
        $localProfileIds = [];
        foreach ($this->profileRepository->findAll() as $profile) {
            $localProfileIds[$profile->getId()] = true;
        }
        $this->entityManager->clear();

        // 4. Iterate cities and import items
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $batchCount = 0;
        $citiesProcessed = 0;

        foreach ($profilesByCityAndNetwork as $cityId => $networkGroups) {
            $slug = $cityMap[$cityId] ?? null;
            if (!$slug) {
                continue;
            }

            if ($cityFilter && $slug !== $cityFilter) {
                continue;
            }

            $citiesProcessed++;

            foreach ($networkGroups as $networkIdentifier => $cityProfiles) {
                $url = sprintf('%s/api/%s/socialnetwork-feeditems?networkIdentifier=%s', $baseUrl, $slug, $networkIdentifier);

                try {
                    $apiItems = $this->httpClient->request('GET', $url)->toArray();
                } catch (\Throwable $e) {
                    $io->warning(sprintf('%s/%s: Fehler beim Laden: %s', $slug, $networkIdentifier, $e->getMessage()));
                    continue;
                }

                if (empty($apiItems)) {
                    continue;
                }

                // Resolve which local profile ID to assign items to
                $resolvedProfileId = $this->resolveProfileId($cityProfiles, $localProfileIds);

                if (!$resolvedProfileId) {
                    $totalSkipped += count($apiItems);
                    continue;
                }

                foreach ($apiItems as $data) {
                    $profileId = $resolvedProfileId;

                    // If multiple profiles: try to match by URL
                    if (count($cityProfiles) > 1) {
                        $matchedId = $this->matchItemToProfileId($data, $cityProfiles, $localProfileIds);
                        if ($matchedId) {
                            $profileId = $matchedId;
                        }
                    }

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

                    // Use getReference() for a lightweight proxy — survives clear()
                    $profileRef = $this->entityManager->getReference(Profile::class, $profileId);

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
            }

            if ($citiesProcessed % 25 === 0) {
                $io->info(sprintf(
                    '%d Städte verarbeitet — %d erstellt, %d aktualisiert, %d übersprungen',
                    $citiesProcessed,
                    $totalCreated,
                    $totalUpdated,
                    $totalSkipped,
                ));
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
            $this->debugDataHolder?->reset();
        }

        $io->success(sprintf(
            '%s%d Städte verarbeitet: %d Items erstellt, %d aktualisiert, %d übersprungen.',
            $dryRun ? '[Dry-Run] ' : '',
            $citiesProcessed,
            $totalCreated,
            $totalUpdated,
            $totalSkipped,
        ));

        return Command::SUCCESS;
    }

    private function loadCityMap(string $baseUrl): array
    {
        $cityMap = [];

        foreach (['asc', 'desc'] as $direction) {
            $url = sprintf('%s/api/city?extended=true&size=500&orderBy=id&orderDirection=%s', $baseUrl, $direction);
            $cities = $this->httpClient->request('GET', $url)->toArray();

            foreach ($cities as $city) {
                $cityMap[$city['id']] = $city['main_slug']['slug'];
            }
        }

        return $cityMap;
    }

    private function resolveProfileId(array $cityProfiles, array $localProfileIds): ?int
    {
        foreach ($cityProfiles as $ap) {
            if (isset($localProfileIds[$ap['id']])) {
                return $ap['id'];
            }
        }

        return null;
    }

    private function matchItemToProfileId(array $itemData, array $cityProfiles, array $localProfileIds): ?int
    {
        $itemUrl = $itemData['unique_identifier'] ?? '';
        $itemUrlLower = strtolower($itemUrl);

        foreach ($cityProfiles as $ap) {
            $profileId = $ap['id'];
            if (!isset($localProfileIds[$profileId])) {
                continue;
            }

            $profileIdentifier = strtolower($ap['identifier']);

            $profileHost = (string) parse_url($profileIdentifier, PHP_URL_HOST);
            $profilePath = trim((string) parse_url($profileIdentifier, PHP_URL_PATH), '/');

            $itemHost = (string) parse_url($itemUrlLower, PHP_URL_HOST);

            if ($profileHost && $itemHost && str_contains($itemHost, str_replace('www.', '', $profileHost))) {
                if ($profilePath && str_contains($itemUrlLower, '/' . $profilePath . '/')) {
                    return $profileId;
                }
                if ($profilePath && str_starts_with(ltrim((string) parse_url($itemUrlLower, PHP_URL_PATH), '/'), $profilePath . '/')) {
                    return $profileId;
                }
            }
        }

        // Fallback: first available local profile
        return $this->resolveProfileId($cityProfiles, $localProfileIds);
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
