<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Group;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Group> */
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    public function findOneByClientAndName(Client $client, string $name): ?Group
    {
        return $this->findOneBy(['client' => $client, 'name' => $name]);
    }

    /**
     * Groups that already contain $profile. Optionally restricted to a single
     * client (used to hide foreign-client groups from a client-token user).
     *
     * @return list<Group>
     */
    public function findByProfile(\App\Entity\Profile $profile, ?Client $client = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->innerJoin('g.profiles', 'p')
            ->andWhere('p.id = :profileId')
            ->setParameter('profileId', $profile->getId())
            ->orderBy('g.name', 'ASC');

        if ($client !== null) {
            $qb->andWhere('g.client = :clientScope')->setParameter('clientScope', $client);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Groups the profile is *not* in yet, restricted to clients the profile
     * is linked to (so we never offer a foreign-tenant group as a target).
     *
     * @return list<Group>
     */
    public function findAvailableForProfile(\App\Entity\Profile $profile, ?Client $client = null): array
    {
        $allowedClients = $client !== null
            ? [$client]
            : iterator_to_array($profile->getClients());

        if ($allowedClients === []) {
            return [];
        }

        $allowedClientIds = array_map(fn($c) => $c->getId(), $allowedClients);

        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.client IN (:clientIds)')
            ->setParameter('clientIds', $allowedClientIds)
            ->andWhere('g.id NOT IN (
                SELECT g2.id FROM App\Entity\Group g2
                JOIN g2.profiles p2
                WHERE p2.id = :profileId
            )')
            ->setParameter('profileId', $profile->getId())
            ->orderBy('g.name', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Group>
     */
    public function findPaginated(int $page, int $limit, ?Client $client = null, string $search = ''): array
    {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.client', 'c')
            ->addSelect('c')
            ->orderBy('g.name', 'ASC');

        $this->applyFilters($qb, $client, $search);

        return $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countFiltered(?Client $client = null, string $search = ''): int
    {
        $qb = $this->createQueryBuilder('g')->select('COUNT(g.id)');
        $this->applyFilters($qb, $client, $search);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyFilters(\Doctrine\ORM\QueryBuilder $qb, ?Client $client, string $search): void
    {
        if ($client !== null) {
            $qb->andWhere('g.client = :client')->setParameter('client', $client);
        }

        if ($search !== '') {
            $qb->andWhere('g.name LIKE :search')->setParameter('search', '%' . $search . '%');
        }
    }
}
