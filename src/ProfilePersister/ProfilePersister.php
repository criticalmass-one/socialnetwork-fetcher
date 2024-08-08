<?php declare(strict_types=1);

namespace App\ProfilePersister;

use App\Model\SocialNetworkProfile;
use JMS\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProfilePersister implements ProfilePersisterInterface
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

    public function persistProfile(SocialNetworkProfile $socialNetworkProfile): SocialNetworkProfile
    {
        $jsonData = $this->serializer->serialize($socialNetworkProfile, 'json');

        $uri = sprintf('/api/hamburg/socialnetwork-profiles/%d', $socialNetworkProfile->getId());

        $result = $this->client->request('POST', $uri, [
            'body' => $jsonData,
        ]);

        return $socialNetworkProfile;
    }
}
