<?php declare(strict_types=1);

namespace App\Doctrine\Filter;

use ApiPlatform\Doctrine\Common\Filter\OpenApiFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\IriFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\Metadata\ParameterProviderFilterInterface;
use ApiPlatform\State\ParameterProvider\IriConverterParameterProvider;
use Doctrine\ORM\QueryBuilder;

/**
 * Relation filter that accepts IRIs (`?profile=/api/profiles/42`) as well as plain
 * numeric ids (`?profile=42`), like the legacy SearchFilter "exact" strategy did.
 *
 * API Platform's IriFilter only understands IRIs: IriConverterParameterProvider
 * turns them into entity references but leaves anything else as a raw string (or
 * drops it from a list), which IriFilter cannot handle (HTTP 500). This filter
 * reads the raw query value, turns numeric ids into entity references itself
 * and then delegates to IriFilter, so to-one and to-many relations both work.
 * An empty value is ignored (like before); any other unresolvable value matches nothing.
 */
final class IriOrIdFilter implements FilterInterface, OpenApiParameterFilterInterface, ParameterProviderFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;
    use OpenApiFilterTrait;

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        /** @var Parameter $parameter */
        $parameter = $context['parameter'];
        $raw = $context['filters'][$parameter->getKey()] ?? $parameter->getValue();
        $rawValues = \is_array($raw) ? array_values($raw) : [$raw];

        if ([] === $rawValues || [''] === $rawValues) {
            return;
        }

        $converted = $parameter->getValue();
        $convertedValues = \is_array($converted) ? $converted : [$converted];

        if (\count($convertedValues) === \count($rawValues) && array_all($convertedValues, static fn (mixed $v): bool => \is_object($v))) {
            new IriFilter()->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);

            return;
        }

        $targetClass = $this->resolveTargetClass($queryBuilder, $resourceClass, (string) $parameter->getProperty());

        if (null === $targetClass || !array_all($rawValues, static fn (mixed $v): bool => \is_string($v) && ctype_digit($v))) {
            $queryBuilder->{$context['whereClause'] ?? 'andWhere'}('1 = 0');

            return;
        }

        $em = $queryBuilder->getEntityManager();
        $references = array_map(static fn (string $id): object => $em->getReference($targetClass, (int) $id), $rawValues);

        $parameter = clone $parameter;
        $parameter->setValue(\is_array($raw) ? $references : $references[0]);
        $context['parameter'] = $parameter;

        new IriFilter()->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
    }

    public static function getParameterProvider(): string
    {
        return IriConverterParameterProvider::class;
    }

    /**
     * Walks a (possibly nested, dot-separated) association path and returns the entity class at its end.
     *
     * @param class-string $resourceClass
     *
     * @return class-string|null
     */
    private function resolveTargetClass(QueryBuilder $queryBuilder, string $resourceClass, string $property): ?string
    {
        $class = $resourceClass;

        foreach (explode('.', $property) as $association) {
            $metadata = $queryBuilder->getEntityManager()->getClassMetadata($class);

            if (!$metadata->hasAssociation($association)) {
                return null;
            }

            $class = $metadata->getAssociationTargetClass($association);
        }

        return $class;
    }
}
