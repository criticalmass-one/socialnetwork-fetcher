<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Security\ClientTokenUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class ClientTokenUserProviderTest extends TestCase
{
    public function testLoadUserByIdentifierReturnsClient(): void
    {
        $client = new Client();
        $client->setToken('valid-token');

        $repository = $this->createMock(ClientRepository::class);
        $repository->method('findOneByToken')->with('valid-token')->willReturn($client);

        $provider = new ClientTokenUserProvider($repository);

        $this->assertSame($client, $provider->loadUserByIdentifier('valid-token'));
    }

    public function testLoadUserByIdentifierThrowsForInvalidToken(): void
    {
        $repository = $this->createMock(ClientRepository::class);
        $repository->method('findOneByToken')->willReturn(null);

        $provider = new ClientTokenUserProvider($repository);

        $this->expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier('invalid-token');
    }

    public function testFailedLookupDoesNotPutTheTokenOnTheException(): void
    {
        $repository = $this->createMock(ClientRepository::class);
        $repository->method('findOneByToken')->willReturn(null);

        $provider = new ClientTokenUserProvider($repository);

        try {
            $provider->loadUserByIdentifier('super-secret-token');
            $this->fail('Expected a UserNotFoundException.');
        } catch (UserNotFoundException $exception) {
            $this->assertStringNotContainsString('super-secret-token', (string) $exception->getUserIdentifier());
            $this->assertStringStartsWith('client-token#', (string) $exception->getUserIdentifier());
        }
    }

    public function testSupportsClassReturnsTrueForClient(): void
    {
        $repository = $this->createMock(ClientRepository::class);
        $provider = new ClientTokenUserProvider($repository);

        $this->assertTrue($provider->supportsClass(Client::class));
    }

    public function testSupportsClassReturnsFalseForOther(): void
    {
        $repository = $this->createMock(ClientRepository::class);
        $provider = new ClientTokenUserProvider($repository);

        $this->assertFalse($provider->supportsClass(\stdClass::class));
    }

    public function testRefreshUserReloadsTheClientFromTheDatabase(): void
    {
        $stale = new Client();
        $stale->setToken('valid-token');

        $fresh = new Client();
        $fresh->setToken('valid-token');

        $repository = $this->createMock(ClientRepository::class);
        $repository->expects($this->once())
            ->method('findOneByToken')
            ->with('valid-token')
            ->willReturn($fresh);

        $provider = new ClientTokenUserProvider($repository);

        $this->assertSame($fresh, $provider->refreshUser($stale));
    }

    public function testRefreshUserRejectsADisabledOrRotatedClient(): void
    {
        $client = new Client();
        $client->setToken('revoked-token');

        // findOneByToken filters on enabled=true, so a disabled client or a
        // rotated token simply is not found any more.
        $repository = $this->createMock(ClientRepository::class);
        $repository->method('findOneByToken')->willReturn(null);

        $provider = new ClientTokenUserProvider($repository);

        $this->expectException(UserNotFoundException::class);
        $provider->refreshUser($client);
    }
}
