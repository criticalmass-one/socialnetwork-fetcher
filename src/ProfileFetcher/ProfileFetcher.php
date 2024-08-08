<?php declare(strict_types=1);

namespace App\ProfileFetcher;

use App\FeedFetcher\FetchInfo;
use App\Model\SocialNetworkProfile;
use App\Serializer\SerializerInterface;
use GuzzleHttp\Client;

class ProfileFetcher implements ProfileFetcherInterface
{
    protected Client $client;

    public function __construct(
        private readonly SerializerInterface $serializer,
        string $criticalmassHostname
    ) {
        $this->client = new Client([
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

        $result = $this->client->get($query);

        $jsonContent = $result->getBody()->getContents();

        $profileList = $this->serializer->deserialize($jsonContent, sprintf('%s[]', SocialNetworkProfile::class), 'json');

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