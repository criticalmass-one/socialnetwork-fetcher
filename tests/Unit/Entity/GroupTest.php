<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Profile;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase
{
    public function testConstructorInitializesEmptyProfilesAndCreatedAt(): void
    {
        $group = new Group();

        $this->assertSame(0, $group->getProfileCount());
        $this->assertEqualsWithDelta(time(), $group->getCreatedAt()->getTimestamp(), 5);
    }

    public function testAddProfileIsIdempotent(): void
    {
        $group = new Group();
        $profile = new Profile();

        $group->addProfile($profile);
        $group->addProfile($profile);

        $this->assertSame(1, $group->getProfileCount());
    }

    public function testRemoveProfile(): void
    {
        $group = new Group();
        $a = new Profile();
        $b = new Profile();

        $group->addProfile($a);
        $group->addProfile($b);
        $this->assertSame(2, $group->getProfileCount());

        $group->removeProfile($a);
        $this->assertSame(1, $group->getProfileCount());
        $this->assertTrue($group->getProfiles()->contains($b));
    }

    public function testClientGetterSetter(): void
    {
        $group = new Group();
        $client = new Client();

        $group->setClient($client);

        $this->assertSame($client, $group->getClient());
    }

    public function testFieldsRoundtrip(): void
    {
        $group = new Group();
        $group->setName('Demo');
        $group->setDescription('Some description.');
        $group->setColor('#abcdef');

        $this->assertSame('Demo', $group->getName());
        $this->assertSame('Some description.', $group->getDescription());
        $this->assertSame('#abcdef', $group->getColor());
    }
}
