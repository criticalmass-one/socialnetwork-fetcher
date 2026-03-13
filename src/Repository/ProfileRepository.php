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

    /**
     * @param list<int> $networkIds
     * @return list<Profile>
     */
    public function findPaginated(int $page, int $limit, array $networkIds = [], string $search = '', string $status = ''): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.network', 'n')
            ->addSelect('n')
            ->orderBy('p.identifier', 'ASC');

        $this->applyFilters($qb, $networkIds, $search, $status);

        return $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $networkIds
     */
    public function countFiltered(array $networkIds = [], string $search = '', string $status = ''): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->leftJoin('p.network', 'n');

        $this->applyFilters($qb, $networkIds, $search, $status);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<int> $networkIds
     */
    private function applyFilters(\Doctrine\ORM\QueryBuilder $qb, array $networkIds, string $search, string $status): void
    {
        if ($networkIds !== []) {
            $qb->andWhere('n.id IN (:networkIds)')
                ->setParameter('networkIds', $networkIds);
        }

        if ($search !== '') {
            $qb->andWhere('p.identifier LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        match ($status) {
            'success' => $qb->andWhere('p.lastFetchSuccessDateTime IS NOT NULL'),
            'failed' => $qb->andWhere('p.lastFetchFailureDateTime IS NOT NULL'),
            'never' => $qb->andWhere('p.lastFetchSuccessDateTime IS NULL AND p.lastFetchFailureDateTime IS NULL'),
            default => null,
        };
    }
}
