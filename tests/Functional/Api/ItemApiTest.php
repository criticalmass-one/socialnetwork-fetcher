<?php declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\AbstractApiTestCase;

class ItemApiTest extends AbstractApiTestCase
{
    public function testGetCollectionReturnsOnlyClientItems(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $members = $data['hydra:member'] ?? $data['member'] ?? [];
        $texts = array_map(fn(array $i) => $i['text'], $members);

        $this->assertNotEmpty($texts);
        foreach ($texts as $text) {
            $this->assertThat(
                $text,
                $this->logicalOr(
                    $this->stringContains('Shared'),
                    $this->stringContains('OnlyA')
                )
            );
        }
    }

    public function testGetCollectionDoesNotContainOtherClientItems(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $members = $data['hydra:member'] ?? $data['member'] ?? [];
        $texts = array_map(fn(array $i) => $i['text'], $members);

        // clientA should not see clientB-only items
        foreach ($texts as $text) {
            $this->assertStringNotContainsString('OnlyB', $text);
        }
    }

    public function testGetSingleItemOwnedByClient(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items');
        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];

        $this->assertNotEmpty($items);

        $itemId = $items[0]['id'];
        $this->requestAsClientA('GET', '/api/items/' . $itemId);

        $this->assertResponseIsSuccessful();
    }

    public function testGetSingleItemNotOwnedReturns404(): void
    {
        // Get an item from clientB
        $responseB = $this->requestAsClientB('GET', '/api/items');
        $dataB = $responseB->toArray();
        $itemsB = $dataB['hydra:member'] ?? $dataB['member'] ?? [];

        // Find an item that belongs to profileOnlyB
        $onlyBItemId = null;
        foreach ($itemsB as $item) {
            if (str_contains($item['text'], 'OnlyB')) {
                $onlyBItemId = $item['id'];
                break;
            }
        }

        $this->assertNotNull($onlyBItemId, 'Should find an OnlyB item');

        // Try to access it as clientA
        $this->requestAsClientA('GET', '/api/items/' . $onlyBItemId);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCollectionOrderedByDateTimeDesc(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];

        if (count($items) > 1) {
            for ($i = 1; $i < count($items); $i++) {
                $this->assertGreaterThanOrEqual(
                    $items[$i]['dateTime'],
                    $items[$i - 1]['dateTime'],
                    'Items should be ordered by dateTime descending'
                );
            }
        }
    }

    public function testHiddenItemsExcludedByDefault(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items');
        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];

        $texts = array_map(fn(array $i) => $i['text'], $items);
        $this->assertNotContains('Shared hidden item', $texts);
    }

    public function testHiddenItemsIncludedWhenFiltered(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items?hidden=true');
        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];

        $texts = array_map(fn(array $i) => $i['text'], $items);
        $this->assertContains('Shared hidden item', $texts);
    }

    public function testSoftDeletedItemsExcludedByDefault(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items');
        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];

        $texts = array_map(fn(array $i) => $i['text'], $items);
        $this->assertNotContains('Shared soft-deleted item', $texts);
    }

    public function testFilterByNetworkIdentifier(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items?profile.network.identifier=bluesky_profile');
        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertStringContainsString('OnlyA', $item['text']);
        }
    }

    public function testFilterByTextPartial(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items?text=Shared');
        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertStringContainsString('Shared', $item['text']);
            $this->assertStringNotContainsString('OnlyA', $item['text']);
        }
    }

    public function testCollectionResponseExcludesRawPayloads(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items');
        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertArrayNotHasKey('raw', $item);
            $this->assertArrayNotHasKey('rawSource', $item);
            $this->assertArrayNotHasKey('parsedSource', $item);
        }
    }

    public function testSingleItemResponseIncludesRawPayloads(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items');
        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];
        $itemId = $items[0]['id'];

        $detail = $this->requestAsClientA('GET', '/api/items/' . $itemId)->toArray();

        $this->assertArrayHasKey('raw', $detail);
        $this->assertArrayHasKey('rawSource', $detail);
        $this->assertArrayHasKey('parsedSource', $detail);
    }

    public function testCollectionResponseIncludesMediaUrlFields(): void
    {
        $response = $this->requestAsClientA('GET', '/api/items');
        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertArrayHasKey('photoUrls', $item);
            $this->assertIsArray($item['photoUrls']);
            $this->assertArrayHasKey('videoUrl', $item);
        }
    }

    public function testFilterByDateTimeAfter(): void
    {
        $cutoff = (new \DateTimeImmutable('-6 hours'))->format(\DateTimeImmutable::ATOM);
        $response = $this->requestAsClientA('GET', '/api/items?dateTime[after]=' . urlencode($cutoff));
        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertGreaterThan($cutoff, $item['dateTime']);
        }
        // Items older than 6h should not be present
        $texts = array_map(fn(array $i) => $i['text'], $items);
        $this->assertNotContains('Shared item 3', $texts);
        $this->assertNotContains('OnlyA item 2', $texts);
    }

    private function firstItemIdForProfile(int $profileId): int
    {
        $data = $this->requestAsClientA('GET', '/api/items?profile=/api/profiles/' . $profileId)->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? [];
        $this->assertNotEmpty($items, 'Expected at least one item for profile ' . $profileId);

        return $items[0]['id'];
    }

    private function enableSavePhotos(int $profileId): void
    {
        $this->requestWithToken('PATCH', '/api/profiles/' . $profileId, self::TOKEN_A, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'body' => json_encode(['savePhotos' => true]),
        ]);
        $this->assertResponseIsSuccessful();
    }

    public function testDownloadMediaTriggerQueuesItem(): void
    {
        $this->enableSavePhotos(90001);
        $itemId = $this->firstItemIdForProfile(90001);

        $this->requestAsClientA('POST', '/api/items/' . $itemId . '/download-media', [
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $this->assertResponseStatusCodeSame(202);

        $detail = $this->requestAsClientA('GET', '/api/items/' . $itemId)->toArray();
        $this->assertSame('pending', $detail['mediaStatus']);
    }

    public function testDownloadMediaTriggerRequiresProfileFlag(): void
    {
        // profileOnlyA (90002) has savePhotos/saveVideos disabled by default
        $itemId = $this->firstItemIdForProfile(90002);

        $this->requestAsClientA('POST', '/api/items/' . $itemId . '/download-media', [
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testDownloadMediaTriggerNotOwnedReturns404(): void
    {
        $dataB = $this->requestAsClientB('GET', '/api/items')->toArray();
        $itemsB = $dataB['hydra:member'] ?? $dataB['member'] ?? [];

        $onlyBItemId = null;
        foreach ($itemsB as $item) {
            if (str_contains($item['text'], 'OnlyB')) {
                $onlyBItemId = $item['id'];
                break;
            }
        }
        $this->assertNotNull($onlyBItemId);

        $this->requestAsClientA('POST', '/api/items/' . $onlyBItemId . '/download-media', [
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }
}
