<?php declare(strict_types=1);

namespace App\Consumer;

use App\Model\SocialNetworkFeedItem;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

class FeedItemConsumer extends AbstractFeedItemConsumer implements ConsumerInterface
{
    public function execute(AMQPMessage $message): int
    {
        /** @var SocialNetworkFeedItem $socialNetworkFeedItem */
        $socialNetworkFeedItem = $this->serializer->deserialize($message->getBody(), SocialNetworkFeedItem::class, 'json');

        $this->feedItemPersister->persistFeedItem($socialNetworkFeedItem)->flush();

        return self::MSG_ACK;
    }
}