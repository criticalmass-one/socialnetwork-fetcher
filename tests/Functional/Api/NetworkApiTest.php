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

        $networkId = $networks[0]['id'];

        $this->requestAsClientA('GET', '/api/networks/' . $networkId);

        $this->assertResponseIsSuccessful();
    }

    public function testPostCreateNetwork(): void
    {
        $response = $this->requestAsClientA('POST', '/api/networks', [
            'json' => [
                'identifier' => 'test_network',
                'name' => 'Test Network',
                'icon' => 'fas fa-test',
                'backgroundColor' => '#000000',
                'textColor' => '#ffffff',
                'profileUrlPattern' => '#^https?://test\.example\.com/.+$#i',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $response->toArray();
        $this->assertSame('test_network', $data['identifier']);
        $this->assertSame('Test Network', $data['name']);
    }

    public function testPutUpdateNetwork(): void
    {
        // Create a network to update
        $response = $this->requestAsClientA('POST', '/api/networks', [
            'json' => [
                'identifier' => 'update_test',
                'name' => 'Before Update',
                'icon' => 'fas fa-test',
                'backgroundColor' => '#000000',
                'textColor' => '#ffffff',
                'profileUrlPattern' => '#^https?://test\.example\.com/.+$#i',
            ],
        ]);

        $id = $response->toArray()['id'];

        $this->requestAsClientA('PUT', '/api/networks/' . $id, [
            'json' => [
                'identifier' => 'update_test',
                'name' => 'After Update',
                'icon' => 'fas fa-test',
                'backgroundColor' => '#111111',
                'textColor' => '#eeeeee',
                'profileUrlPattern' => '#^https?://test\.example\.com/.+$#i',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $updated = $this->requestAsClientA('GET', '/api/networks/' . $id)->toArray();
        $this->assertSame('After Update', $updated['name']);
    }
}
