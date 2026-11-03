<?php declare(strict_types=1);

namespace App\Doctrine\Filter;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ParameterProviderInterface;

/**
 * Parameter provider for IriOrIdFilter: turns IRIs into entity references and
 * leaves everything else (plain ids, garbage) untouched for the filter to judge.
 *
 * API Platform's IriConverterParameterProvider stops at the first value of a list
 * that is no IRI and drops the rest, so `?profile[]=42` arrived as an empty list —
 * which the filter took for "no filter" and returned every item of the client.
 */
final readonly class IriOrIdParameterProvider implements ParameterProviderInterface
{
    public function __construct(
        private IriConverterInterface $iriConverter,
    ) {
    }

    public function provide(Parameter $parameter, array $parameters = [], array $context = []): ?Operation
    {
        $operation = $context['operation'] ?? null;
        $value = $parameter->getValue();

        if ($value instanceof ParameterNotFound || null === $value || '' === $value || [] === $value) {
            return $operation;
        }

        $parameter->setValue(\is_array($value) ? array_map($this->convert(...), array_values($value)) : $this->convert($value));

        return $operation;
    }

    private function convert(mixed $value): mixed
    {
        if (!\is_string($value) || '' === $value || ctype_digit($value)) {
            return $value;
        }

        try {
            return $this->iriConverter->getResourceFromIri($value, ['fetch_data' => false]);
        } catch (InvalidArgumentException|ItemNotFoundException) {
            return $value;
        }
    }
}
