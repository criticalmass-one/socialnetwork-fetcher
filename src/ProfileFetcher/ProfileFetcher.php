<?php declare(strict_types=1);

namespace App\ProfileFetcher;

use App\FeedFetcher\FetchInfo;
use JMS\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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

    public function fetchByNetworkIdentifier(string $networkIdentifier, string $citySlug = null): array
    {
        $parameters = [
            'networkIdentifier' => $networkIdentifier,
            'entities' => 'city',
        ];

        if ($citySlug) {
            $parameters['citySlug'] = $citySlug;
        }

        $query = sprintf('/api/socialnetwork-profiles?%s', http_build_query($parameters));

        $result = $this->client->request('GET', $query);

        $jsonContent = $result->getContent();

        $profileList = $this->serializer->deserialize($jsonContent, 'array<App\Model\SocialNetworkProfile>', 'json');

        return $profileList;
    }

    public function fetchByNetworkIdentifiers(array $networkIdentifiers = [], string $citySlug = null): array
    {
        $profileList = [];

        foreach ($networkIdentifiers as $networkIdentifier) {
            $profileList += $this->fetchByNetworkIdentifier($networkIdentifier, $citySlug);
        }

        return $profileList;
    }

    public function fetchByFetchInfo(FetchInfo $fetchInfo): array
    {
        return $this->fetchByNetworkIdentifiers($fetchInfo->getNetworkList(), $fetchInfo->getCitySlug());
    }
}