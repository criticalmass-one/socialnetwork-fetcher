<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Profile;
use PHPUnit\Framework\TestCase;

class ProfileTest extends TestCase
{
    public function testConstructorInitializesEmptyClients(): void
    {
        $profile = new Profile();

        $this->assertCount(0, $profile->getClients());
    }

    public function testGetAdditionalDataDecodesJson(): void
    {
        $profile = new Profile();
        $data = ['rss_feed_id' => 'abc123', 'key' => 'value'];
        $profile->setAdditionalData($data);

        $this->assertSame($data, $profile->getAdditionalData());
    }

    public function testGetAdditionalDataReturnsNullWhenNotSet(): void
    {
        $profile = new Profile();

        $this->assertNull($profile->getAdditionalData());
    }

    public function testSetAdditionalDataNullSetsNull(): void
    {
        $profile = new Profile();
        $profile->setAdditionalData(['key' => 'value']);
        $profile->setAdditionalData(null);

        $this->assertNull($profile->getAdditionalData());
    }

    public function testGetClientCount(): void
    {
        $profile = new Profile();

        $this->assertSame(0, $profile->getClientCount());
    }

    public function testDefaultValues(): void
    {
        $profile = new Profile();

        $this->assertTrue($profile->isAutoFetch());
        $this->assertFalse($profile->isFetchSource());
        $this->assertFalse($profile->isDeleted());
        $this->assertNull($profile->getDeletedAt());
    }
}
