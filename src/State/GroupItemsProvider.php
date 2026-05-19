<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides paginated items for a single group: /api/groups/{groupId}/items.
 *
 * @implements ProviderInterface<Item>
 */
class GroupItemsProvider implements ProviderInterface
{
    public const DEFAULT_ITEMS_PER_PAGE = 50;
    public const MAX_ITEMS_PER_PAGE = 200;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Paginator|array
    {
        $client = $this->security->getUser();
        if (!$client instanceof Client) {
            throw new AccessDeniedHttpException();
        }

        $groupId = (int) ($uriVariables['groupId'] ?? 0);
        $group = $this->em->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getClient()?->getId() !== $client->getId()) {
            throw new NotFoundHttpException('Group not found.');
        }

        $filters = $context['filters'] ?? [];

        $page = max(1, (int) ($filters['page'] ?? 1));
        $requestedPerPage = $filters['itemsPerPage'] ?? self::DEFAULT_ITEMS_PER_PAGE;
        $itemsPerPage = min(max(1, (int) $requestedPerPage), self::MAX_ITEMS_PER_PAGE);

        $profileIds = $this->liveProfileIds($group);
        if ($profileIds === []) {
            // Group has no non-soft-deleted members — return an empty page so the
            // Hydra envelope still carries totalItems=0 for the client.
            return new Paginator(new DoctrinePaginator(
                $this->em->createQueryBuilder()
                    ->select('i')->from(Item::class, 'i')->where('1 = 0')
                    ->setMaxResults($itemsPerPage)
                    ->setFirstResult(($page - 1) * $itemsPerPage)
                    ->getQuery()
            ));
        }

        $qb = $this->em->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->innerJoin('i.profile', 'p')
            ->andWhere('p.id IN (:profileIds)')
            ->andWhere('p.deleted = false')
            ->andWhere('i.hidden = false')
            ->andWhere('i.deleted = false')
            ->setParameter('profileIds', $profileIds)
            ->orderBy('i.dateTime', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);

        $this->applyOptionalFilters($qb, $filters);

        return new Paginator(new DoctrinePaginator($qb->getQuery()));
    }

    /** @return list<int> */
    private function liveProfileIds(Group $group): array
    {
        $ids = [];
        foreach ($group->getProfiles() as $profile) {
            if (!$profile->isDeleted() && $profile->getId() !== null) {
                $ids[] = $profile->getId();
            }
        }
        return $ids;
    }

    /** @param array<string, mixed> $filters */
    private function applyOptionalFilters(\Doctrine\ORM\QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['network'])) {
            $qb->innerJoin('p.network', 'n')
                ->andWhere('n.identifier = :networkIdentifier')
                ->setParameter('networkIdentifier', (string) $filters['network']);
        }

        if (!empty($filters['since'])) {
            $qb->andWhere('i.dateTime >= :since')
                ->setParameter('since', new \DateTimeImmutable((string) $filters['since']));
        }

        if (!empty($filters['until'])) {
            $qb->andWhere('i.dateTime <= :until')
                ->setParameter('until', new \DateTimeImmutable((string) $filters['until']));
        }
    }
}
