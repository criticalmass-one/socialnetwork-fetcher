<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Network;
use App\Entity\Profile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Profile> */
class ProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Profile::class);
    }

    public function findOneByNetworkAndIdentifier(Network $network, string $identifier): ?Profile
    {
        return $this->findOneBy(['network' => $network, 'identifier' => $identifier]);
    }
}
