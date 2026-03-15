<?php declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SyncRssAppFeedIdsCommand;
use App\Entity\Network;
use App\Entity\Profile;
use App\Repository\ProfileRepository;
use App\RssApp\RssAppInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SyncRssAppFeedIdsCommandTest extends TestCase
{
    private function createProfile(int $id, string $networkIdentifier, string $identifier, ?string $feedId = null): Profile
    {
        $network = new Network();
        $network->setIdentifier($networkIdentifier);
        $network->setName($networkIdentifier);
        $network->setIcon('fas fa-test');
        $network->setBackgroundColor('#000');
        $network->setTextColor('#fff');
        $network->setProfileUrlPattern('#.*#');

        $profile = new Profile();
        $profile->setId($id);
        $profile->setIdentifier($identifier);
        $profile->setNetwork($network);

        if ($feedId !== null) {
            $profile->setAdditionalData(['rss_feed_id' => $feedId]);
        }

        return $profile;
    }

    public function testFindsAndStoresFeedId(): void
    {
        $profile = $this->createProfile(1, 'instagram_profile', 'https://www.instagram.com/testuser');

        $rssApp = $this->createMock(RssAppInterface::class);
        $rssApp->method('listFeeds')->willReturn([
            ['id' => 'feed-abc', 'source_url' => 'https://www.instagram.com/testuser/'],
        ]);

        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findAll')->willReturn([$profile]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $command = new SyncRssAppFeedIdsCommand($rssApp, $profileRepo, $em);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('1 aktualisiert', $tester->getDisplay());
        $this->assertSame('feed-abc', $profile->getAdditionalData()['rss_feed_id']);
    }

    public function testSkipsProfileWithExistingFeedId(): void
    {
        $profile = $this->createProfile(1, 'instagram_profile', 'https://www.instagram.com/testuser', 'existing-feed');

        $rssApp = $this->createMock(RssAppInterface::class);
        $rssApp->method('listFeeds')->willReturn([
            ['id' => 'feed-abc', 'source_url' => 'https://www.instagram.com/testuser/'],
        ]);

        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findAll')->willReturn([$profile]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $command = new SyncRssAppFeedIdsCommand($rssApp, $profileRepo, $em);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('0 aktualisiert', $tester->getDisplay());
        $this->assertStringContainsString('1 bereits verknüpft', $tester->getDisplay());
    }

    public function testForceUpdatesExistingFeedId(): void
    {
        $profile = $this->createProfile(1, 'instagram_profile', 'https://www.instagram.com/testuser', 'old-feed');

        $rssApp = $this->createMock(RssAppInterface::class);
        $rssApp->method('listFeeds')->willReturn([
            ['id' => 'new-feed', 'source_url' => 'https://www.instagram.com/testuser/'],
        ]);

        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findAll')->willReturn([$profile]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $command = new SyncRssAppFeedIdsCommand($rssApp, $profileRepo, $em);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame('new-feed', $profile->getAdditionalData()['rss_feed_id']);
    }

    public function testDryRunDoesNotPersist(): void
    {
        $profile = $this->createProfile(1, 'facebook_profile', 'https://www.facebook.com/profile.php?id=12345');

        $rssApp = $this->createMock(RssAppInterface::class);
        $rssApp->method('listFeeds')->willReturn([
            ['id' => 'feed-xyz', 'source_url' => 'https://www.facebook.com/profile.php?id=12345'],
        ]);

        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findAll')->willReturn([$profile]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $command = new SyncRssAppFeedIdsCommand($rssApp, $profileRepo, $em);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[Dry-Run]', $tester->getDisplay());
        $this->assertNull($profile->getAdditionalData());
    }

    public function testSkipsNonRssAppNetworks(): void
    {
        $profile = $this->createProfile(1, 'mastodon', 'https://mastodon.social/@user');

        $rssApp = $this->createMock(RssAppInterface::class);
        $rssApp->method('listFeeds')->willReturn([]);

        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findAll')->willReturn([$profile]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $command = new SyncRssAppFeedIdsCommand($rssApp, $profileRepo, $em);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('0 aktualisiert', $tester->getDisplay());
    }

    public function testNetworkFilterRejectsInvalidNetwork(): void
    {
        $rssApp = $this->createMock(RssAppInterface::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $command = new SyncRssAppFeedIdsCommand($rssApp, $profileRepo, $em);

        $tester = new CommandTester($command);
        $tester->execute(['--network' => 'mastodon']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testNetworkFilterOnlyProcessesSelectedNetwork(): void
    {
        $instagram = $this->createProfile(1, 'instagram_profile', 'https://www.instagram.com/testuser');
        $facebook = $this->createProfile(2, 'facebook_profile', 'https://www.facebook.com/profile.php?id=12345');

        $rssApp = $this->createMock(RssAppInterface::class);
        $rssApp->method('listFeeds')->willReturn([
            ['id' => 'ig-feed', 'source_url' => 'https://www.instagram.com/testuser/'],
            ['id' => 'fb-feed', 'source_url' => 'https://www.facebook.com/profile.php?id=12345'],
        ]);

        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findAll')->willReturn([$instagram, $facebook]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $command = new SyncRssAppFeedIdsCommand($rssApp, $profileRepo, $em);

        $tester = new CommandTester($command);
        $tester->execute(['--network' => 'instagram_profile']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame('ig-feed', $instagram->getAdditionalData()['rss_feed_id']);
        $this->assertNull($facebook->getAdditionalData());
    }

    public function testProfileNotFoundInRssApp(): void
    {
        $profile = $this->createProfile(1, 'instagram_profile', 'https://www.instagram.com/unknownuser');

        $rssApp = $this->createMock(RssAppInterface::class);
        $rssApp->method('listFeeds')->willReturn([]);

        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findAll')->willReturn([$profile]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $command = new SyncRssAppFeedIdsCommand($rssApp, $profileRepo, $em);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('1 nicht gefunden', $display);
    }
}
