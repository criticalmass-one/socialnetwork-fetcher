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

    public function findOneByNetworkAndIdentifier(string $network, string $identifier, ?int $cityId = null): ?SocialNetworkProfile
    {
        $criteria = ['network' => $network, 'identifier' => $identifier];

        if ($cityId !== null) {
            $criteria['cityId'] = $cityId;
        }

        return $this->findOneBy($criteria);
    }
}
