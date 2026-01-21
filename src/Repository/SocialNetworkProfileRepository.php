<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\SocialNetworkProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SocialNetworkProfile> */
class SocialNetworkProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialNetworkProfile::class);
    }

    public function findOneByNetworkAndIdentifier(string $network, string $identifier): ?SocialNetworkProfile
    {
        return $this->findOneBy(['network' => $network, 'identifier' => $identifier]);
    }
}
