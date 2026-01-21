<?php declare(strict_types=1);

namespace App\Consumer;

use App\Model\Item;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

class FeedItemConsumer extends AbstractFeedItemConsumer implements ConsumerInterface
{
    public function execute(AMQPMessage $message): int
    {
        /** @var Item $item */
        $item = $this->serializer->deserialize($message->getBody(), Item::class, 'json');

        $this->feedItemPersister->persistFeedItem($item)->flush();

        return self::MSG_ACK;
    }
}