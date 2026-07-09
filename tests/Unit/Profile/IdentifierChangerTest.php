<?php declare(strict_types=1);

namespace App\Tests\Unit\Profile;

use App\Entity\Network;
use App\Entity\Profile;
use App\Profile\IdentifierChangeException;
use App\Profile\IdentifierChangeResult;
use App\Profile\IdentifierChanger;
use App\Repository\ProfileRepository;
use App\RssApp\FeedRegistrar;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IdentifierChangerTest extends TestCase
{
    private ProfileRepository&MockObject $profileRepository;
    private FeedRegistrar&MockObject $feedRegistrar;
    private EntityManagerInterface&MockObject $em;
    private IdentifierChanger $changer;

    protected function setUp(): void
    {
        $this->profileRepository = $this->createMock(ProfileRepository::class);
        $this->feedRegistrar = $this->createMock(FeedRegistrar::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->changer = new IdentifierChanger(
            $this->profileRepository,
            $this->feedRegistrar,
            $this->em,
        );
    }

    public function testUnchangedIdentifierDoesNotRelinkOrFlush(): void
    {
        $profile = $this->makeProfile('https://www.instagram.com/samename/');

        $this->feedRegistrar->expects($this->never())->method('relinkRssAppFeed');
        $this->em->expects($this->never())->method('flush');

        $result = $this->changer->change($profile, 'https://www.instagram.com/samename/');

        $this->assertFalse($result->changed);
    }

    public function testEmptyIdentifierThrows(): void
    {
        $profile = $this->makeProfile('https://www.instagram.com/foo/');

        $this->expectException(IdentifierChangeException::class);

        $this->changer->change($profile, '   ');
    }

    public function testInvalidIdentifierForNetworkThrows(): void
    {
        $profile = $this->makeProfile('https://www.instagram.com/foo/');

        $this->feedRegistrar->expects($this->never())->method('relinkRssAppFeed');

        $this->expectException(IdentifierChangeException::class);

        $this->changer->change($profile, 'not-a-valid-instagram-url');
    }

    public function testDuplicateIdentifierThrows(): void
    {
        $profile = $this->makeProfile('https://www.instagram.com/foo/', 1);

        $other = $this->makeProfile('https://www.instagram.com/newname/', 2);

        $this->profileRepository->method('findOneByNetworkAndIdentifier')->willReturn($other);

        $this->feedRegistrar->expects($this->never())->method('relinkRssAppFeed');

        $this->expectException(IdentifierChangeException::class);

        $this->changer->change($profile, 'https://www.instagram.com/newname/');
    }

    public function testValidChangeSetsIdentifierRelinksAndFlushes(): void
    {
        $profile = $this->makeProfile('https://www.instagram.com/oldname/', 1);

        $this->profileRepository->method('findOneByNetworkAndIdentifier')->willReturn(null);

        $expected = IdentifierChangeResult::relinked(true, false, 3);
        $this->feedRegistrar->expects($this->once())
            ->method('relinkRssAppFeed')
            ->with($profile)
            ->willReturnCallback(function (Profile $p) use ($expected): IdentifierChangeResult {
                // Identifier must already be updated before the re-link runs.
                $this->assertSame('https://www.instagram.com/newname/', $p->getIdentifier());
                return $expected;
            });

        $this->em->expects($this->once())->method('flush');

        $result = $this->changer->change($profile, 'https://www.instagram.com/newname/');

        $this->assertSame($expected, $result);
        $this->assertSame('https://www.instagram.com/newname/', $profile->getIdentifier());
    }

    public function testDuplicateCheckIgnoresSameProfile(): void
    {
        $profile = $this->makeProfile('https://www.instagram.com/oldname/', 7);

        // Repository returns the very same profile (its own row) — must not be treated as a conflict.
        $this->profileRepository->method('findOneByNetworkAndIdentifier')->willReturn($profile);

        $this->feedRegistrar->expects($this->once())
            ->method('relinkRssAppFeed')
            ->willReturn(IdentifierChangeResult::relinked(false, false, 0));

        $result = $this->changer->change($profile, 'https://www.instagram.com/newname/');

        $this->assertTrue($result->changed);
    }

    private function makeProfile(string $identifier, ?int $id = null): Profile
    {
        $network = new Network();
        $network->setIdentifier('instagram_profile');
        $network->setName('Instagram-Profil');
        $network->setProfileUrlPattern('#^https?://(www\.)?instagram\.[A-Za-z]{2,3}/[A-Za-z0-9\-_]{5,}/?$#i');

        $profile = new Profile();
        $profile->setNetwork($network);
        $profile->setIdentifier($identifier);

        if ($id !== null) {
            $profile->setId($id);
        }

        return $profile;
    }
}
