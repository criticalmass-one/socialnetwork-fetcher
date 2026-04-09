<?php declare(strict_types=1);

namespace App\Serializer\Normalizer;

use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class FlexibleDateTimeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private readonly DateTimeNormalizer $inner;

    public function __construct(array $defaultContext = [])
    {
        $this->inner = new DateTimeNormalizer($defaultContext);
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        return $this->inner->normalize($data, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsNormalization($data, $format, $context);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): \DateTimeInterface
    {
        if (is_int($data) || (is_string($data) && ctype_digit($data))) {
            $context[DateTimeNormalizer::FORMAT_KEY] = 'U';
        }

        return $this->inner->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if ($data === null || $data === '' || $data === 0) {
            return false;
        }

        return $this->inner->supportsDenormalization($data, $type, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->inner->getSupportedTypes($format);
    }
}
