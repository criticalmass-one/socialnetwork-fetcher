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
}
