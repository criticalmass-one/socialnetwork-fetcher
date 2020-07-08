<?php declare(strict_types=1);

namespace App\ProfileFetcher;

use App\Model\SocialNetworkProfile;
use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;

class ProfileFetcher implements ProfileFetcherInterface
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

    public function fetchByNetworkIdentifier(string $networkIdentifier): array
    {
        $result = $this->client->get(sprintf('/api/socialnetwork-profiles?citySlug=hamburg&networkIdentifier=%s', $networkIdentifier));

        $jsonContent = $result->getBody()->getContents();

        $profileList = $this->serializer->deserialize($jsonContent, 'array<App\Model\SocialNetworkProfile>', 'json');

        return $profileList;
    }


    public function fetchByNetworkIdentifiers(array $networkIdentifiers = []): array
    {
        $profileList = [];

        foreach ($networkIdentifiers as $networkIdentifier) {
            $profileList += $this->fetchByNetworkIdentifier($networkIdentifier);
        }

        return $profileList;
    }
}