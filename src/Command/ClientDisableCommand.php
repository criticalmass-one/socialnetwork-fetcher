<?php declare(strict_types=1);

namespace App\Command;

use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:client:disable',
    description: 'Disable an API client',
)]
class ClientDisableCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientRepository $clientRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Client name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        $client = $this->clientRepository->findOneByName($name);

        if (!$client) {
            $io->error(sprintf('Client "%s" not found.', $name));
            return Command::FAILURE;
        }

        $client->setEnabled(false);
        $this->em->flush();

        $io->success(sprintf('Client "%s" disabled.', $name));

        return Command::SUCCESS;
    }
}
