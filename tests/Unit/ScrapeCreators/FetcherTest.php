<?php declare(strict_types=1);

namespace App\Tests\Unit\ScrapeCreators;

use App\FeedFetcher\FetchInfo;
use App\Model\Profile;
use App\NetworkFeedFetcher\Instagram\InstagramFeedFetcher;
use App\NetworkFeedFetcher\Instagram\InstagramScrapeCreatorsFeedFetcher;
use App\NetworkFeedFetcher\Threads\ThreadsScrapeCreatorsFeedFetcher;
use App\RssApp\RssAppInterface;
use App\ScrapeCreators\ScrapeCreatorsException;
use App\ScrapeCreators\ScrapeCreatorsInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FetcherTest extends TestCase
{
    public function testExactlyOneFetcherTakesAProfile(): void
    {
        $rssApp = new InstagramFeedFetcher(new NullLogger(), $this->createStub(RssAppInterface::class));
        $scrapeCreators = new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $this->createStub(ScrapeCreatorsInterface::class));

        $default = $this->makeProfile();
        $switched = $this->makeProfile()->setFetchProvider('scrapecreators');

        self::assertTrue($rssApp->supports($default));
        self::assertFalse($scrapeCreators->supports($default));
        self::assertFalse($rssApp->supports($switched));
        self::assertTrue($scrapeCreators->supports($switched));
    }

    public function testOtherNetworkIsNotSupported(): void
    {
        $fetcher = new ThreadsScrapeCreatorsFeedFetcher(new NullLogger(), $this->createStub(ScrapeCreatorsInterface::class));

        self::assertFalse($fetcher->supports($this->makeProfile()->setFetchProvider('scrapecreators')));
    }

    public function testFetchConvertsPostsAndSkipsBrokenOnes(): void
    {
        $api = $this->createMock(ScrapeCreatorsInterface::class);
        $api->expects(self::once())->method('getInstagramPosts')->with('edcriticalmass')
            ->willReturn([PostConverterTest::photoPost(), ['code' => 'ohneDatum']]);

        $profile = $this->makeProfile();
        $items = (new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $api))->fetch($profile, new FetchInfo());

        self::assertCount(1, $items);
        self::assertSame('https://www.instagram.com/p/DfnONvgDTop', $items[0]->getPermalink());
        self::assertNull($profile->getLastFetchFailureError());
    }

    public function testThreadsFetcherUsesTheThreadsEndpoint(): void
    {
        $api = $this->createMock(ScrapeCreatorsInterface::class);
        $api->expects(self::once())->method('getThreadsPosts')->with('edcriticalmass')
            ->willReturn([PostConverterTest::photoPost()]);
        $api->expects(self::never())->method('getInstagramPosts');

        $profile = (new Profile())->setId(7)->setNetwork('threads_profile')->setIdentifier('https://www.threads.com/@edcriticalmass');
        $items = (new ThreadsScrapeCreatorsFeedFetcher(new NullLogger(), $api))->fetch($profile, new FetchInfo());

        self::assertSame('https://threads.net/@edcriticalmass/post/DfnONvgDTop', $items[0]->getPermalink());
    }

    public function testApiErrorMarksProfileAsFailed(): void
    {
        $api = $this->createStub(ScrapeCreatorsInterface::class);
        $api->method('getInstagramPosts')->willThrowException(new ScrapeCreatorsException('HTTP 404: not_found Profile is restricted'));

        $profile = $this->makeProfile();
        $items = (new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $api))->fetch($profile, new FetchInfo());

        self::assertSame([], $items);
        self::assertNotNull($profile->getLastFetchFailureDateTime());
        self::assertStringContainsString('Profile is restricted', (string) $profile->getLastFetchFailureError());
        self::assertStringContainsString('edcriticalmass', (string) $profile->getLastFetchFailureError());
    }

    public function testIdentifierWithoutHandleFailsWithoutRequest(): void
    {
        $api = $this->createMock(ScrapeCreatorsInterface::class);
        $api->expects(self::never())->method('getInstagramPosts');

        $profile = $this->makeProfile()->setIdentifier('https://www.instagram.com/');
        (new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $api))->fetch($profile, new FetchInfo());

        self::assertStringContainsString('Kein Kontoname', (string) $profile->getLastFetchFailureError());
    }

    private function makeProfile(): Profile
    {
        return (new Profile())
            ->setId(42)
            ->setNetwork('instagram_profile')
            ->setIdentifier('https://www.instagram.com/edcriticalmass/');
    }
}
