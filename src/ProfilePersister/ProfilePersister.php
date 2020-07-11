<?php declare(strict_types=1);

namespace App\ProfilePersister;

use App\FeedFetcher\FetchInfo;
use App\Model\SocialNetworkProfile;
use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;

class ProfilePersister implements ProfilePersisterInterface
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

    public function persistProfile(SocialNetworkProfile $socialNetworkProfile): SocialNetworkProfile
    {
        $jsonData = $this->serializer->serialize($socialNetworkProfile, 'json');

        $uri = sprintf('/api/hamburg/socialnetwork-profiles/%d', $socialNetworkProfile->getId());

        $result = $this->client->post($uri, [
            'body' => $jsonData,
        ]);

        return $socialNetworkProfile;
    }
}
