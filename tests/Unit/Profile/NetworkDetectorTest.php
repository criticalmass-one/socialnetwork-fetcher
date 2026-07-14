<?php declare(strict_types=1);

namespace App\Tests\Unit\Profile;

use App\Entity\Network;
use App\Profile\NetworkDetector;
use App\Repository\NetworkRepository;
use PHPUnit\Framework\TestCase;

class NetworkDetectorTest extends TestCase
{
    private NetworkDetector $detector;

    protected function setUp(): void
    {
        $networks = [
            $this->network('instagram_profile', '#^https?://(www\.)?instagram\.[A-Za-z]{2,3}/[A-Za-z0-9._\-]{5,}/?$#i'),
            $this->network('facebook_page', '#^https?://(www\.)?facebook\.com/(?!groups/|events/|profile\.php)([A-Za-z0-9.\-]+)/?$#i'),
            $this->network('bluesky_profile', '#^(https?://bsky\.app/profile/(did:plc:[a-z0-9]+|[a-z0-9.\-]+\.[a-z]{2,})/?|[a-z0-9.\-]+\.[a-z]{2,})$#i'),
            $this->network('youtube_channel', '#^https?://(www\.)?youtube\.com/channel/.+$#i'),
            $this->network('youtube_video', '#^((?:https?:)?//)?((?:www|m)\.)?((?:youtube\.com|youtu\.be))(\/(?:watch+\?v=|embed\/|v\/)?)([\w\-]+)(\S+)?$#i'),
            $this->network('homepage', '#^https?://.+$#i'),
        ];

        $repository = $this->createMock(NetworkRepository::class);
        $repository->method('findAll')->willReturn($networks);

        $this->detector = new NetworkDetector($repository);
    }

    public function testDetectsInstagramProfile(): void
    {
        $result = $this->detector->detect('https://www.instagram.com/torsten.franz.lg/');

        self::assertTrue($result->isDetected());
        self::assertSame('instagram_profile', $result->network->getIdentifier());
    }

    public function testDetectsFacebookPage(): void
    {
        $result = $this->detector->detect('https://www.facebook.com/GrueneLueneburg');

        self::assertTrue($result->isDetected());
        self::assertSame('facebook_page', $result->network->getIdentifier());
    }

    public function testDetectsBlueskyFromBareHandle(): void
    {
        $result = $this->detector->detect('someone.bsky.social');

        self::assertTrue($result->isDetected());
        self::assertSame('bluesky_profile', $result->network->getIdentifier());
    }

    public function testFallsBackToHomepageWhenOnlyGenericMatch(): void
    {
        $result = $this->detector->detect('https://example.org/blog');

        self::assertTrue($result->isDetected());
        self::assertSame('homepage', $result->network->getIdentifier());
    }

    public function testAmbiguousWhenMultipleSpecificNetworksMatch(): void
    {
        // A channel URL matches both youtube_channel and the broad youtube_video.
        $result = $this->detector->detect('https://www.youtube.com/channel/UCabc123');

        self::assertFalse($result->isDetected());
        self::assertTrue($result->isAmbiguous());
        self::assertCount(2, $result->candidates);
    }

    public function testNoneWhenNothingMatches(): void
    {
        $result = $this->detector->detect('not a url at all');

        self::assertFalse($result->isDetected());
        self::assertFalse($result->isAmbiguous());
    }

    public function testNoneForEmptyIdentifier(): void
    {
        $result = $this->detector->detect('   ');

        self::assertFalse($result->isDetected());
    }

    private function network(string $identifier, string $pattern): Network
    {
        $network = new Network();
        $network->setIdentifier($identifier);
        $network->setName($identifier);
        $network->setProfileUrlPattern($pattern);

        return $network;
    }
}
