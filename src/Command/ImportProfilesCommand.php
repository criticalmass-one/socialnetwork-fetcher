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

        $url = sprintf('https://%s/api/socialnetwork-profiles?size=10000', $this->criticalmassHostname);

        $io->info(sprintf('Lade Profile von %s ...', $url));

        $response = $this->httpClient->request('GET', $url);
        $apiProfiles = $response->toArray();

        $io->info(sprintf('%d Profile von der API geladen.', count($apiProfiles)));

        $networkMap = [];
        foreach ($this->networkRepository->findAll() as $network) {
            $networkMap[$network->getIdentifier()] = $network;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $seen = [];

        foreach ($apiProfiles as $data) {
            $networkIdentifier = $data['network'] ?? null;

            if (!$networkIdentifier || !isset($networkMap[$networkIdentifier])) {
                $io->warning(sprintf('Netzwerk "%s" nicht gefunden, überspringe Profil #%d (%s)', $networkIdentifier, $data['id'], $data['identifier'] ?? ''));
                $skipped++;
                continue;
            }

            $network = $networkMap[$networkIdentifier];
            $uniqueKey = $network->getId() . '::' . $data['identifier'];

            if (isset($seen[$uniqueKey])) {
                $skipped++;
                continue;
            }
            $seen[$uniqueKey] = true;

            $existing = $this->profileRepository->find($data['id'])
                ?? $this->profileRepository->findOneByNetworkAndIdentifier($network, $data['identifier']);

            if ($existing) {
                $profile = $existing;
                $isNew = false;
            } else {
                $profile = new Profile();
                $profile->setId($data['id']);
                $isNew = true;
            }

            $profile->setNetwork($network);
            $profile->setIdentifier($data['identifier']);
            $profile->setAutoPublish($data['auto_publish'] ?? true);
            $profile->setAutoFetch($data['auto_fetch'] ?? true);

            $additionalData = $data['additional_data'] ?? null;
            if (is_array($additionalData) && !empty($additionalData)) {
                $profile->setAdditionalData($additionalData);
            } else {
                $profile->setAdditionalData(null);
            }

            if (!empty($data['last_fetch_success_date_time'])) {
                $profile->setLastFetchSuccessDateTime(
                    (new \DateTimeImmutable())->setTimestamp((int) $data['last_fetch_success_date_time'])
                );
            }

            if (!empty($data['last_fetch_failure_date_time'])) {
                $profile->setLastFetchFailureDateTime(
                    (new \DateTimeImmutable())->setTimestamp((int) $data['last_fetch_failure_date_time'])
                );
            }

            if (!empty($data['last_fetch_failure_error'])) {
                $profile->setLastFetchFailureError($data['last_fetch_failure_error']);
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
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%s%d erstellt, %d aktualisiert, %d übersprungen.',
            $dryRun ? '[Dry-Run] ' : '',
            $created,
            $updated,
            $skipped,
        ));

        return Command::SUCCESS;
    }
}
