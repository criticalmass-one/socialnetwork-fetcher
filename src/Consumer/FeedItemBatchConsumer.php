<?php declare(strict_types=1);

namespace App\Consumer;

use App\Model\Item;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

class FeedItemBatchConsumer extends AbstractFeedItemConsumer implements BatchConsumerInterface
{
    public function batchExecute(array $messages): array
    {
        $itemList = [];
        $resultList = [];

        /** @var AMQPMessage $message */
        foreach ($messages as $message) {
            $itemList[] = $this->serializer->deserialize($message->getBody(), Item::class, 'json');

            $resultList[(int)$message->delivery_info['delivery_tag']] = true;
        }

        $this->feedItemPersister->persistFeedItemList($itemList)->flush();

        return $resultList;
    }
}
