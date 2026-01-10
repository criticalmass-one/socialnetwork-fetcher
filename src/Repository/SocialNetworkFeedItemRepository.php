<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\SocialNetworkFeedItem;
use App\Entity\SocialNetworkProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SocialNetworkFeedItem> */
class SocialNetworkFeedItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialNetworkFeedItem::class);
    }

    public function findOneByProfileAndUniqueIdentifier(SocialNetworkProfile $profile, string $uniqueIdentifier): ?SocialNetworkFeedItem
    {
        return $this->findOneBy([
            'socialNetworkProfile' => $profile,
            'uniqueIdentifier' => $uniqueIdentifier,
        ]);
    }
}

