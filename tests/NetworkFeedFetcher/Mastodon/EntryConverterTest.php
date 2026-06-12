<?php declare(strict_types=1);

namespace App\Tests\NetworkFeedFetcher\Mastodon;

use App\Model\Profile;
use App\NetworkFeedFetcher\Mastodon\EntryConverter;
use App\NetworkFeedFetcher\Mastodon\Model\Status;
use PHPUnit\Framework\TestCase;

class EntryConverterTest extends TestCase
{
    private function createStatus(): Status
    {
        return (new Status())
            ->setId('116296397062584045')
            ->setCreatedAt(new \DateTime('2026-06-10 12:00:00'))
            ->setUrl('https://mastodon.social/@Example/116296397062584045')
            ->setContent('<p>Hello world</p>');
    }

    private function createProfile(): Profile
    {
        $profile = new Profile();
        $profile->setId(42);

        return $profile;
    }

    public function testConvertSetsBasicFields(): void
    {
        $item = EntryConverter::convert($this->createProfile(), $this->createStatus());

        $this->assertNotNull($item);
        $this->assertSame('https://mastodon.social/@Example/116296397062584045', $item->getPermalink());
        $this->assertSame('https://mastodon.social/@Example/116296397062584045', $item->getUniqueIdentifier());
        $this->assertSame('<p>Hello world</p>', $item->getText());
        $this->assertSame(42, $item->getProfileId());
    }

    public function testConvertStoresRawEntry(): void
    {
        $rawEntry = [
            'id' => '116296397062584045',
            'url' => 'https://mastodon.social/@Example/116296397062584045',
            'media_attachments' => [
                ['type' => 'video', 'url' => 'https://files.mastodon.social/media/video.mp4'],
            ],
        ];

        $item = EntryConverter::convert($this->createProfile(), $this->createStatus(), $rawEntry);

        $this->assertNotNull($item);
        $this->assertJson($item->getRaw());

        $decoded = json_decode($item->getRaw(), true);
        $this->assertSame('video', $decoded['media_attachments'][0]['type']);
    }

    public function testConvertWithoutRawEntryLeavesRawNull(): void
    {
        $item = EntryConverter::convert($this->createProfile(), $this->createStatus());

        $this->assertNotNull($item);
        $this->assertNull($item->getRaw());
    }
}
