<?php declare(strict_types=1);

namespace App\FeedItemPersister;

use App\FeedFetcher\FetchResult;
use App\Model\SocialNetworkFeedItem;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use JMS\Serializer\SerializerInterface;

class ApiPusher implements FeedItemPersisterInterface
{
    protected Client $client;
    protected SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer, string $criticalmassHostname)
    {
        $this->client = new Client([
            'base_uri' => $criticalmassHostname,
        ]);

        $this->serializer = $serializer;
    }

    public function persistFeedItemList(array $feedItemList, ?FetchResult $fetchResult): FeedItemPersisterInterface
    {
        foreach ($feedItemList as $feedItem) {
            $this->persistFeedItem($feedItem, $fetchResult);
        }

        return $this;
    }

    public function persistFeedItem(SocialNetworkFeedItem $feedItem, ?FetchResult $fetchResult): FeedItemPersisterInterface
    {
        $jsonData = $this->serializer->serialize($feedItem, 'json');

        try {
            $response = $this->client->put('/api/hamburg/socialnetwork-feeditems', [
                'body' => $jsonData
            ]);
        } catch (ClientException $exception) { // got a 4xx status code response
            dd($exception->getMessage());
            if ($fetchResult) {
                $fetchResult->incCounterPushed4xx();
            }

            return $this;
        } catch (ServerException $exception) // got a 5xx status code response
        {
            if ($fetchResult) {
                $fetchResult->incCounterPushed5xx();
            }

            return $this;
        }

        $fetchResult->incCounterPushed200();

        return $this;
    }

    public function flush(): FeedItemPersisterInterface
    {
        return $this;
    }
}