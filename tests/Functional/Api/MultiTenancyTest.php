<?php declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\AbstractApiTestCase;

class MultiTenancyTest extends AbstractApiTestCase
{
    private function getMembers(array $data): array
    {
        if (isset($data[0])) {
            return $data;
        }

        return $data['hydra:member'] ?? $data['member'] ?? [];
    }

    public function testClientACannotSeeClientBProfiles(): void
    {
        $response = $this->requestAsClientA('GET', '/api/profiles');
        $identifiers = array_map(fn(array $p) => $p['identifier'], $this->getMembers($response->toArray()));

        $this->assertNotContains('https://mastodon.social/@onlyb', $identifiers);
    }

    public function testClientBCannotSeeClientAProfiles(): void
    {
        $response = $this->requestAsClientB('GET', '/api/profiles');
        $identifiers = array_map(fn(array $p) => $p['identifier'], $this->getMembers($response->toArray()));

        $this->assertNotContains('onlya.bsky.social', $identifiers);
    }

    public function testSharedProfileVisibleToBoth(): void
    {
        $responseA = $this->requestAsClientA('GET', '/api/profiles');
        $identifiersA = array_map(fn(array $p) => $p['identifier'], $this->getMembers($responseA->toArray()));

        $responseB = $this->requestAsClientB('GET', '/api/profiles');
        $identifiersB = array_map(fn(array $p) => $p['identifier'], $this->getMembers($responseB->toArray()));

        $this->assertContains('https://mastodon.social/@shared', $identifiersA);
        $this->assertContains('https://mastodon.social/@shared', $identifiersB);
    }

    public function testClientACannotSeeClientBItems(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items');
        $texts = array_map(fn(array $i) => $i['text'], $this->getMembers($response->toArray()));

        foreach ($texts as $text) {
            $this->assertStringNotContainsString('OnlyB', $text);
        }
    }

    public function testSharedProfileItemsVisibleToBoth(): void
    {
        $responseA = $this->requestAsClientA('GET', '/api/items');
        $textsA = array_map(fn(array $i) => $i['text'], $this->getMembers($responseA->toArray()));

        $responseB = $this->requestAsClientB('GET', '/api/items');
        $textsB = array_map(fn(array $i) => $i['text'], $this->getMembers($responseB->toArray()));

        $this->assertContains('Shared item 1', $textsA);
        $this->assertContains('Shared item 1', $textsB);
    }

    public function testClientACannotDeleteClientBProfile(): void
    {
        // Get profileOnlyB ID via clientB
        $responseB = $this->requestAsClientB('GET', '/api/profiles');

        $onlyBId = null;
        foreach ($this->getMembers($responseB->toArray()) as $p) {
            if ($p['identifier'] === 'https://mastodon.social/@onlyb') {
                $onlyBId = $p['id'];
                break;
            }
        }

        $this->assertNotNull($onlyBId);

        // Try to delete as clientA
        $this->requestAsClientA('DELETE', '/api/profiles/' . $onlyBId);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testTimelineIsolation(): void
    {
        $since = (new \DateTimeImmutable('-50 hours'))->format(\DateTimeInterface::ATOM);

        $responseA = $this->requestAsClientA('GET', '/api/timeline?since=' . urlencode($since));
        $dataA = $responseA->toArray();
        $itemsA = $this->getMembers($dataA);
        $textsA = array_map(fn(array $i) => $i['text'], $itemsA);

        $responseB = $this->requestAsClientB('GET', '/api/timeline?since=' . urlencode($since));
        $dataB = $responseB->toArray();
        $itemsB = $this->getMembers($dataB);
        $textsB = array_map(fn(array $i) => $i['text'], $itemsB);

        // ClientA sees shared + onlyA, NOT onlyB
        $this->assertNotContains('OnlyB item 1', $textsA);
        $this->assertNotContains('OnlyB item 2', $textsA);

        // ClientB sees shared + onlyB, NOT onlyA
        $this->assertNotContains('OnlyA item 1', $textsB);
        $this->assertNotContains('OnlyA item 2', $textsB);

        // Both see shared items
        $this->assertContains('Shared item 1', $textsA);
        $this->assertContains('Shared item 1', $textsB);
    }
}
