<?php declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\RssAppStaleFeedsCommand;
use App\Entity\Network;
use App\Entity\Profile;
use App\Repository\ItemRepository;
use App\Repository\ProfileRepository;
use App\RssApp\StalenessDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RssAppStaleFeedsCommandTest extends TestCase
{
    private const DAY = 86400;

    /** @var array<int, list<int>> profile id → timestamps */
    private array $timestamps = [];

    public function testListsRecentlySilentProfileOnly(): void
    {
        $tester = $this->runCommand([
            // daily poster, silent for 10 days → listed
            $this->profile(1, 'instagram_profile', 'https://www.instagram.com/torsten.franz.lg/', 10, 1),
            // daily poster, posted yesterday → not listed
            $this->profile(2, 'instagram_profile', 'https://www.instagram.com/active/', 1, 1),
            // silent for 400 days → hidden without --all
            $this->profile(3, 'facebook_page', 'https://www.facebook.com/dead/', 400, 7),
        ]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('torsten.franz.lg', $display);
        self::assertStringNotContainsString('/active/', $display);
        self::assertStringNotContainsString('/dead/', $display);
        self::assertStringContainsString('1 Profil(e)', $display);
    }

    public function testAllIncludesLongDeadProfiles(): void
    {
        $tester = $this->runCommand([
            $this->profile(3, 'facebook_page', 'https://www.facebook.com/dead/', 400, 7),
        ], ['--all' => true]);

        self::assertStringContainsString('/dead/', $tester->getDisplay());
    }

    public function testNetworkFilter(): void
    {
        $tester = $this->runCommand([
            $this->profile(1, 'instagram_profile', 'https://www.instagram.com/quiet/', 10, 1),
            $this->profile(4, 'facebook_page', 'https://www.facebook.com/quiet/', 10, 1),
        ], ['--network' => ['facebook_page']]);

        self::assertStringContainsString('facebook.com/quiet', $tester->getDisplay());
        self::assertStringNotContainsString('instagram.com/quiet', $tester->getDisplay());
    }

    public function testSkipsProfilesWithoutAutoFetch(): void
    {
        $profile = $this->profile(1, 'instagram_profile', 'https://www.instagram.com/paused/', 10, 1);
        $profile->setAutoFetch(false);

        $tester = $this->runCommand([$profile]);

        self::assertStringContainsString('Keine auffällig stillen', $tester->getDisplay());
    }

    public function testFailOnStaleSetsExitCode(): void
    {
        $tester = $this->runCommand([
            $this->profile(1, 'instagram_profile', 'https://www.instagram.com/quiet/', 10, 1),
        ], ['--fail-on-stale' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    /**
     * @param list<Profile> $profiles
     * @param array<string, mixed> $options
     */
    private function runCommand(array $profiles, array $options = []): CommandTester
    {
        $profileRepository = $this->createStub(ProfileRepository::class);
        $profileRepository->method('findWithRssAppFeedId')->willReturn($profiles);

        $itemRepository = $this->createStub(ItemRepository::class);
        $itemRepository->method('findRecentTimestampsForProfile')
            ->willReturnCallback(fn (Profile $profile): array => $this->timestamps[$profile->getId()] ?? []);

        $tester = new CommandTester(new RssAppStaleFeedsCommand($profileRepository, $itemRepository, new StalenessDetector()));
        $tester->execute($options);

        return $tester;
    }

    private function profile(int $id, string $networkIdentifier, string $identifier, int $newestDaysAgo, int $gapDays): Profile
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
        $profile->setRssAppFeedId('feed-' . $id);

        $newest = time() - $newestDaysAgo * self::DAY;
        $this->timestamps[$id] = array_map(static fn (int $i): int => $newest - $i * $gapDays * self::DAY, range(0, 19));

        return $profile;
    }
}
