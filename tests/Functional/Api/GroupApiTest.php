<?php declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\AbstractApiTestCase;

class GroupApiTest extends AbstractApiTestCase
{
    public function testGetCollectionEmptyInitially(): void
    {
        $response = $this->requestAsClientA('GET', '/api/groups');
        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $members = $data['hydra:member'] ?? $data['member'] ?? [];
        $this->assertSame([], $members);
    }

    public function testPostCreateGroup(): void
    {
        $sharedProfileIri = $this->ownedProfileIri('https://mastodon.social/@shared');

        $response = $this->requestAsClientA('POST', '/api/groups', [
            'json' => [
                'name' => 'My Mix',
                'description' => 'Test group',
                'color' => '#ff8800',
                'profiles' => [$sharedProfileIri],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('My Mix', $data['name']);
        $this->assertSame(1, $data['profileCount']);
    }

    public function testPostRefusesProfileNotLinkedToClient(): void
    {
        // ClientA tries to add profileOnlyB (linked to clientB)
        $onlyBProfileId = $this->lookupProfileIdAsClientB('https://mastodon.social/@onlyb');

        $this->requestAsClientA('POST', '/api/groups', [
            'json' => [
                'name' => 'Bad Group',
                'profiles' => ['/api/profiles/' . $onlyBProfileId],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetSingleGroupEmbedsProfiles(): void
    {
        $groupId = $this->createGroup('Detail Test', [
            $this->ownedProfileIri('https://mastodon.social/@shared'),
        ]);

        $response = $this->requestAsClientA('GET', '/api/groups/' . $groupId);
        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertSame('Detail Test', $data['name']);
        $this->assertArrayHasKey('profiles', $data);
        $this->assertCount(1, $data['profiles']);
    }

    public function testGetCollectionDoesNotEmbedProfiles(): void
    {
        $this->createGroup('Slim Test', [
            $this->ownedProfileIri('https://mastodon.social/@shared'),
        ]);

        $response = $this->requestAsClientA('GET', '/api/groups');
        $data = $response->toArray();
        $members = $data['hydra:member'] ?? $data['member'] ?? [];

        $this->assertNotEmpty($members);
        foreach ($members as $group) {
            $this->assertArrayNotHasKey('profiles', $group);
            $this->assertArrayHasKey('profileCount', $group);
        }
    }

    public function testClientAndClientBSeeSeparateGroups(): void
    {
        $this->createGroup('Group A', [], 'A');
        $this->createGroup('Group B', [], 'B');

        $aMembers = $this->extractMembers($this->requestAsClientA('GET', '/api/groups'));
        $bMembers = $this->extractMembers($this->requestAsClientB('GET', '/api/groups'));

        $aNames = array_map(fn($g) => $g['name'], $aMembers);
        $bNames = array_map(fn($g) => $g['name'], $bMembers);

        $this->assertContains('Group A', $aNames);
        $this->assertNotContains('Group B', $aNames);
        $this->assertContains('Group B', $bNames);
        $this->assertNotContains('Group A', $bNames);
    }

    public function testGetForeignGroupReturns404(): void
    {
        $groupId = $this->createGroup('Other client group', [], 'B');

        $this->requestAsClientA('GET', '/api/groups/' . $groupId);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteGroup(): void
    {
        $groupId = $this->createGroup('To delete', []);

        $this->requestAsClientA('DELETE', '/api/groups/' . $groupId);
        $this->assertResponseStatusCodeSame(204);

        $this->requestAsClientA('GET', '/api/groups/' . $groupId);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAddProfilesConvenienceRoute(): void
    {
        $groupId = $this->createGroup('Membership Test', []);
        $sharedIri = $this->ownedProfileIri('https://mastodon.social/@shared');

        $response = $this->requestAsClientA('POST', '/api/groups/' . $groupId . '/profiles', [
            'json' => ['profiles' => [$sharedIri]],
        ]);

        $this->assertResponseIsSuccessful();

        $check = $this->requestAsClientA('GET', '/api/groups/' . $groupId)->toArray();
        $this->assertCount(1, $check['profiles']);
    }

    public function testAddProfilesRefusesForeignProfile(): void
    {
        $groupId = $this->createGroup('Membership Test', []);
        $onlyBProfileId = $this->lookupProfileIdAsClientB('https://mastodon.social/@onlyb');

        $this->requestAsClientA('POST', '/api/groups/' . $groupId . '/profiles', [
            'json' => ['profileIds' => [$onlyBProfileId]],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRemoveProfileConvenienceRoute(): void
    {
        $sharedIri = $this->ownedProfileIri('https://mastodon.social/@shared');
        $groupId = $this->createGroup('Membership Test', [$sharedIri]);

        $sharedId = (int) substr($sharedIri, strrpos($sharedIri, '/') + 1);

        $this->requestAsClientA('DELETE', sprintf('/api/groups/%d/profiles/%d', $groupId, $sharedId));
        $this->assertResponseStatusCodeSame(204);

        $check = $this->requestAsClientA('GET', '/api/groups/' . $groupId)->toArray();
        $this->assertSame([], $check['profiles']);
    }

    public function testForeignGroupCannotBeModified(): void
    {
        $groupId = $this->createGroup('Other client group', [], 'B');

        $this->requestAsClientA('DELETE', '/api/groups/' . $groupId);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testPutForeignGroupReturns404(): void
    {
        $groupId = $this->createGroup('Foreign Put', [], 'B');

        $this->requestAsClientA('PUT', '/api/groups/' . $groupId, [
            'json' => ['name' => 'Hijacked', 'profiles' => []],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPatchUpdatesNameAndKeepsProfiles(): void
    {
        $sharedIri = $this->ownedProfileIri('https://mastodon.social/@shared');
        $groupId = $this->createGroup('Before Patch', [$sharedIri]);

        $response = $this->requestWithToken('PATCH', '/api/groups/' . $groupId, self::TOKEN_A, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'body' => json_encode(['name' => 'After Patch', 'color' => '#00aa00']),
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('After Patch', $data['name']);
        $this->assertSame('#00aa00', $data['color']);

        // Partielles Update lässt die Mitgliedschaft unberührt
        $check = $this->requestAsClientA('GET', '/api/groups/' . $groupId)->toArray();
        $this->assertCount(1, $check['profiles']);
    }

    public function testGroupItemsEndpoint(): void
    {
        $sharedIri = $this->ownedProfileIri('https://mastodon.social/@shared');
        $groupId = $this->createGroup('Items Test', [$sharedIri]);

        $response = $this->requestAsClientA('GET', '/api/groups/' . $groupId . '/items');
        $this->assertResponseIsSuccessful();

        $items = $this->extractMembers($response);
        $this->assertNotEmpty($items, 'group items endpoint should return shared items');
        foreach ($items as $item) {
            $this->assertStringContainsString('Shared', $item['text']);
        }
    }

    public function testGroupItemsEndpoint404ForForeignGroup(): void
    {
        $groupId = $this->createGroup('Foreign Items Test', [], 'B');

        $this->requestAsClientA('GET', '/api/groups/' . $groupId . '/items');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testGroupItemsExcludesHiddenAndDeleted(): void
    {
        $sharedIri = $this->ownedProfileIri('https://mastodon.social/@shared');
        $groupId = $this->createGroup('Hidden Test', [$sharedIri]);

        $items = $this->extractMembers($this->requestAsClientA('GET', '/api/groups/' . $groupId . '/items'));
        $texts = array_map(fn($i) => $i['text'], $items);

        $this->assertNotContains('Shared hidden item', $texts);
        $this->assertNotContains('Shared soft-deleted item', $texts);
    }

    public function testGroupRssFeed(): void
    {
        $sharedIri = $this->ownedProfileIri('https://mastodon.social/@shared');
        $groupId = $this->createGroup('Rss Test', [$sharedIri]);

        $response = $this->requestAsClientA('GET', '/api/feeds/groups/' . $groupId . '.rss', ['headers' => ['Accept' => '*/*']]);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('application/rss+xml', $response->getHeaders()['content-type'][0]);

        $body = $response->getContent();
        $this->assertStringStartsWith('<?xml', $body);
        $this->assertStringContainsString('<rss ', $body);
        $this->assertStringContainsString('Shared item 1', $body);
    }

    public function testGroupRssFeed404ForForeignGroup(): void
    {
        $groupId = $this->createGroup('Foreign Rss Test', [], 'B');

        $this->requestAsClientA('GET', '/api/feeds/groups/' . $groupId . '.rss', ['headers' => ['Accept' => '*/*']]);
        $this->assertResponseStatusCodeSame(404);
    }

    private function extractMembers(\Symfony\Contracts\HttpClient\ResponseInterface $response): array
    {
        $data = $response->toArray();
        return $data['hydra:member'] ?? $data['member'] ?? [];
    }

    private function ownedProfileIri(string $identifier, string $client = 'A'): string
    {
        $response = $client === 'A'
            ? $this->requestAsClientA('GET', '/api/profiles')
            : $this->requestAsClientB('GET', '/api/profiles');

        foreach ($this->extractMembers($response) as $profile) {
            if (($profile['identifier'] ?? null) === $identifier) {
                return '/api/profiles/' . $profile['id'];
            }
        }
        throw new \RuntimeException(sprintf('Profile %s not found for client %s', $identifier, $client));
    }

    private function lookupProfileIdAsClientB(string $identifier): int
    {
        $response = $this->requestAsClientB('GET', '/api/profiles');
        $members = $this->extractMembers($response);

        foreach ($members as $profile) {
            if ($profile['identifier'] === $identifier) {
                return (int) $profile['id'];
            }
        }
        throw new \RuntimeException('Profile not found: ' . $identifier);
    }

    /** @param list<string> $profileIris */
    private function createGroup(string $name, array $profileIris = [], string $client = 'A'): int
    {
        $response = $client === 'A'
            ? $this->requestAsClientA('POST', '/api/groups', ['json' => ['name' => $name, 'profiles' => $profileIris]])
            : $this->requestAsClientB('POST', '/api/groups', ['json' => ['name' => $name, 'profiles' => $profileIris]]);

        $this->assertResponseStatusCodeSame(201);
        return (int) $response->toArray()['id'];
    }
}
