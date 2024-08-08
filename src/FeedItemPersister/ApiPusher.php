<?php declare(strict_types=1);

namespace App\FeedItemPersister;

use App\FeedFetcher\FetchResult;
use App\Model\SocialNetworkFeedItem;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiPusher implements FeedItemPersisterInterface
{
    private HttpClientInterface $client;

    public function __construct(
        private readonly SerializerInterface $serializer,
        HttpClientInterface $client,
        string $criticalmassHostname)
    {
        $this->client = $client->withOptions([
            'base_uri' => $criticalmassHostname,
        ]);
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
            $response = $this->client->request('GET', '/api/hamburg/socialnetwork-feeditems', [
                'body' => $jsonData
            ]);
        } catch (ClientException $exception) { // got a 4xx status code response
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
