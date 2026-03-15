<?php declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\AbstractApiTestCase;

class ProfileApiTest extends AbstractApiTestCase
{
    public function testGetCollectionReturnsOnlyClientProfiles(): void
    {
        $response = $this->requestAsClientA('GET', '/api/profiles');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $members = $data['hydra:member'] ?? $data['member'] ?? [];
        $identifiers = array_map(fn(array $p) => $p['identifier'], $members);

        $this->assertContains('https://mastodon.social/@shared', $identifiers);
        $this->assertContains('onlya.bsky.social', $identifiers);
        $this->assertNotContains('https://mastodon.social/@onlyb', $identifiers);
    }

    public function testGetCollectionExcludesSoftDeletedProfiles(): void
    {
        $response = $this->requestAsClientA('GET', '/api/profiles');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $members = $data['hydra:member'] ?? $data['member'] ?? [];
        $identifiers = array_map(fn(array $p) => $p['identifier'], $members);

        $this->assertNotContains('https://mastodon.social/@deleted', $identifiers);
    }

    public function testGetSingleProfileOwnedByClient(): void
    {
        $response = $this->requestAsClientA('GET', '/api/profiles');
        $data = $response->toArray();
        $profiles = $data['hydra:member'] ?? $data['member'] ?? [];

        $profileId = $profiles[0]['id'];

        $this->requestAsClientA('GET', '/api/profiles/' . $profileId);

        $this->assertResponseIsSuccessful();
    }

    public function testGetSingleProfileNotOwnedReturns404(): void
    {
        // Get profileOnlyB via clientB
        $responseB = $this->requestAsClientB('GET', '/api/profiles');
        $dataB = $responseB->toArray();
        $profilesB = $dataB['hydra:member'] ?? $dataB['member'] ?? [];

        $profileOnlyBId = null;
        foreach ($profilesB as $p) {
            if ($p['identifier'] === 'https://mastodon.social/@onlyb') {
                $profileOnlyBId = $p['id'];
                break;
            }
        }

        $this->assertNotNull($profileOnlyBId, 'profileOnlyB should exist for clientB');

        // Try to access it as clientA
        $this->requestAsClientA('GET', '/api/profiles/' . $profileOnlyBId);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPostLinkExistingProfileIdempotent(): void
    {
        $mastodonIri = $this->getMastodonNetworkIri();

        // POST a profile that already exists and is linked to clientA — idempotent
        $response = $this->requestAsClientA('POST', '/api/profiles', [
            'json' => [
                'network' => $mastodonIri,
                'identifier' => 'https://mastodon.social/@shared',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $response->toArray();
        $this->assertSame('https://mastodon.social/@shared', $data['identifier']);
    }

    public function testPostLinkExistingProfile(): void
    {
        $blueskyIri = $this->getNetworkIri('bluesky_profile');

        // Link profileOnlyA (belongs to clientA) to clientB — idempotent
        $response = $this->requestAsClientB('POST', '/api/profiles', [
            'json' => [
                'network' => $blueskyIri,
                'identifier' => 'onlya.bsky.social',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('onlya.bsky.social', $data['identifier']);
    }

    public function testPostReactivatesSoftDeletedProfile(): void
    {
        $mastodonIri = $this->getMastodonNetworkIri();

        $response = $this->requestAsClientA('POST', '/api/profiles', [
            'json' => [
                'network' => $mastodonIri,
                'identifier' => 'https://mastodon.social/@deleted',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('https://mastodon.social/@deleted', $data['identifier']);
        $this->assertFalse($data['deleted']);
    }

    public function testDeleteUnlinksFromClient(): void
    {
        // Get shared profile ID via clientA
        $response = $this->requestAsClientA('GET', '/api/profiles');
        $data = $response->toArray();
        $profiles = $data['hydra:member'] ?? $data['member'] ?? [];

        $sharedId = null;
        foreach ($profiles as $p) {
            if ($p['identifier'] === 'https://mastodon.social/@shared') {
                $sharedId = $p['id'];
                break;
            }
        }

        $this->assertNotNull($sharedId);

        // Delete (unlink) from clientA
        $this->requestAsClientA('DELETE', '/api/profiles/' . $sharedId);
        $this->assertResponseStatusCodeSame(204);

        // ClientA can no longer see it
        $response2 = $this->requestAsClientA('GET', '/api/profiles');
        $data2 = $response2->toArray();
        $profiles2 = $data2['hydra:member'] ?? $data2['member'] ?? [];
        $identifiers = array_map(fn(array $p) => $p['identifier'], $profiles2);
        $this->assertNotContains('https://mastodon.social/@shared', $identifiers);

        // ClientB still sees it
        $responseB = $this->requestAsClientB('GET', '/api/profiles');
        $dataB = $responseB->toArray();
        $profilesB = $dataB['hydra:member'] ?? $dataB['member'] ?? [];
        $identifiersB = array_map(fn(array $p) => $p['identifier'], $profilesB);
        $this->assertContains('https://mastodon.social/@shared', $identifiersB);
    }

    public function testDeleteSoftDeletesWhenLastClient(): void
    {
        // profileOnlyA (90002) is only linked to clientA
        $response = $this->requestAsClientA('GET', '/api/profiles');
        $data = $response->toArray();
        $profiles = $data['hydra:member'] ?? $data['member'] ?? [];

        $onlyAId = null;
        foreach ($profiles as $p) {
            if ($p['identifier'] === 'onlya.bsky.social') {
                $onlyAId = $p['id'];
                break;
            }
        }

        $this->assertNotNull($onlyAId);

        // Delete — clientA is the only client, so soft-delete happens
        $this->requestAsClientA('DELETE', '/api/profiles/' . $onlyAId);
        $this->assertResponseStatusCodeSame(204);

        // Gone from collection
        $this->requestAsClientA('GET', '/api/profiles/' . $onlyAId);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testProfilesIncludeNetworkData(): void
    {
        $response = $this->requestAsClientA('GET', '/api/profiles');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $members = $data['hydra:member'] ?? $data['member'] ?? [];

        foreach ($members as $profile) {
            $this->assertArrayHasKey('network', $profile);
            $this->assertArrayHasKey('identifier', $profile['network']);
        }
    }

    private function getMastodonNetworkIri(): string
    {
        return $this->getNetworkIri('mastodon');
    }

    private function getNetworkIri(string $identifier): string
    {
        $response = $this->requestAsClientA('GET', '/api/networks');
        $data = $response->toArray();
        $networks = $data['hydra:member'] ?? $data['member'] ?? [];

        foreach ($networks as $n) {
            if ($n['identifier'] === $identifier) {
                return '/api/networks/' . $n['id'];
            }
        }

        throw new \RuntimeException("Network '$identifier' not found");
    }
}
