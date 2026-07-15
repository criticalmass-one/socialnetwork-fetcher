<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Group;
use App\Entity\PublicPageEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicPageEvent>
 */
class PublicPageEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicPageEvent::class);
    }

    public function countByGroupAndType(Group $group, string $type, ?\DateTimeImmutable $since = null): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.group = :group')
            ->andWhere('e.type = :type')
            ->setParameter('group', $group)
            ->setParameter('type', $type);

        if ($since !== null) {
            $qb->andWhere('e.occurredAt >= :since')->setParameter('since', $since);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<array{url: string, count: int}>
     */
    public function topClickedUrls(Group $group, int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.url AS url', 'COUNT(e.id) AS cnt')
            ->andWhere('e.group = :group')
            ->andWhere('e.type = :type')
            ->andWhere('e.url IS NOT NULL')
            ->setParameter('group', $group)
            ->setParameter('type', PublicPageEvent::TYPE_CLICK)
            ->groupBy('e.url')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => ['url' => (string) $row['url'], 'count' => (int) $row['cnt']],
            $rows,
        );
    }
}
