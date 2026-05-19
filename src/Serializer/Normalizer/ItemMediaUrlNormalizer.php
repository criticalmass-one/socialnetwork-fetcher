<?php declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Item;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ItemMediaUrlNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'ITEM_MEDIA_URL_NORMALIZER_ALREADY_CALLED';
    private const MEDIA_PATH_PREFIX = '/media/';

    public function __construct(private readonly UrlHelper $urlHelper)
    {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): \ArrayObject|array|string|int|float|bool|null
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data) || !$object instanceof Item) {
            return $data;
        }

        $photoPaths = $object->getPhotoPaths();
        $videoPath = $object->getVideoPath();

        $data['photoUrls'] = array_values(array_map(
            fn(string $path) => $this->buildAbsoluteUrl($path),
            $photoPaths,
        ));

        $data['videoUrl'] = $videoPath !== null ? $this->buildAbsoluteUrl($videoPath) : null;

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof Item;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Item::class => false];
    }

    private function buildAbsoluteUrl(string $relativePath): string
    {
        return $this->urlHelper->getAbsoluteUrl(self::MEDIA_PATH_PREFIX . ltrim($relativePath, '/'));
    }
}
