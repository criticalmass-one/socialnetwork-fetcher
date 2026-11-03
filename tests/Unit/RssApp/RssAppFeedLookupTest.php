<?php declare(strict_types=1);

namespace App\Tests\Unit\RssApp;

use App\RssApp\RssApp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RssAppFeedLookupTest extends TestCase
{
    public function testPagesThroughFeedListOnlyOnceForSeveralLookups(): void
    {
        $client = new MockHttpClient([
            // RSS.app pages by 100; a total above that forces a second page.
            $this->feedPage([['id' => 'feed-1', 'source_url' => 'https://www.facebook.com/a']], total: 101),
            $this->feedPage([['id' => 'feed-2', 'source_url' => 'https://www.facebook.com/b']], total: 101),
        ]);

        $rssApp = new RssApp($client, 'key', 'secret');

        self::assertSame('feed-1', $rssApp->findRssAppFeedIdBySourceUrl('https://www.facebook.com/a'));
        self::assertSame('feed-2', $rssApp->findRssAppFeedIdBySourceUrl('https://www.facebook.com/b'));
        self::assertNull($rssApp->findRssAppFeedIdBySourceUrl('https://www.facebook.com/missing'));
        self::assertNull($rssApp->findRssAppFeedIdBySourceUrl('https://www.facebook.com/missing-too'));

        self::assertSame(2, $client->getRequestsCount(), 'two list pages, loaded once');
    }

    public function testReloadsFeedListAfterTtl(): void
    {
        $client = new MockHttpClient([
            $this->feedPage([], total: 0),
            $this->feedPage([['id' => 'feed-1', 'source_url' => 'https://www.facebook.com/a']], total: 1),
        ]);

        $rssApp = new RssApp($client, 'key', 'secret', feedListTtlSeconds: 0);

        self::assertNull($rssApp->findRssAppFeedIdBySourceUrl('https://www.facebook.com/a'));
        self::assertSame('feed-1', $rssApp->findRssAppFeedIdBySourceUrl('https://www.facebook.com/a'));
        self::assertSame(2, $client->getRequestsCount());
    }

    public function testFirstFeedWinsForDuplicateSourceUrl(): void
    {
        $client = new MockHttpClient([
            $this->feedPage([
                ['id' => 'feed-old', 'source_url' => 'https://www.facebook.com/a'],
                ['id' => 'feed-new', 'source_url' => 'https://www.facebook.com/a'],
            ], total: 2),
        ]);

        $rssApp = new RssApp($client, 'key', 'secret');

        self::assertSame('feed-old', $rssApp->findRssAppFeedIdBySourceUrl('https://www.facebook.com/a'));
    }

    public function testCreatedFeedIsFoundWithoutReloading(): void
    {
        $client = new MockHttpClient([
            $this->feedPage([], total: 0),
            new MockResponse(json_encode(['id' => 'feed-new', 'source_url' => 'https://www.facebook.com/a']), ['http_code' => 200]),
        ]);

        $rssApp = new RssApp($client, 'key', 'secret');

        self::assertNull($rssApp->findRssAppFeedIdBySourceUrl('https://www.facebook.com/a'));
        $rssApp->createFeed('https://www.facebook.com/a');

        self::assertSame('feed-new', $rssApp->findRssAppFeedIdBySourceUrl('https://www.facebook.com/a'));
        self::assertSame(2, $client->getRequestsCount());
    }

    public function testDeletedFeedIsNoLongerFound(): void
    {
        $client = new MockHttpClient([
            $this->feedPage([['id' => 'feed-1', 'source_url' => 'https://www.facebook.com/a']], total: 1),
            new MockResponse('', ['http_code' => 204]),
        ]);

        $rssApp = new RssApp($client, 'key', 'secret');

        self::assertSame('feed-1', $rssApp->findRssAppFeedIdBySourceUrl('https://www.facebook.com/a'));
        $rssApp->deleteFeed('feed-1');

        self::assertNull($rssApp->findRssAppFeedIdBySourceUrl('https://www.facebook.com/a'));
        self::assertSame(2, $client->getRequestsCount());
    }

    /**
     * @param list<array<string, string>> $feeds
     */
    private function feedPage(array $feeds, int $total): MockResponse
    {
        return new MockResponse(json_encode(['data' => $feeds, 'total' => $total]), ['http_code' => 200]);
    }
}
