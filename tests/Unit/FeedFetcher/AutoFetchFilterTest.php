<?php declare(strict_types=1);

namespace App\Tests\Unit\FeedFetcher;

use App\FeedFetcher\AbstractFeedFetcher;
use App\FeedFetcher\FeedFetcherInterface;
use App\FeedFetcher\FetchInfo;
use App\FeedItemPersister\FeedItemPersisterInterface;
use App\Model\Profile;
use App\ProfileFetcher\ProfileFetcherInterface;
use App\ProfilePersister\ProfilePersisterInterface;
use App\SourceFetcher\SourceFetcher;
use PHPUnit\Framework\TestCase;

class AutoFetchFilterTest extends TestCase
{
    public function testProfilesWithAutoFetchSwitchedOffAreNotFetched(): void
    {
        $active = (new Profile())->setId(1)->setIdentifier('https://mastodon.social/@aktiv');
        $switchedOff = (new Profile())->setId(2)->setIdentifier('https://toot.example/@aus')->setAutoFetch(false);

        $profileFetcher = $this->createStub(ProfileFetcherInterface::class);
        $profileFetcher->method('fetchByFetchInfo')->willReturn([$active, $switchedOff]);

        $feedFetcher = new class(
            $this->createStub(FeedItemPersisterInterface::class),
            $profileFetcher,
            $this->createStub(ProfilePersisterInterface::class),
            $this->createStub(SourceFetcher::class),
        ) extends AbstractFeedFetcher {
            public function fetch(FetchInfo $fetchInfo, callable $callback): FeedFetcherInterface
            {
                return $this;
            }

            public function persist(): FeedFetcherInterface
            {
                return $this;
            }

            public function profilesFor(FetchInfo $fetchInfo): array
            {
                return $this->getProfiles($fetchInfo);
            }
        };

        self::assertSame([$active], $feedFetcher->profilesFor(new FetchInfo()));
    }
}
