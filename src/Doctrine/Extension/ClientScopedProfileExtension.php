<?php declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Client;
use App\Entity\Profile;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class ClientScopedProfileExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
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
        if ($resourceClass !== Profile::class) {
            return;
        }

        $this->scope($queryBuilder, $queryNameGenerator);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== Profile::class) {
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

        $clientAlias = $queryNameGenerator->generateJoinAlias('clientScope');

        $queryBuilder
            ->innerJoin(sprintf('%s.clients', $rootAlias), $clientAlias)
            ->andWhere(sprintf('%s.id = :clientScopeId', $clientAlias))
            ->andWhere(sprintf('%s.deleted = false', $rootAlias))
            ->setParameter('clientScopeId', $client->getId());
    }
}
