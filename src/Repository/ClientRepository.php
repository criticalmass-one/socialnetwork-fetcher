<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Client> */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function findOneByName(string $name): ?Client
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function findOneByToken(string $token): ?Client
    {
        return $this->findOneBy(['token' => $token, 'enabled' => true]);
    }
}
