<?php declare(strict_types=1);

namespace App\Serializer;

use \Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

interface SerializerInterface extends SymfonySerializerInterface
{
    final public const string FORMAT = 'json';

    public function serialize(mixed $data, string $format = self::FORMAT, array $context = []): string;
    public function deserialize(mixed $data, string $type, string $format = self::FORMAT, array $context = []): mixed;
}
