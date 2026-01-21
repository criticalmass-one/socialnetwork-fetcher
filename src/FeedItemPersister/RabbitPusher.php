<?php declare(strict_types=1);

namespace App\FeedItemPersister;

use App\FeedFetcher\FetchResult;
use App\Model\Item;
use App\Serializer\SerializerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;

class RabbitPusher implements FeedItemPersisterInterface
{
    public function __construct(
        private readonly ProducerInterface $producer,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function persistFeedItemList(array $feedItemList, ?FetchResult $fetchResult): FeedItemPersisterInterface
    {
        foreach ($feedItemList as $feedItem) {
            $this->persistFeedItem($feedItem, $fetchResult);
        }

        return $this;
    }

    public function persistFeedItem(Item $item, ?FetchResult $fetchResult): FeedItemPersisterInterface
    {
        $this->producer->publish($this->serializer->serialize($item, 'json'));

        if ($fetchResult) {
            $fetchResult->incCounterRabbit();
        }

        return $this;
    }

    public function flush(): FeedItemPersisterInterface
    {
        return $this;
    }
}