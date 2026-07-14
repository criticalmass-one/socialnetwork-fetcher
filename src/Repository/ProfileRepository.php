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

    /**
     * Find a live profile whose identifier URL ends with the given account name
     * (last path segment), e.g. "patrickpietruck" ->
     * https://www.instagram.com/patrickpietruck/. Used to attach browser-uploaded
     * media to the right profile when no item exists yet.
     */
    public function findOneByAccountName(string $account): ?Profile
    {
        $account = trim($account, "/ \t\n\r\0\x0B");
        if ($account === '') {
            return null;
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.deleted = false')
            ->andWhere('p.identifier LIKE :withSlash OR p.identifier LIKE :noSlash')
            ->setParameter('withSlash', '%/' . $account . '/')
            ->setParameter('noSlash', '%/' . $account)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByNetworkAndIdentifier(Network $network, string $identifier): ?Profile
    {
        return $this->findOneBy(['network' => $network, 'identifier' => $identifier]);
    }

    public function findNextFreeId(): int
    {
        $maxId = $this->createQueryBuilder('p')
            ->select('MAX(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $maxId) + 1;
    }

    /**
     * @return list<Profile>
     */
    public function findWithRssAppFeedId(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.network', 'n')
            ->addSelect('n')
            ->where('p.rssAppFeedId IS NOT NULL')
            ->andWhere('p.deleted = false')
            ->orderBy('p.identifier', 'ASC')
            ->getQuery()
            ->getResult();
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
