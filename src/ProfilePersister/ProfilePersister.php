<?php declare(strict_types=1);

namespace App\ProfilePersister;

use App\Model\SocialNetworkProfile;
use App\Serializer\SerializerInterface;
use GuzzleHttp\Client;

class ProfilePersister implements ProfilePersisterInterface
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
