<?php declare(strict_types=1);

namespace App\Consumer;

use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractFeedItemConsumer
{
    public function __construct(
        protected readonly SerializerInterface $serializer
    ) {

    }
}
