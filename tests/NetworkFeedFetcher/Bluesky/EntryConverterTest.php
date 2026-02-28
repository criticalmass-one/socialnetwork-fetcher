<?php declare(strict_types=1);

namespace App\Tests\NetworkFeedFetcher\Bluesky;

use App\Model\SocialNetworkProfile;
use App\NetworkFeedFetcher\Bluesky\EntryConverter;
use PHPUnit\Framework\TestCase;

class EntryConverterTest extends TestCase
{
    private SocialNetworkProfile $profile;

    protected function setUp(): void
    {
        $this->profile = new SocialNetworkProfile();
        $this->profile->setId(42);
    }

    public function testConvertValidEntry(): void
    {
        $entry = [
            'post' => [
                'uri' => 'at://did:plc:abc123/app.bsky.feed.post/xyz789',
                'author' => [
                    'handle' => 'testuser.bsky.social',
                ],
                'record' => [
                    'text' => 'Hello Bluesky!',
                    'createdAt' => '2025-07-01T12:00:00.000Z',
                ],
            ],
        ];

        $feedItem = EntryConverter::convert($this->profile, $entry);

        $this->assertNotNull($feedItem);
        $this->assertEquals(42, $feedItem->getSocialNetworkProfileId());
        $this->assertEquals('at://did:plc:abc123/app.bsky.feed.post/xyz789', $feedItem->getUniqueIdentifier());
        $this->assertEquals('https://bsky.app/profile/testuser.bsky.social/post/xyz789', $feedItem->getPermalink());
        $this->assertEquals('Hello Bluesky!', $feedItem->getText());
        $this->assertEquals('2025-07-01', $feedItem->getDateTime()->format('Y-m-d'));
    }

    public function testConvertReturnsNullForMissingPost(): void
    {
        $this->assertNull(EntryConverter::convert($this->profile, []));
    }

    public function testConvertReturnsNullForMissingText(): void
    {
        $entry = [
            'post' => [
                'uri' => 'at://did:plc:abc123/app.bsky.feed.post/xyz789',
                'author' => ['handle' => 'test.bsky.social'],
                'record' => ['createdAt' => '2025-07-01T12:00:00.000Z'],
            ],
        ];

        $this->assertNull(EntryConverter::convert($this->profile, $entry));
    }

    public function testConvertReturnsNullForMissingHandle(): void
    {
        $entry = [
            'post' => [
                'uri' => 'at://did:plc:abc123/app.bsky.feed.post/xyz789',
                'author' => [],
                'record' => [
                    'text' => 'Hello',
                    'createdAt' => '2025-07-01T12:00:00.000Z',
                ],
            ],
        ];

        $this->assertNull(EntryConverter::convert($this->profile, $entry));
    }
}
