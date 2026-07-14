<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Group;
use App\Entity\PushSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    /**
     * @return list<PushSubscription>
     */
    public function findByGroup(Group $group): array
    {
        return $this->findBy(['group' => $group]);
    }

    public function findOneByGroupAndEndpoint(Group $group, string $endpoint): ?PushSubscription
    {
        return $this->findOneBy(['group' => $group, 'endpoint' => $endpoint]);
    }

    public function countByGroup(Group $group): int
    {
        return $this->count(['group' => $group]);
    }
}
