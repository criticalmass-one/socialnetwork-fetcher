<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Network;
use PHPUnit\Framework\TestCase;

class NetworkTest extends TestCase
{
    public function testIsValidProfileUrlReturnsTrue(): void
    {
        $network = new Network();
        $network->setProfileUrlPattern('#^https?://(www\.)?twitter\.com/[A-Za-z0-9_]+/?$#i');

        $this->assertTrue($network->isValidProfileUrl('https://twitter.com/testuser'));
    }

    public function testIsValidProfileUrlReturnsFalse(): void
    {
        $network = new Network();
        $network->setProfileUrlPattern('#^https?://(www\.)?twitter\.com/[A-Za-z0-9_]+/?$#i');

        $this->assertFalse($network->isValidProfileUrl('https://mastodon.social/@user'));
    }

    public function testIsValidProfileUrlReturnsFalseWhenPatternIsNull(): void
    {
        $network = new Network();

        $this->assertFalse($network->isValidProfileUrl('https://example.com'));
    }
}
