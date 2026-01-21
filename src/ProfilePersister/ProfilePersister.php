<?php declare(strict_types=1);

namespace App\ProfilePersister;

use App\Model\Profile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Serializer\SerializerInterface;

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

    public function persistProfile(Profile $profile): Profile
    {
        $jsonData = $this->serializer->serialize($profile, 'json');

        $uri = sprintf('/api/hamburg/socialnetwork-profiles/%d', $profile->getId());

        $result = $this->client->request('POST', $uri, [
            'body' => $jsonData,
        ]);

        return $profile;
    }
}
