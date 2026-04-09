<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Client;
use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/** @implements ProviderInterface<Item> */
class TimelineProvider implements ProviderInterface
{
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT = 500;
    private const DEFAULT_HOURS = 24;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $client = $this->security->getUser();

        if (!$client instanceof Client) {
            return [];
        }

        $filters = $context['filters'] ?? [];

        $limit = min(
            max(1, (int) ($filters['limit'] ?? self::DEFAULT_LIMIT)),
            self::MAX_LIMIT,
        );

        $since = isset($filters['since'])
            ? new \DateTimeImmutable($filters['since'])
            : new \DateTimeImmutable(sprintf('-%d hours', self::DEFAULT_HOURS));

        $until = isset($filters['until'])
            ? new \DateTimeImmutable($filters['until'])
            : null;

        $network = $filters['network'] ?? null;

        $qb = $this->em->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->innerJoin('i.profile', 'p')
            ->innerJoin('p.clients', 'c')
            ->where('c.id = :clientId')
            ->andWhere('p.deleted = false')
            ->andWhere('i.dateTime >= :since')
            ->setParameter('clientId', $client->getId())
            ->setParameter('since', $since)
            ->orderBy('i.dateTime', 'DESC')
            ->setMaxResults($limit);

        if ($until !== null) {
            $qb->andWhere('i.dateTime <= :until')
                ->setParameter('until', $until);
        }

        if ($network !== null) {
            $qb->innerJoin('p.network', 'n')
                ->andWhere('n.identifier = :network')
                ->setParameter('network', $network);
        }

        return $qb->getQuery()->getResult();
    }
}
