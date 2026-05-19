<?php declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Client;
use App\Entity\Group;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class ClientScopedGroupExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
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
        if ($resourceClass !== Group::class) {
            return;
        }

        $this->scope($queryBuilder);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== Group::class) {
            return;
        }

        $this->scope($queryBuilder);
    }

    private function scope(QueryBuilder $queryBuilder): void
    {
        $client = $this->security->getUser();
        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (!$client instanceof Client) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $queryBuilder
            ->andWhere(sprintf('%s.client = :clientScopeId', $rootAlias))
            ->setParameter('clientScopeId', $client->getId());
    }
}
