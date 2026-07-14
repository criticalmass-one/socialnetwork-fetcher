<?php declare(strict_types=1);

namespace App\Tests\Unit\RssApp;

use App\RssApp\RssApp;
use App\RssApp\RssAppException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RssAppRetryTest extends TestCase
{
    public function testCreateFeedRetriesAfterRateLimitThenSucceeds(): void
    {
        $client = new MockHttpClient([
            $this->rateLimited(retryAfter: '0'),
            new MockResponse(json_encode(['id' => 'feed-123', 'source_url' => 'https://example.com']), ['http_code' => 200]),
        ]);

        $rssApp = new RssApp($client, 'key', 'secret');
        $feed = $rssApp->createFeed('https://example.com');

        self::assertSame('feed-123', $feed['id']);
        self::assertSame(2, $client->getRequestsCount(), 'one retry after the 429');
    }

    public function testCreateFeedGivesUpAfterMaxAttempts(): void
    {
        $client = new MockHttpClient(array_fill(0, 6, $this->rateLimited(retryAfter: '0')));

        $rssApp = new RssApp($client, 'key', 'secret');

        try {
            $rssApp->createFeed('https://example.com');
            self::fail('expected RssAppException');
        } catch (RssAppException $e) {
            self::assertStringContainsString('429', $e->getMessage());
        }

        // 1 initial + 3 retries = 4 attempts, then it gives up.
        self::assertSame(4, $client->getRequestsCount());
    }

    public function testDoesNotBlockWhenRetryAfterIsTooLong(): void
    {
        $client = new MockHttpClient([
            $this->rateLimited(retryAfter: '3600'),
            new MockResponse(json_encode(['id' => 'never-reached']), ['http_code' => 200]),
        ]);

        $rssApp = new RssApp($client, 'key', 'secret');

        $this->expectException(RssAppException::class);
        try {
            $rssApp->createFeed('https://example.com');
        } finally {
            // Surfaces the 429 immediately instead of sleeping an hour / retrying.
            self::assertSame(1, $client->getRequestsCount());
        }
    }

    private function rateLimited(string $retryAfter): MockResponse
    {
        return new MockResponse(
            json_encode(['message' => 'API rate limit exceeded']),
            ['http_code' => 429, 'response_headers' => ['retry-after' => $retryAfter]],
        );
    }
}
