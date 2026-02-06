<?php declare(strict_types=1);

namespace App\Serializer;

use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use App\Serializer\Normalizer\FlexibleDateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

class Serializer implements SerializerInterface
{
    private SymfonySerializerInterface $serializer;

    public function __construct()
    {
        $this->createSerializer();
    }

    public function serialize(mixed $data, string $format = self::FORMAT, array $context = []): string
    {
        $context[AbstractObjectNormalizer::SKIP_NULL_VALUES] = true;

        return $this->serializer->serialize($data, $format, $context);
    }

    public function deserialize(mixed $data, string $type, string $format = self::FORMAT, array $context = []): mixed
    {
        $context[AbstractObjectNormalizer::SKIP_NULL_VALUES] = true;

        return $this->serializer->deserialize($data, $type, $format, $context);
    }

    private function createSerializer(): void
    {
        $dateTimeNormalizerOptions = [
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:sP',
        ];

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $normalizers = [
            new FlexibleDateTimeNormalizer($dateTimeNormalizerOptions),
            new ObjectNormalizer(
                classMetadataFactory: $classMetadataFactory,
                nameConverter: new CamelCaseToSnakeCaseNameConverter(),
                propertyTypeExtractor: new ReflectionExtractor(),
            ),
            new GetSetMethodNormalizer(
                classMetadataFactory: $classMetadataFactory,
                nameConverter: new CamelCaseToSnakeCaseNameConverter(),
            ),
            new ArrayDenormalizer(),
        ];

        $encoders = [
            new JsonEncoder()
        ];

        $this->serializer = new SymfonySerializer($normalizers, $encoders);
    }
}
