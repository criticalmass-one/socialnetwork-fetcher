<?php declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ScheduledFetchCommand;
use App\Entity\Network;
use App\FeedFetcher\FeedFetcherInterface;
use App\FeedFetcher\FetchInfo;
use App\Repository\NetworkRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

class ScheduledFetchCommandTest extends TestCase
{
    private function createNetwork(string $identifier, ?string $cronExpression = '* * * * *'): Network
    {
        $network = new Network();
        $network->setIdentifier($identifier);
        $network->setName($identifier);
        $network->setIcon('fas fa-test');
        $network->setBackgroundColor('#000');
        $network->setTextColor('#fff');
        $network->setCronExpression($cronExpression);

        return $network;
    }

    /**
     * @param Network[] $networks
     * @param array<string[]> $fetchedNetworkLists receives the network list of every fetch() call
     */
    private function createCommand(array $networks, LockFactory $lockFactory, array &$fetchedNetworkLists): ScheduledFetchCommand
    {
        $networkRepository = $this->createStub(NetworkRepository::class);
        $networkRepository->method('findAll')->willReturn($networks);

        $feedFetcher = $this->createStub(FeedFetcherInterface::class);
        $feedFetcher->method('fetch')->willReturnCallback(
            function (FetchInfo $fetchInfo) use ($feedFetcher, &$fetchedNetworkLists): FeedFetcherInterface {
                $fetchedNetworkLists[] = $fetchInfo->getNetworkList();

                return $feedFetcher;
            }
        );
        $feedFetcher->method('persist')->willReturn($feedFetcher);

        return new ScheduledFetchCommand($networkRepository, $feedFetcher, $lockFactory);
    }

    public function testFetchesDueNetworks(): void
    {
        $fetched = [];
        $command = $this->createCommand([
            $this->createNetwork('instagram_profile'),
            $this->createNetwork('mastodon', null),
        ], new LockFactory(new InMemoryStore()), $fetched);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([['instagram_profile']], $fetched);
    }

    public function testSkipsNetworkWhosePreviousRunIsStillActive(): void
    {
        $lockFactory = new LockFactory(new InMemoryStore());
        $runningLock = $lockFactory->createLock('fetch-scheduled-facebook_page', null);
        $runningLock->acquire();

        $fetched = [];
        $command = $this->createCommand([
            $this->createNetwork('facebook_page'),
            $this->createNetwork('threads_profile'),
        ], $lockFactory, $fetched);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([['threads_profile']], $fetched);
        $this->assertStringContainsString('facebook_page: vorheriger Lauf noch aktiv', $tester->getDisplay());
    }

    public function testDoesNotFetchWhenAllDueNetworksAreLocked(): void
    {
        $lockFactory = new LockFactory(new InMemoryStore());
        $runningLock = $lockFactory->createLock('fetch-scheduled-facebook_page', null);
        $runningLock->acquire();

        $fetched = [];
        $command = $this->createCommand([$this->createNetwork('facebook_page')], $lockFactory, $fetched);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame([], $fetched);
    }

    public function testReleasesLocksAfterRun(): void
    {
        $lockFactory = new LockFactory(new InMemoryStore());

        $fetched = [];
        $command = $this->createCommand([$this->createNetwork('facebook_page')], $lockFactory, $fetched);

        (new CommandTester($command))->execute([]);

        $this->assertTrue($lockFactory->createLock('fetch-scheduled-facebook_page', null)->acquire());
    }
}
