<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\SocialNetwork;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SocialNetwork> */
class SocialNetworkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialNetwork::class);
    }

    public function findOneByName(string $name): ?SocialNetwork
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function findNetworkForProfileUrl(string $url): ?SocialNetwork
    {
        $networks = $this->findAll();

        foreach ($networks as $network) {
            if ($network->isValidProfileUrl($url)) {
                return $network;
            }
        }

        return null;
    }
}
