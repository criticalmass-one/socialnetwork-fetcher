<?php declare(strict_types=1);

namespace App\Command;

use App\Repository\ClientRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:client:list',
    description: 'List all API clients',
)]
class ClientListCommand extends Command
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $clients = $this->clientRepository->findAll();

        if ($clients === []) {
            $io->info('No clients found.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [
                $client->getId(),
                $client->getName(),
                $client->isEnabled() ? 'yes' : 'no',
                $client->getProfiles()->count(),
                $client->getCreatedAt()->format('Y-m-d H:i'),
            ];
        }

        $io->table(['ID', 'Name', 'Enabled', 'Profiles', 'Created'], $rows);

        return Command::SUCCESS;
    }
}
