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
     * Groups the profile is *not* in yet.
     *
     * - When $client is given (client-token user), restrict to groups owned
     *   by that client *and* the profile must be linked to that client too.
     * - When $client is null (admin), return *all* groups the profile is not
     *   in, regardless of whether the profile is linked to the group's
     *   client. Admins are tenant-wide and we don't want to hide groups
     *   behind a profile↔client link that may not exist for imported data.
     *
     * @return list<Group>
     */
    public function findAvailableForProfile(\App\Entity\Profile $profile, ?Client $client = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.id NOT IN (
                SELECT g2.id FROM App\Entity\Group g2
                JOIN g2.profiles p2
                WHERE p2.id = :profileId
            )')
            ->setParameter('profileId', $profile->getId())
            ->orderBy('g.name', 'ASC');

        if ($client !== null) {
            // Client-token user: only their own groups, and only if the
            // profile is actually linked to them.
            if (!$profile->getClients()->contains($client)) {
                return [];
            }
            $qb->andWhere('g.client = :clientScope')->setParameter('clientScope', $client);
        }

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
