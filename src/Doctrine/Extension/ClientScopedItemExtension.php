<?php declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Client;
use App\Entity\Item;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class ClientScopedItemExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== Item::class) {
            return;
        }

        $this->scope($queryBuilder, $queryNameGenerator);
        $this->applyVisibilityDefaults($queryBuilder, $context);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== Item::class) {
            return;
        }

        $this->scope($queryBuilder, $queryNameGenerator);
    }

    private function scope(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator): void
    {
        $client = $this->security->getUser();
        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (!$client instanceof Client) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $profileAlias = $queryNameGenerator->generateJoinAlias('profile');
        $clientAlias = $queryNameGenerator->generateJoinAlias('clientScope');

        $queryBuilder
            ->innerJoin(sprintf('%s.profile', $rootAlias), $profileAlias)
            ->innerJoin(sprintf('%s.clients', $profileAlias), $clientAlias)
            ->andWhere(sprintf('%s.id = :clientScopeId', $clientAlias))
            ->andWhere(sprintf('%s.deleted = false', $profileAlias))
            ->setParameter('clientScopeId', $client->getId());
    }

    private function applyVisibilityDefaults(QueryBuilder $queryBuilder, array $context): void
    {
        $filters = $context['filters'] ?? [];
        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (!array_key_exists('hidden', $filters)) {
            $queryBuilder->andWhere(sprintf('%s.hidden = false', $rootAlias));
        }
        if (!array_key_exists('deleted', $filters)) {
            $queryBuilder->andWhere(sprintf('%s.deleted = false', $rootAlias));
        }
    }
}
