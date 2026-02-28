<?php declare(strict_types=1);

namespace App\Serializer;

use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class NullableDateTimeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private DateTimeNormalizer $inner;

    public function __construct(array $defaultContext = [])
    {
        $this->inner = new DateTimeNormalizer($defaultContext);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->inner->getSupportedTypes($format);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsNormalization($data, $format, $context);
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): int|float|string
    {
        return $this->inner->normalize($data, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsDenormalization($data, $type, $format, $context);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (null === $data || (is_string($data) && '' === trim($data))) {
            return null;
        }

        if (is_int($data) || is_float($data)) {
            $context[DateTimeNormalizer::FORMAT_KEY] = 'U';

            return $this->inner->denormalize($data, $type, $format, $context);
        }

        return $this->inner->denormalize($data, $type, $format, $context);
    }
}
