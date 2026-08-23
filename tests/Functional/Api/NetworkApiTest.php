<?php declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\AbstractApiTestCase;

class NetworkApiTest extends AbstractApiTestCase
{
    public function testGetCollectionReturnsNetworks(): void
    {
        $response = $this->requestAsClientA('GET', '/api/networks');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $members = $data['hydra:member'] ?? $data['member'] ?? [];
        $this->assertGreaterThan(0, count($members));

        $identifiers = array_map(fn(array $n) => $n['identifier'], $members);
        $this->assertContains('mastodon', $identifiers);
        $this->assertContains('bluesky_profile', $identifiers);
    }

    public function testGetSingleNetwork(): void
    {
        $response = $this->requestAsClientA('GET', '/api/networks');
        $data = $response->toArray();
        $networks = $data['hydra:member'] ?? $data['member'] ?? [];

        if ([] === $networks) {
            self::markTestSkipped('No network fixture available - cannot exercise the client guard.');
        }

        $networkId = $networks[0]['id'];

        $this->requestAsClientA('GET', '/api/networks/' . $networkId);

        $this->assertResponseIsSuccessful();
    }

    public function testPostCreateNetworkIsForbiddenForClients(): void
    {
        // Networks are global configuration shared by every client. A Bearer
        // token must not be able to add one (see the ROLE_ADMIN guard on the
        // Network resource); the Web UI / a migration is the way in.
        $this->requestAsClientA('POST', '/api/networks', [
            'json' => [
                'identifier' => 'test_network',
                'name' => 'Test Network',
                'icon' => 'fas fa-test',
                'backgroundColor' => '#000000',
                'textColor' => '#ffffff',
                'profileUrlPattern' => '#^https?://test\\.example\\.com/.+$#i',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testPutUpdateNetworkIsForbiddenForClients(): void
    {
        $response = $this->requestAsClientA('GET', '/api/networks');
        $data = $response->toArray();
        $networks = $data['hydra:member'] ?? $data['member'] ?? [];
        if ([] === $networks) {
            self::markTestSkipped('No network fixture available - cannot exercise the client guard.');
        }

        $id = $networks[0]['id'];

        $this->requestAsClientA('PUT', '/api/networks/' . $id, [
            'json' => [
                'identifier' => 'update_test',
                'name' => 'After Update',
                'icon' => 'fas fa-test',
                'backgroundColor' => '#111111',
                'textColor' => '#eeeeee',
                'profileUrlPattern' => '#^https?://test\\.example\\.com/.+$#i',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
