<?php declare(strict_types=1);

namespace App\Tests\Functional\PublicPage;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Item;
use App\Entity\Profile;
use App\Tests\Functional\AbstractApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client as ApiClient;
use Doctrine\ORM\EntityManagerInterface;

class PublicGroupControllerTest extends AbstractApiTestCase
{
    private const SHARED_PROFILE_ID = 90001;

    public function testEnabledGroupShowsItems(): void
    {
        $client = static::createClient();
        $this->makeGroup('showitems01', ['timeWindowDays' => null]);

        $client->request('GET', '/p/showitems01');

        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Shared item 1', $this->body($client));
    }

    public function testDisabledGroupReturns404(): void
    {
        $client = static::createClient();
        $this->makeGroup('disabled01', ['enabled' => false]);

        $client->request('GET', '/p/disabled01');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUnknownSlugReturns404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/p/does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testHiddenAndDeletedItemsExcluded(): void
    {
        $client = static::createClient();
        $this->makeGroup('nohidden01', ['timeWindowDays' => null]);

        $client->request('GET', '/p/nohidden01');
        $body = $this->body($client);

        self::assertStringNotContainsString('Shared hidden item', $body);
        self::assertStringNotContainsString('Shared soft-deleted item', $body);
    }

    public function testTimeWindowExcludesOldItems(): void
    {
        $client = static::createClient();
        $this->makeGroup('window01', ['timeWindowDays' => 1]);

        $client->request('GET', '/p/window01');
        $body = $this->body($client);

        self::assertStringContainsString('Shared item 1', $body);   // 1h ago
        self::assertStringNotContainsString('Shared item 3', $body); // 48h ago
    }

    public function testPhotosHiddenWhenDisabled(): void
    {
        $client = static::createClient();
        $this->makeGroup('nophoto01', [
            'timeWindowDays' => null,
            'showPhotos' => false,
            'mediaItem' => ['photoPaths' => ['90001/700/photo_0.jpg']],
        ]);

        $client->request('GET', '/p/nophoto01');

        self::assertStringNotContainsString('photo_0.jpg', $this->body($client));
    }

    public function testPhotosShownWhenEnabled(): void
    {
        $client = static::createClient();
        $this->makeGroup('photo01', [
            'timeWindowDays' => null,
            'showPhotos' => true,
            'mediaItem' => ['photoPaths' => ['90001/701/photo_0.jpg']],
        ]);

        $client->request('GET', '/p/photo01');

        self::assertStringContainsString('photo_0.jpg', $this->body($client));
    }

    public function testVideosHiddenWhenDisabled(): void
    {
        $client = static::createClient();
        $this->makeGroup('novideo01', [
            'timeWindowDays' => null,
            'showVideos' => false,
            'mediaItem' => ['videoPath' => '90001/702/video.mp4'],
        ]);

        $client->request('GET', '/p/novideo01');

        self::assertStringNotContainsString('<video', $this->body($client));
    }

    public function testVideosShownWhenEnabled(): void
    {
        $client = static::createClient();
        $this->makeGroup('video01', [
            'timeWindowDays' => null,
            'showVideos' => true,
            'mediaItem' => ['videoPath' => '90001/703/video.mp4'],
        ]);

        $client->request('GET', '/p/video01');

        self::assertStringContainsString('<video', $this->body($client));
    }

    public function testTranscriptHiddenWhenDisabled(): void
    {
        $client = static::createClient();
        $this->makeGroup('notrans01', [
            'timeWindowDays' => null,
            'showVideos' => true,
            'showTranscript' => false,
            'mediaItem' => ['videoPath' => '90001/704/video.mp4', 'transcript' => 'SECRET TRANSCRIPT'],
        ]);

        $client->request('GET', '/p/notrans01');

        self::assertStringNotContainsString('SECRET TRANSCRIPT', $this->body($client));
    }

    public function testTranscriptShownWhenEnabled(): void
    {
        $client = static::createClient();
        $this->makeGroup('trans01', [
            'timeWindowDays' => null,
            'showVideos' => true,
            'showTranscript' => true,
            'mediaItem' => ['videoPath' => '90001/705/video.mp4', 'transcript' => 'VISIBLE TRANSCRIPT'],
        ]);

        $client->request('GET', '/p/trans01');

        self::assertStringContainsString('VISIBLE TRANSCRIPT', $this->body($client));
    }

    public function testPasswordGateBlocksThenUnlocks(): void
    {
        $client = static::createClient();
        $this->makeGroup('locked01', ['timeWindowDays' => null, 'password' => 'letmein']);

        // Locked: gate is shown, items are not.
        $client->request('GET', '/p/locked01');
        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('passwortgeschützt', $this->body($client));
        self::assertStringNotContainsString('Shared item 1', $this->body($client));

        // Wrong password keeps the gate.
        $client->request('POST', '/p/locked01/unlock', ['extra' => ['parameters' => ['password' => 'nope']]]);
        self::assertStringContainsString('Falsches Passwort', $this->body($client));

        // Correct password unlocks; the following page load shows the feed.
        $client->request('POST', '/p/locked01/unlock', ['extra' => ['parameters' => ['password' => 'letmein']]]);
        $client->request('GET', '/p/locked01');
        self::assertStringContainsString('Shared item 1', $this->body($client));
    }

    /** @param array<string, mixed> $opts */
    private function makeGroup(string $slug, array $opts = []): void
    {
        $em = $this->em();

        $clientA = $em->getRepository(Client::class)->findOneBy(['name' => 'Client A']);
        $profile = $em->getRepository(Profile::class)->find(self::SHARED_PROFILE_ID);
        self::assertInstanceOf(Client::class, $clientA);
        self::assertInstanceOf(Profile::class, $profile);

        $group = new Group();
        $group->setName($opts['name'] ?? 'Public Test');
        $group->setClient($clientA);
        $group->addProfile($profile);
        $group->setPublicPageEnabled($opts['enabled'] ?? true);
        $group->setPublicSlug($slug);
        $group->setShowPhotos($opts['showPhotos'] ?? true);
        $group->setShowVideos($opts['showVideos'] ?? true);
        $group->setShowTranscript($opts['showTranscript'] ?? false);
        $group->setShowCaptions($opts['showCaptions'] ?? true);
        $group->setTimeWindowDays($opts['timeWindowDays'] ?? null);
        if (!empty($opts['password'])) {
            $group->setPublicPassword($opts['password']);
        }
        $em->persist($group);

        if (isset($opts['mediaItem'])) {
            $this->addMediaItem($em, $profile, $opts['mediaItem']);
        }

        $em->flush();
    }

    /** @param array<string, mixed> $media */
    private function addMediaItem(EntityManagerInterface $em, Profile $profile, array $media): void
    {
        $item = new Item();
        $item->setProfile($profile);
        $item->setUniqueIdentifier('media-' . bin2hex(random_bytes(4)));
        $item->setText('Media caption');
        $item->setPermalink('https://mastodon.social/@shared/media');
        $item->setDateTime(new \DateTimeImmutable('-1 hour'));
        if (isset($media['photoPaths'])) {
            $item->setPhotoPaths($media['photoPaths']);
        }
        if (isset($media['videoPath'])) {
            $item->setVideoPath($media['videoPath']);
        }
        if (isset($media['transcript'])) {
            $item->setTranscript($media['transcript']);
        }
        $em->persist($item);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    private function body(ApiClient $client): string
    {
        return $client->getResponse()->getContent() ?: '';
    }
}
