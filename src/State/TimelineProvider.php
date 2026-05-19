<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Client;
use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Bundle\SecurityBundle\Security;

/** @implements ProviderInterface<Item> */
class TimelineProvider implements ProviderInterface
{
    public const DEFAULT_ITEMS_PER_PAGE = 50;
    public const MAX_ITEMS_PER_PAGE = 200;
    private const DEFAULT_HOURS = 24;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Paginator|array
    {
        $client = $this->security->getUser();

        if (!$client instanceof Client) {
            return [];
        }

        $filters = $context['filters'] ?? [];

        $page = max(1, (int) ($filters['page'] ?? 1));

        $requestedPerPage = $filters['itemsPerPage'] ?? $filters['limit'] ?? self::DEFAULT_ITEMS_PER_PAGE;
        $itemsPerPage = min(
            max(1, (int) $requestedPerPage),
            self::MAX_ITEMS_PER_PAGE,
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
            ->andWhere('i.hidden = false')
            ->andWhere('i.deleted = false')
            ->andWhere('i.dateTime >= :since')
            ->setParameter('clientId', $client->getId())
            ->setParameter('since', $since)
            ->orderBy('i.dateTime', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);

        if ($until !== null) {
            $qb->andWhere('i.dateTime <= :until')
                ->setParameter('until', $until);
        }

        if ($network !== null) {
            $qb->innerJoin('p.network', 'n')
                ->andWhere('n.identifier = :network')
                ->setParameter('network', $network);
        }

        return new Paginator(new DoctrinePaginator($qb->getQuery()));
    }
}
