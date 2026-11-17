<?php declare(strict_types=1);

namespace App\Tests\Unit\ScrapeCreators;

use App\FeedFetcher\FetchInfo;
use App\Model\Profile;
use App\NetworkFeedFetcher\Instagram\InstagramFeedFetcher;
use App\NetworkFeedFetcher\Instagram\InstagramScrapeCreatorsFeedFetcher;
use App\NetworkFeedFetcher\Threads\ThreadsScrapeCreatorsFeedFetcher;
use App\RssApp\RssAppInterface;
use App\ScrapeCreators\PostPage;
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

    public function testProfileIsOnlyTakenAgainAfterTheMinimumInterval(): void
    {
        $fetcher = new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $this->createStub(ScrapeCreatorsInterface::class), 24);
        $switched = fn (): Profile => $this->makeProfile()->setFetchProvider('scrapecreators');

        self::assertTrue($fetcher->supports($switched()), 'never fetched');
        self::assertFalse($fetcher->supports($switched()->setLastFetchSuccessDateTime(new \DateTimeImmutable('-2 hours'))));
        self::assertTrue($fetcher->supports($switched()->setLastFetchSuccessDateTime(new \DateTimeImmutable('-25 hours'))));
        // A failed attempt counts as well, otherwise a dead account is asked every run.
        self::assertFalse($fetcher->supports(
            $switched()
                ->setLastFetchSuccessDateTime(new \DateTimeImmutable('-30 days'))
                ->setLastFetchFailureDateTime(new \DateTimeImmutable('-1 hour'))
        ));
    }

    public function testIntervalOfZeroDisablesTheThrottle(): void
    {
        $fetcher = new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $this->createStub(ScrapeCreatorsInterface::class), 0);

        self::assertTrue($fetcher->supports(
            $this->makeProfile()->setFetchProvider('scrapecreators')->setLastFetchSuccessDateTime(new \DateTimeImmutable('-1 minute'))
        ));
    }

    public function testFetchConvertsPostsAndSkipsBrokenOnes(): void
    {
        $api = $this->createMock(ScrapeCreatorsInterface::class);
        $api->expects(self::once())->method('getInstagramPostsPage')->with('edcriticalmass')
            ->willReturn(new PostPage([PostConverterTest::photoPost(), ['code' => 'ohneDatum']], 'weiter'));

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
        $api->expects(self::never())->method('getInstagramPostsPage');

        $profile = (new Profile())->setId(7)->setNetwork('threads_profile')->setIdentifier('https://www.threads.com/@edcriticalmass');
        $items = (new ThreadsScrapeCreatorsFeedFetcher(new NullLogger(), $api))->fetch($profile, new FetchInfo());

        self::assertSame('https://threads.net/@edcriticalmass/post/DfnONvgDTop', $items[0]->getPermalink());
    }

    public function testApiErrorMarksProfileAsFailed(): void
    {
        $api = $this->createStub(ScrapeCreatorsInterface::class);
        $api->method('getInstagramPostsPage')->willThrowException(new ScrapeCreatorsException('HTTP 404: not_found Profile is restricted'));

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
        $api->expects(self::never())->method('getInstagramPostsPage');

        $profile = $this->makeProfile()->setIdentifier('https://www.instagram.com/');
        (new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $api))->fetch($profile, new FetchInfo());

        self::assertStringContainsString('Kein Kontoname', (string) $profile->getLastFetchFailureError());
    }

    public function testTurnsPagesWhileAPageHoldsOnlyPostsNewerThanTheLastFetch(): void
    {
        $lastFetch = new \DateTimeImmutable('-3 days');
        $new = fn (string $code): array => $this->post($code, '-2 hours');
        $old = fn (string $code): array => $this->post($code, '-10 days');

        // Page 1: three old pinned posts, then only new ones -> go on.
        // Page 2: only new ones -> go on. Page 3: reaches posts older than the last fetch -> stop.
        $pages = [
            '' => new PostPage([$old('pin1'), $old('pin2'), $old('pin3'), $new('a1'), $new('a2')], 'c2'),
            'c2' => new PostPage([$new('b1'), $new('b2')], 'c3'),
            'c3' => new PostPage([$new('c1'), $old('c2')], 'c4'),
        ];
        $api = $this->createMock(ScrapeCreatorsInterface::class);
        $api->expects(self::exactly(3))->method('getInstagramPostsPage')
            ->willReturnCallback(static fn (string $handle, ?string $cursor = null): PostPage => $pages[$cursor ?? '']);

        $items = (new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $api))
            ->fetch($this->makeProfile()->setLastFetchSuccessDateTime($lastFetch), new FetchInfo());

        self::assertCount(9, $items);
    }

    public function testStopsAtTheLastPage(): void
    {
        $api = $this->createMock(ScrapeCreatorsInterface::class);
        $api->expects(self::once())->method('getInstagramPostsPage')
            ->willReturn(new PostPage([$this->post('a', '-1 hour'), $this->post('b', '-1 hour'), $this->post('c', '-1 hour'), $this->post('d', '-1 hour')]));

        (new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $api))
            ->fetch($this->makeProfile()->setLastFetchSuccessDateTime(new \DateTimeImmutable('-3 days')), new FetchInfo());
    }

    public function testNeverFetchedProfileGetsOnePageOnly(): void
    {
        $api = $this->createMock(ScrapeCreatorsInterface::class);
        $api->expects(self::once())->method('getInstagramPostsPage')
            ->willReturn(new PostPage(array_map(fn (int $i): array => $this->post('p' . $i, '-1 hour'), range(1, 12)), 'weiter'));

        (new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $api))->fetch($this->makeProfile(), new FetchInfo());
    }

    public function testPageLimitCapsTheCredits(): void
    {
        $api = $this->createMock(ScrapeCreatorsInterface::class);
        $api->expects(self::exactly(InstagramScrapeCreatorsFeedFetcher::MAX_PAGES))->method('getInstagramPostsPage')
            ->willReturnCallback(fn (): PostPage => new PostPage(
                array_map(fn (int $i): array => $this->post(uniqid('p'), '-1 hour'), range(1, 12)),
                'weiter',
            ));

        (new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $api))
            ->fetch($this->makeProfile()->setLastFetchSuccessDateTime(new \DateTimeImmutable('-1 day')), new FetchInfo());
    }

    public function testFailingFurtherPageKeepsTheFirstOne(): void
    {
        $calls = 0;
        $api = $this->createStub(ScrapeCreatorsInterface::class);
        $api->method('getInstagramPostsPage')->willReturnCallback(function () use (&$calls): PostPage {
            if (++$calls > 1) {
                throw new ScrapeCreatorsException('HTTP 500');
            }

            return new PostPage(array_map(fn (int $i): array => $this->post('p' . $i, '-1 hour'), range(1, 12)), 'weiter');
        });

        $profile = $this->makeProfile()->setLastFetchSuccessDateTime(new \DateTimeImmutable('-3 days'));
        $items = (new InstagramScrapeCreatorsFeedFetcher(new NullLogger(), $api))->fetch($profile, new FetchInfo());

        self::assertCount(12, $items);
        self::assertNull($profile->getLastFetchFailureError());
    }

    private function post(string $code, string $age): array
    {
        return array_merge(PostConverterTest::photoPost(), [
            'code' => $code,
            'pk' => $code,
            'id' => $code,
            'taken_at' => (new \DateTimeImmutable($age))->getTimestamp(),
        ]);
    }

    private function makeProfile(): Profile
    {
        return (new Profile())
            ->setId(42)
            ->setNetwork('instagram_profile')
            ->setIdentifier('https://www.instagram.com/edcriticalmass/');
    }
}
