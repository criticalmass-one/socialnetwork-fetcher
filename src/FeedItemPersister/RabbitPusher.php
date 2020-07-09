<?php declare(strict_types=1);

namespace App\FeedItemPersister;

use App\FeedFetcher\FetchInfo;
use App\FeedFetcher\FetchResult;
use App\Model\SocialNetworkFeedItem;
use JMS\Serializer\SerializerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;

class RabbitPusher implements FeedItemPersisterInterface
{
    protected ProducerInterface $producer;
    protected SerializerInterface $serializer;

    public function __construct(ProducerInterface $producer, SerializerInterface $serializer)
    {
        $this->producer = $producer;
        $this->serializer = $serializer;
    }

    public function persistFeedItemList(array $feedItemList, ?FetchResult $fetchResult): FeedItemPersisterInterface
    {
        foreach ($feedItemList as $feedItem) {
            $this->persistFeedItem($feedItem, $fetchResult);
        }

        return $this;
    }

    public function persistFeedItem(SocialNetworkFeedItem $socialNetworkFeedItem, ?FetchResult $fetchResult): FeedItemPersisterInterface
    {
        $this->producer->publish($this->serializer->serialize($socialNetworkFeedItem, 'json'));

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