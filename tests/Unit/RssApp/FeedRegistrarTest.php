<?php declare(strict_types=1);

namespace App\Tests\Unit\RssApp;

use App\Entity\Network;
use App\Entity\Profile;
use App\FeedFetcher\FeedFetcherInterface;
use App\FeedItemPersister\FeedItemPersisterInterface;
use App\Model\Item as ItemModel;
use App\Model\Profile as ModelProfile;
use App\NetworkFeedFetcher\NetworkFeedFetcherInterface;
use App\RssApp\FeedRegistrar;
use App\RssApp\RssAppInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FeedRegistrarTest extends TestCase
{
    private RssAppInterface $rssApp;
    private FeedFetcherInterface $feedFetcher;
    private FeedItemPersisterInterface $feedItemPersister;
    private FeedRegistrar $registrar;

    protected function setUp(): void
    {
        $this->rssApp = $this->createMock(RssAppInterface::class);
        $this->feedFetcher = $this->createMock(FeedFetcherInterface::class);
        $this->feedItemPersister = $this->createMock(FeedItemPersisterInterface::class);

        $this->registrar = new FeedRegistrar(
            $this->rssApp,
            new NullLogger(),
            $this->feedFetcher,
            $this->feedItemPersister,
        );
    }

    public function testNotApplicableWhenNetworkNotInWhitelist(): void
    {
        $profile = $this->makeProfile('mastodon', 'https://mastodon.social/@foo');

        $this->rssApp->expects($this->never())->method('findRssAppFeedIdBySourceUrl');
        $this->rssApp->expects($this->never())->method('createFeed');

        $result = $this->registrar->registerIfNeeded($profile);

        $this->assertFalse($result->registered);
        $this->assertFalse($result->linkedToExistingFeed);
        $this->assertSame(0, $result->importedItems);
    }

    public function testNotApplicableWhenFeedIdAlreadySet(): void
    {
        $profile = $this->makeProfile('instagram_profile', 'https://instagram.com/foo');
        $profile->setRssAppFeedId('preset-id');

        $this->rssApp->expects($this->never())->method('findRssAppFeedIdBySourceUrl');
        $this->rssApp->expects($this->never())->method('createFeed');

        $result = $this->registrar->registerIfNeeded($profile);

        $this->assertFalse($result->registered);
        $this->assertSame('preset-id', $profile->getRssAppFeedId());
    }

    public function testCreatesNewFeedWhenNoneFoundAtRssApp(): void
    {
        $profile = $this->makeProfile('instagram_profile', 'https://instagram.com/foo', 42);

        $this->rssApp->expects($this->once())
            ->method('findRssAppFeedIdBySourceUrl')
            ->with('https://instagram.com/foo')
            ->willReturn(null);

        $this->rssApp->expects($this->once())
            ->method('createFeed')
            ->with('https://instagram.com/foo')
            ->willReturn(['id' => 'new-feed-123']);

        $this->feedItemPersister->expects($this->never())->method('flush');

        $result = $this->registrar->registerIfNeeded($profile);

        $this->assertTrue($result->registered);
        $this->assertFalse($result->linkedToExistingFeed);
        $this->assertSame(0, $result->importedItems);
        $this->assertSame('new-feed-123', $profile->getRssAppFeedId());
    }

    public function testLinksToExistingFeedAndImportsInitialItems(): void
    {
        $profile = $this->makeProfile('instagram_profile', 'https://instagram.com/foo', 42);

        $this->rssApp->expects($this->once())
            ->method('findRssAppFeedIdBySourceUrl')
            ->with('https://instagram.com/foo')
            ->willReturn('existing-feed-99');

        $this->rssApp->expects($this->never())->method('createFeed');

        $items = [new ItemModel(), new ItemModel(), new ItemModel()];

        $networkFetcher = $this->createMock(NetworkFeedFetcherInterface::class);
        $networkFetcher->method('supports')->willReturn(true);
        $networkFetcher->expects($this->once())
            ->method('fetch')
            ->willReturnCallback(function (ModelProfile $modelProfile, $fetchInfo) use ($items): array {
                $this->assertSame(42, $modelProfile->getId());
                $this->assertSame('instagram_profile', $modelProfile->getNetwork());
                $this->assertSame(FeedRegistrar::INITIAL_IMPORT_COUNT, $fetchInfo->getCount());
                return $items;
            });

        $this->feedFetcher->method('getNetworkFetcherList')->willReturn([$networkFetcher]);

        $this->feedItemPersister->expects($this->once())
            ->method('persistFeedItemList')
            ->with($items)
            ->willReturnSelf();
        $this->feedItemPersister->expects($this->once())->method('flush')->willReturnSelf();

        $result = $this->registrar->registerIfNeeded($profile);

        $this->assertTrue($result->registered);
        $this->assertTrue($result->linkedToExistingFeed);
        $this->assertSame(3, $result->importedItems);
        $this->assertSame('existing-feed-99', $profile->getRssAppFeedId());
    }

    public function testReturnsNotApplicableWhenRssAppLookupThrows(): void
    {
        $profile = $this->makeProfile('instagram_profile', 'https://instagram.com/foo', 42);

        $this->rssApp->method('findRssAppFeedIdBySourceUrl')
            ->willThrowException(new \RuntimeException('boom'));

        $result = $this->registrar->registerIfNeeded($profile);

        $this->assertFalse($result->registered);
        $this->assertNull($profile->getRssAppFeedId());
    }

    public function testNotApplicableWhenIdentifierIsNull(): void
    {
        $network = new Network();
        $network->setIdentifier('instagram_profile');

        $profile = new Profile();
        $profile->setNetwork($network);

        $this->rssApp->expects($this->never())->method('findRssAppFeedIdBySourceUrl');

        $result = $this->registrar->registerIfNeeded($profile);

        $this->assertFalse($result->registered);
    }

    public function testLinkExistingFeedAndImportSetsFeedIdAndImports(): void
    {
        $profile = $this->makeProfile('instagram_profile', 'https://instagram.com/foo', 99);

        $items = [new ItemModel(), new ItemModel()];

        $networkFetcher = $this->createMock(NetworkFeedFetcherInterface::class);
        $networkFetcher->method('supports')->willReturn(true);
        $networkFetcher->expects($this->once())
            ->method('fetch')
            ->willReturnCallback(function (ModelProfile $modelProfile, $fetchInfo) use ($items): array {
                $this->assertSame(99, $modelProfile->getId());
                $this->assertSame(FeedRegistrar::INITIAL_IMPORT_COUNT, $fetchInfo->getCount());
                return $items;
            });

        $this->feedFetcher->method('getNetworkFetcherList')->willReturn([$networkFetcher]);

        $this->feedItemPersister->expects($this->once())
            ->method('persistFeedItemList')
            ->with($items)
            ->willReturnSelf();
        $this->feedItemPersister->expects($this->once())->method('flush')->willReturnSelf();

        $this->rssApp->expects($this->never())->method('findRssAppFeedIdBySourceUrl');
        $this->rssApp->expects($this->never())->method('createFeed');

        $imported = $this->registrar->linkExistingFeedAndImport($profile, 'adopted-feed-42');

        $this->assertSame(2, $imported);
        $this->assertSame('adopted-feed-42', $profile->getRssAppFeedId());
    }

    private function makeProfile(string $networkIdentifier, string $url, ?int $id = null): Profile
    {
        $network = new Network();
        $network->setIdentifier($networkIdentifier);

        $profile = new Profile();
        $profile->setNetwork($network);
        $profile->setIdentifier($url);

        if ($id !== null) {
            $reflection = new \ReflectionProperty(Profile::class, 'id');
            $reflection->setValue($profile, $id);
        }

        return $profile;
    }
}
