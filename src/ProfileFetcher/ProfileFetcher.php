<?php declare(strict_types=1);

namespace App\ProfileFetcher;

use App\FeedFetcher\FetchInfo;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Model\Profile;
use App\Serializer\SerializerInterface;

class ProfileFetcher implements ProfileFetcherInterface
{
    private HttpClientInterface $client;

    public function __construct(
        private readonly SerializerInterface $serializer,
        HttpClientInterface $client,
        string $criticalmassHostname
    ) {
        $this->client = $client->withOptions([
            'base_uri' => $criticalmassHostname,
        ]);
    }

    public function fetchByNetworkIdentifier(string $networkIdentifier): array
    {
        $parameters = [
            'networkIdentifier' => $networkIdentifier,
            'entities' => 'city',
        ];

        $query = sprintf('/api/socialnetwork-profiles?%s', http_build_query($parameters));

        $result = $this->client->request('GET', $query);

        $jsonContent = $result->getContent();

        return $this->serializer->deserialize($jsonContent, sprintf('%s[]', Profile::class), 'json');
    }

    public function fetchByNetworkIdentifiers(array $networkIdentifiers = []): array
    {
        $profileList = [];

        foreach ($networkIdentifiers as $networkIdentifier) {
            $profileList += $this->fetchByNetworkIdentifier($networkIdentifier);
        }

        return $profileList;
    }

    public function fetchByFetchInfo(FetchInfo $fetchInfo): array
    {
        return $this->fetchByNetworkIdentifiers($fetchInfo->getNetworkList());
    }
}