<?php declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\AbstractApiTestCase;

class TimelineApiTest extends AbstractApiTestCase
{
    public function testTimelineDefaultsToLast24Hours(): void
    {
        $response = $this->requestAsClientA('GET', '/api/timeline');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? $data;
        // If the response is a plain array (non-paginated), use it directly
        if (isset($data[0])) {
            $items = $data;
        }

        $texts = array_map(fn(array $i) => $i['text'], $items);

        // Items within 24h for clientA: shared-item-1 (1h), shared-item-2 (12h), onlya-item-1 (2h)
        $this->assertContains('Shared item 1', $texts);
        $this->assertContains('Shared item 2', $texts);
        $this->assertContains('OnlyA item 1', $texts);
        // Items outside 24h should not be included
        $this->assertNotContains('Shared item 3', $texts);
        $this->assertNotContains('OnlyA item 2', $texts);
    }

    public function testTimelineLimitParameter(): void
    {
        $response = $this->requestAsClientA('GET', '/api/timeline?limit=2');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? $data;
        if (isset($data[0])) {
            $items = $data;
        }

        $this->assertCount(2, $items);
    }

    public function testTimelineLimitClampedToMax500(): void
    {
        $response = $this->requestAsClientA('GET', '/api/timeline?limit=1000');

        $this->assertResponseIsSuccessful();
    }

    public function testTimelineSinceParameter(): void
    {
        $since = (new \DateTimeImmutable('-50 hours'))->format(\DateTimeInterface::ATOM);
        $response = $this->requestAsClientA('GET', '/api/timeline?since=' . urlencode($since));

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? $data;
        if (isset($data[0])) {
            $items = $data;
        }

        $texts = array_map(fn(array $i) => $i['text'], $items);

        // All clientA items should be included
        $this->assertContains('Shared item 1', $texts);
        $this->assertContains('Shared item 3', $texts);
        $this->assertContains('OnlyA item 2', $texts);
    }

    public function testTimelineUntilParameter(): void
    {
        $until = (new \DateTimeImmutable('-6 hours'))->format(\DateTimeInterface::ATOM);
        $since = (new \DateTimeImmutable('-50 hours'))->format(\DateTimeInterface::ATOM);
        $response = $this->requestAsClientA('GET', '/api/timeline?since=' . urlencode($since) . '&until=' . urlencode($until));

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? $data;
        if (isset($data[0])) {
            $items = $data;
        }

        $texts = array_map(fn(array $i) => $i['text'], $items);

        $this->assertNotContains('Shared item 1', $texts); // 1h ago — too recent
        $this->assertNotContains('OnlyA item 1', $texts);  // 2h ago — too recent
        $this->assertContains('Shared item 2', $texts);     // 12h ago
    }

    public function testTimelineSinceAndUntilCombined(): void
    {
        $since = (new \DateTimeImmutable('-13 hours'))->format(\DateTimeInterface::ATOM);
        $until = (new \DateTimeImmutable('-1 hour -30 minutes'))->format(\DateTimeInterface::ATOM);
        $response = $this->requestAsClientA('GET', '/api/timeline?since=' . urlencode($since) . '&until=' . urlencode($until));

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? $data;
        if (isset($data[0])) {
            $items = $data;
        }

        $texts = array_map(fn(array $i) => $i['text'], $items);

        $this->assertContains('OnlyA item 1', $texts);
        $this->assertContains('Shared item 2', $texts);
    }

    public function testTimelineNetworkFilter(): void
    {
        $since = (new \DateTimeImmutable('-50 hours'))->format(\DateTimeInterface::ATOM);
        $response = $this->requestAsClientA('GET', '/api/timeline?network=mastodon&since=' . urlencode($since));

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? $data;
        if (isset($data[0])) {
            $items = $data;
        }

        $texts = array_map(fn(array $i) => $i['text'], $items);

        // Only mastodon items: shared items
        foreach ($texts as $text) {
            $this->assertStringContainsString('Shared', $text);
        }
        $this->assertNotContains('OnlyA item 1', $texts);
    }

    public function testTimelineClientScoped(): void
    {
        $since = (new \DateTimeImmutable('-50 hours'))->format(\DateTimeInterface::ATOM);
        $response = $this->requestAsClientA('GET', '/api/timeline?since=' . urlencode($since));

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $items = $data['hydra:member'] ?? $data['member'] ?? $data;
        if (isset($data[0])) {
            $items = $data;
        }

        $texts = array_map(fn(array $i) => $i['text'], $items);

        $this->assertNotContains('OnlyB item 1', $texts);
        $this->assertNotContains('OnlyB item 2', $texts);
    }
}
