<?php declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Group;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Adds an absolute `publicUrl` to serialized groups, built from the public slug
 * against the app router. Null when the public page is disabled or has no slug.
 */
class GroupPublicUrlNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'GROUP_PUBLIC_URL_NORMALIZER_ALREADY_CALLED';

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): \ArrayObject|array|string|int|float|bool|null
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data) || !$object instanceof Group) {
            return $data;
        }

        $data['publicUrl'] = ($object->isPublicPageEnabled() && $object->getPublicSlug() !== null)
            ? $this->urlGenerator->generate('app_public_group', ['slug' => $object->getPublicSlug()], UrlGeneratorInterface::ABSOLUTE_URL)
            : null;

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof Group;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Group::class => false];
    }
}
