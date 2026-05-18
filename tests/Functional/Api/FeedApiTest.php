<?php declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\AbstractApiTestCase;

class FeedApiTest extends AbstractApiTestCase
{
    public function testTimelineRssReturnsXmlContentType(): void
    {
        $response = $this->requestAsClientA('GET', '/api/feeds/timeline.rss', ['headers' => ['Accept' => '*/*']]);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('application/rss+xml', $response->getHeaders()['content-type'][0]);
    }

    public function testTimelineRssBodyIsValidRss(): void
    {
        $response = $this->requestAsClientA('GET', '/api/feeds/timeline.rss', ['headers' => ['Accept' => '*/*']]);
        $body = $response->getContent();

        $this->assertStringStartsWith('<?xml', $body);
        $this->assertStringContainsString('<rss ', $body);
        $this->assertStringContainsString('version="2.0"', $body);
        $this->assertStringContainsString('<channel>', $body);
        $this->assertStringContainsString('<atom:link', $body);
    }

    public function testTimelineRssContainsClientItems(): void
    {
        $response = $this->requestAsClientA('GET', '/api/feeds/timeline.rss?since=' . urlencode((new \DateTimeImmutable('-50 hours'))->format(\DateTimeInterface::ATOM)), ['headers' => ['Accept' => '*/*']]);

        $body = $response->getContent();
        $this->assertStringContainsString('Shared item 1', $body);
        $this->assertStringContainsString('OnlyA item 1', $body);
        // Items from clientB or hidden/deleted shouldn't leak in:
        $this->assertStringNotContainsString('OnlyB item 1', $body);
        $this->assertStringNotContainsString('Shared hidden item', $body);
        $this->assertStringNotContainsString('Shared soft-deleted item', $body);
    }

    public function testTimelineRssRequiresAuth(): void
    {
        // No Bearer token at all
        $client = static::createClient();
        $client->request('GET', '/api/feeds/timeline.rss');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testProfileFeedRssReturnsItemsForOwnedProfile(): void
    {
        // Look up profileShared id via the API
        $profilesResponse = $this->requestAsClientA('GET', '/api/profiles');
        $data = $profilesResponse->toArray();
        $members = $data['hydra:member'] ?? $data['member'] ?? [];

        $sharedId = null;
        foreach ($members as $profile) {
            if ($profile['identifier'] === 'https://mastodon.social/@shared') {
                $sharedId = $profile['id'];
                break;
            }
        }
        $this->assertNotNull($sharedId);

        $response = $this->requestAsClientA('GET', '/api/feeds/profiles/' . $sharedId . '.rss', ['headers' => ['Accept' => '*/*']]);
        $this->assertResponseIsSuccessful();

        $body = $response->getContent();
        $this->assertStringContainsString('Shared item 1', $body);
        $this->assertStringNotContainsString('OnlyA item 1', $body);
    }

    public function testProfileFeedRssReturns404ForNotOwnedProfile(): void
    {
        // Look up profileOnlyB id via clientB then request via clientA
        $profilesB = $this->requestAsClientB('GET', '/api/profiles')->toArray();
        $members = $profilesB['hydra:member'] ?? $profilesB['member'] ?? [];

        $onlyBId = null;
        foreach ($members as $profile) {
            if ($profile['identifier'] === 'https://mastodon.social/@onlyb') {
                $onlyBId = $profile['id'];
                break;
            }
        }
        $this->assertNotNull($onlyBId);

        $this->requestAsClientA('GET', '/api/feeds/profiles/' . $onlyBId . '.rss', ['headers' => ['Accept' => '*/*']]);
        $this->assertResponseStatusCodeSame(404);
    }
}
