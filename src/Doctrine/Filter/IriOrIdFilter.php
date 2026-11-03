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
use ApiPlatform\State\ParameterNotFound;
use Doctrine\ORM\QueryBuilder;

/**
 * Relation filter that accepts IRIs (`?profile=/api/profiles/42`) as well as plain
 * numeric ids (`?profile=42`), like the legacy SearchFilter "exact" strategy did.
 *
 * API Platform's IriFilter only understands IRIs. IriOrIdParameterProvider turns
 * IRIs into entity references and leaves plain ids as strings; this filter turns
 * those ids into references itself and then delegates to IriFilter, so to-one and
 * to-many relations as well as lists (`?profile[]=1&profile[]=2`) work.
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
        $value = $parameter->getValue();
        $values = \is_array($value) ? array_values($value) : [$value];

        if ([] === $values || [''] === $values || $value instanceof ParameterNotFound) {
            return;
        }

        $targetClass = $this->resolveTargetClass($queryBuilder, $resourceClass, (string) $parameter->getProperty());

        if (null === $targetClass || !array_all($values, static fn (mixed $v): bool => \is_object($v) || (\is_string($v) && ctype_digit($v)))) {
            $queryBuilder->{$context['whereClause'] ?? 'andWhere'}('1 = 0');

            return;
        }

        $em = $queryBuilder->getEntityManager();
        $references = array_map(static fn (mixed $v): object => \is_object($v) ? $v : $em->getReference($targetClass, (int) $v), $values);

        $parameter = clone $parameter;
        $parameter->setValue(\is_array($value) ? $references : $references[0]);
        $context['parameter'] = $parameter;

        new IriFilter()->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
    }

    public static function getParameterProvider(): string
    {
        return IriOrIdParameterProvider::class;
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
