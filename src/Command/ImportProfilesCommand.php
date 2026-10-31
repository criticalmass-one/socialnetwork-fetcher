<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Profile;
use App\Repository\NetworkRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import-profiles',
    description: 'Import social network profiles from criticalmass.in API',
)]
class ImportProfilesCommand extends Command
{
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly NetworkRepository $networkRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly string $criticalmassHostname,
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

        $networkMap = [];
        foreach ($this->networkRepository->findAll() as $network) {
            $networkMap[$network->getIdentifier()] = $network;
        }

        // Deduplicate API data: by network+identifier (DB constraint) AND by API id (Doctrine identity map)
        $uniqueProfiles = [];
        $seenIds = [];
        $duplicatesRemoved = 0;

        $page = 0;
        $size = 1000;

        $io->info(sprintf('Lade Profile von %s ...', $this->criticalmassHostname));

        do {
            $url = sprintf('https://%s/api/socialnetwork-profiles?page=%d&size=%d', $this->criticalmassHostname, $page, $size);

            $response = $this->httpClient->request('GET', $url);
            $responseData = $response->toArray();
            $apiProfiles = $responseData['data'] ?? [];
            $totalPages = $responseData['meta']['totalPages'] ?? 1;

            $io->info(sprintf('Seite %d/%d: %d Profile geladen.', $page + 1, $totalPages, count($apiProfiles)));

            foreach ($apiProfiles as $data) {
                $networkIdentifier = $data['network'] ?? null;

                if (!$networkIdentifier || !isset($networkMap[$networkIdentifier])) {
                    $io->warning(sprintf('Netzwerk "%s" nicht gefunden, überspringe Profil #%d (%s)', $networkIdentifier, $data['id'], $data['identifier'] ?? ''));
                    continue;
                }

                $network = $networkMap[$networkIdentifier];
                $constraintKey = $network->getId() . '::' . mb_strtolower($data['identifier']);
                $apiId = $data['id'];

                if (isset($uniqueProfiles[$constraintKey]) || isset($seenIds[$apiId])) {
                    $duplicatesRemoved++;
                    continue;
                }

                $uniqueProfiles[$constraintKey] = $data;
                $seenIds[$apiId] = true;
            }

            $page++;
        } while ($page < $totalPages);

        if ($duplicatesRemoved > 0) {
            $io->note(sprintf('%d Duplikate in API-Daten entfernt.', $duplicatesRemoved));
        }

        $total = count($uniqueProfiles);
        $io->info(sprintf('%d eindeutige Profile zum Import.', $total));

        $created = 0;
        $updated = 0;
        $batchCount = 0;

        $io->progressStart($total);

        foreach ($uniqueProfiles as $data) {
            $network = $networkMap[$data['network']];

            $existing = $this->profileRepository->findOneByNetworkAndIdentifier($network, $data['identifier']);

            if ($existing) {
                $profile = $existing;
                $isNew = false;
            } else {
                $profile = new Profile();

                // The API-create path (ClientScopedProfileProcessor) assigns ids via
                // findNextFreeId(), so the local id space can diverge from the source id.
                // If the source id is already taken by an unrelated profile, fall back to a
                // free local id instead of aborting the whole import with a duplicate-PK
                // error (this command previously crashed mid-run on such a collision).
                $newId = (int) $data['id'];

                if (null !== $this->profileRepository->find($newId)) {
                    if (!$dryRun && $batchCount > 0) {
                        // Flush pending inserts so findNextFreeId() sees the current max.
                        $this->entityManager->flush();
                        $batchCount = 0;
                    }

                    $freeId = $this->profileRepository->findNextFreeId();
                    $io->warning(sprintf(
                        'Quell-ID %d bereits belegt – vergebe freie lokale ID %d für %s (%s).',
                        $newId,
                        $freeId,
                        $data['identifier'],
                        $data['network'],
                    ));
                    $newId = $freeId;
                }

                $profile->setId($newId);
                $isNew = true;
            }

            $profile->setNetwork($network);
            $profile->setIdentifier($data['identifier']);
            $profile->setAutoFetch($data['auto_fetch'] ?? true);

            $additionalData = $data['additional_data'] ?? null;
            if (is_array($additionalData) && !empty($additionalData)) {
                $profile->setAdditionalData($additionalData);
            } else {
                $profile->setAdditionalData(null);
            }

            if ($isNew) {
                $profile->setCreatedAt(new \DateTimeImmutable());

                if (!$dryRun) {
                    $this->entityManager->persist($profile);
                }

                $created++;
            } else {
                $updated++;
            }

            $batchCount++;
            $io->progressAdvance();

            if (!$dryRun && $batchCount >= self::BATCH_SIZE) {
                $this->entityManager->flush();
                $batchCount = 0;
            }
        }

        $io->progressFinish();

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%s%d erstellt, %d aktualisiert.',
            $dryRun ? '[Dry-Run] ' : '',
            $created,
            $updated,
        ));

        return Command::SUCCESS;
    }
}
