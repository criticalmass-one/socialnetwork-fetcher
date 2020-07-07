<?php declare(strict_types=1);

namespace App\Consumer;

use App\FeedItemPersister\NonDuplicatesFeedItemPersister;
use JMS\Serializer\SerializerInterface;

abstract class AbstractFeedItemConsumer
{
    protected SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }
}
