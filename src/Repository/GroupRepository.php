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
