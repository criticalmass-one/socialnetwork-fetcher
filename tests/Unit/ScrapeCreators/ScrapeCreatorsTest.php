<?php declare(strict_types=1);

namespace App\Tests\Unit\ScrapeCreators;

use App\ScrapeCreators\ScrapeCreators;
use App\ScrapeCreators\ScrapeCreatorsException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ScrapeCreatorsTest extends TestCase
{
    public function testInstagramRequestSendsKeyAndHandle(): void
    {
        $seen = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['method' => $method, 'url' => $url, 'headers' => $options['headers']];

            return new MockResponse(json_encode(['success' => true, 'items' => [['code' => 'abc'], 'kaputt']]));
        });

        $posts = (new ScrapeCreators($client, 'geheim'))->getInstagramPosts('cm_hh');

        self::assertSame([['code' => 'abc']], $posts);
        self::assertSame('GET', $seen['method']);
        self::assertSame('https://api.scrapecreators.com/v2/instagram/user/posts?handle=cm_hh', $seen['url']);
        self::assertContains('x-api-key: geheim', $seen['headers']);
    }

    public function testInstagramPageCarriesTheCursorAndSendsItBack(): void
    {
        $urls = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return new MockResponse(json_encode(count($urls) === 1
                ? ['items' => [['code' => 'a']], 'next_max_id' => '123_456', 'more_available' => true]
                : ['items' => [['code' => 'b']], 'next_max_id' => '789_456', 'more_available' => false]));
        });
        $api = new ScrapeCreators($client, 'geheim');

        $first = $api->getInstagramPostsPage('cm_hh');
        self::assertSame('123_456', $first->nextCursor);
        self::assertTrue($first->hasMore());

        $second = $api->getInstagramPostsPage('cm_hh', $first->nextCursor);
        self::assertSame([['code' => 'b']], $second->posts);
        self::assertFalse($second->hasMore(), 'more_available=false ends the paging');
        self::assertSame('https://api.scrapecreators.com/v2/instagram/user/posts?handle=cm_hh&next_max_id=123_456', $urls[1]);
    }

    public function testThreadsReadsThePostsList(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['success' => true, 'posts' => [['code' => 'abc']]])));

        self::assertSame([['code' => 'abc']], (new ScrapeCreators($client, 'geheim'))->getThreadsPosts('cm_hh'));
    }

    public function testPrivateProfileIsAnErrorDespiteSuccessTrue(): void
    {
        // Observed: HTTP 404 with "success": true for a private profile.
        $client = new MockHttpClient(new MockResponse(
            json_encode(['success' => true, 'error' => 'not_found', 'message' => 'Profile is restricted']),
            ['http_code' => 404],
        ));

        $this->expectException(ScrapeCreatorsException::class);
        $this->expectExceptionMessage('HTTP 404: not_found Profile is restricted');

        (new ScrapeCreators($client, 'geheim'))->getInstagramPosts('privat');
    }

    public function testServerErrorIsReported(): void
    {
        $client = new MockHttpClient(new MockResponse('Bad Gateway', ['http_code' => 502]));

        $this->expectException(ScrapeCreatorsException::class);
        $this->expectExceptionMessage('HTTP 502');

        (new ScrapeCreators($client, 'geheim'))->getInstagramPosts('cm_hh');
    }

    public function testWithoutKeyNoRequestIsSent(): void
    {
        $client = new MockHttpClient(static function (): never {
            self::fail('no request expected');
        });
        $scrapeCreators = new ScrapeCreators($client, '');

        self::assertFalse($scrapeCreators->isConfigured());

        $this->expectException(ScrapeCreatorsException::class);
        $scrapeCreators->getInstagramPosts('cm_hh');
    }
}
