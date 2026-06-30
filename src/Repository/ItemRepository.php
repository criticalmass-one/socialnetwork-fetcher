<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Item;
use App\Entity\Profile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Item> */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    public function findOneByProfileAndUniqueIdentifier(Profile|int $profile, string $uniqueIdentifier): ?Item
    {
        return $this->findOneBy([
            'profile' => $profile,
            'uniqueIdentifier' => $uniqueIdentifier,
        ]);
    }

    /**
     * Items of a profile that have no media yet (mediaStatus null) or whose last
     * download attempt failed. Used to (re)queue media downloads.
     *
     * @return list<Item>
     */
    public function findNewOrFailedForProfile(Profile $profile): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.profile = :profile')
            ->andWhere('i.mediaStatus IS NULL OR i.mediaStatus = :failed')
            ->setParameter('profile', $profile)
            ->setParameter('failed', 'failed')
            ->getQuery()
            ->getResult();
    }

    /**
     * Paginated items across all profiles in a group, excluding hidden /
     * soft-deleted items and items belonging to soft-deleted profiles.
     *
     * @return list<Item>
     */
    public function findPaginatedByGroup(\App\Entity\Group $group, int $page, int $limit, ?int $networkId = null): array
    {
        $qb = $this->groupQueryBuilder($group)
            ->orderBy('i.dateTime', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($networkId !== null) {
            $qb->andWhere('n.id = :networkId')->setParameter('networkId', $networkId);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByGroup(\App\Entity\Group $group, ?int $networkId = null): int
    {
        $qb = $this->groupQueryBuilder($group)->select('COUNT(i.id)');

        if ($networkId !== null) {
            $qb->andWhere('n.id = :networkId')->setParameter('networkId', $networkId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function groupQueryBuilder(\App\Entity\Group $group): QueryBuilder
    {
        $profileIds = [];
        foreach ($group->getProfiles() as $profile) {
            if (!$profile->isDeleted()) {
                $profileIds[] = $profile->getId();
            }
        }

        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.profile', 'p')
            ->addSelect('p')
            ->leftJoin('p.network', 'n')
            ->addSelect('n')
            ->andWhere('i.hidden = false')
            ->andWhere('i.deleted = false');

        if ($profileIds === []) {
            // Group has no live members → no items. Force-empty result.
            $qb->andWhere('1 = 0');
        } else {
            $qb->andWhere('p.id IN (:profileIds)')->setParameter('profileIds', $profileIds);
        }

        return $qb;
    }

    /**
     * @param list<int> $networkIds
     * @return list<Item>
     */
    public function findPaginated(int $page, int $limit, ?int $profileId = null, array $networkIds = [], string $search = '', string $status = ''): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.profile', 'p')
            ->addSelect('p')
            ->leftJoin('p.network', 'n')
            ->addSelect('n')
            ->orderBy('i.dateTime', 'DESC');

        $this->applyFilters($qb, $profileId, $networkIds, $search, $status);

        return $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $networkIds
     */
    public function countFiltered(?int $profileId = null, array $networkIds = [], string $search = '', string $status = ''): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->leftJoin('i.profile', 'p')
            ->leftJoin('p.network', 'n');

        $this->applyFilters($qb, $profileId, $networkIds, $search, $status);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<int> $profileIds
     * @return array<int, \DateTimeImmutable> map of profile id => last item dateTime
     */
    public function findLastItemDateByProfileIds(array $profileIds): array
    {
        if ($profileIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.profile) AS profileId, MAX(i.dateTime) AS lastDate')
            ->where('i.profile IN (:profileIds)')
            ->setParameter('profileIds', $profileIds)
            ->groupBy('i.profile')
            ->getQuery()
            ->getArrayResult();

        $dates = [];
        foreach ($rows as $row) {
            if ($row['lastDate'] === null) {
                continue;
            }
            $dates[(int) $row['profileId']] = new \DateTimeImmutable((string) $row['lastDate']);
        }

        return $dates;
    }

    /**
     * @param list<int> $profileIds
     * @return array<int, int> map of profile id => item count (missing keys mean zero)
     */
    public function countByProfileIds(array $profileIds): array
    {
        if ($profileIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.profile) AS profileId, COUNT(i.id) AS itemCount')
            ->where('i.profile IN (:profileIds)')
            ->setParameter('profileIds', $profileIds)
            ->groupBy('i.profile')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['profileId']] = (int) $row['itemCount'];
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function countByNetworkSince(\App\Entity\Network $network, array $intervals): array
    {
        $counts = [];

        foreach ($intervals as $key => $since) {
            $qb = $this->createQueryBuilder('i')
                ->select('COUNT(i.id)')
                ->join('i.profile', 'p')
                ->where('p.network = :network')
                ->andWhere('i.dateTime >= :since')
                ->setParameter('network', $network)
                ->setParameter('since', $since);

            $counts[$key] = (int) $qb->getQuery()->getSingleScalarResult();
        }

        return $counts;
    }

    /**
     * @param list<int> $networkIds
     */
    private function applyFilters(QueryBuilder $qb, ?int $profileId, array $networkIds, string $search, string $status): void
    {
        if ($profileId !== null) {
            $qb->andWhere('p.id = :profileId')
                ->setParameter('profileId', $profileId);
        }

        if ($networkIds !== []) {
            $qb->andWhere('n.id IN (:networkIds)')
                ->setParameter('networkIds', $networkIds);
        }

        if ($search !== '') {
            $qb->andWhere('i.text LIKE :search OR i.uniqueIdentifier LIKE :search OR i.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        match ($status) {
            'active' => $qb->andWhere('i.hidden = false AND i.deleted = false'),
            'hidden' => $qb->andWhere('i.hidden = true'),
            'deleted' => $qb->andWhere('i.deleted = true'),
            default => null,
        };
    }
}
