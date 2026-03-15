<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:client:create',
    description: 'Create a new API client',
)]
class ClientCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientRepository $clientRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Unique client name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        if ($this->clientRepository->findOneByName($name)) {
            $io->error(sprintf('Client "%s" already exists.', $name));
            return Command::FAILURE;
        }

        $client = new Client();
        $client->setName($name);
        $client->setToken(Client::generateToken());

        $this->em->persist($client);
        $this->em->flush();

        $io->success(sprintf('Client "%s" created.', $name));
        $io->writeln(sprintf('Token: <info>%s</info>', $client->getToken()));

        return Command::SUCCESS;
    }
}
