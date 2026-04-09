<?php declare(strict_types=1);

namespace App\Command;

use App\Repository\NetworkRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:list-networks',
    description: 'List all networks',
)]
class ListNetworksCommand extends Command
{
    public function __construct(
        private readonly NetworkRepository $networkRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $networks = $this->networkRepository->findAll();

        $table = new Table($output);
        $table->setHeaders(['ID', 'Identifier', 'Name', 'Icon']);

        foreach ($networks as $network) {
            $table->addRow([
                $network->getId(),
                $network->getIdentifier(),
                $network->getName(),
                $network->getIcon(),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
