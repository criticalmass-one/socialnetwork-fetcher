<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\Profile;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testConstructorSetsCreatedAtAndEmptyProfiles(): void
    {
        $client = new Client();

        $this->assertInstanceOf(\DateTimeImmutable::class, $client->getCreatedAt());
        $this->assertCount(0, $client->getProfiles());
    }

    public function testGetUserIdentifierReturnsToken(): void
    {
        $client = new Client();
        $client->setToken('my-test-token');

        $this->assertSame('my-test-token', $client->getUserIdentifier());
    }

    public function testGetUserIdentifierReturnsEmptyStringWhenNoToken(): void
    {
        $client = new Client();

        $this->assertSame('', $client->getUserIdentifier());
    }

    public function testGetRolesReturnsRoleApiClient(): void
    {
        $client = new Client();

        $this->assertSame(['ROLE_API_CLIENT'], $client->getRoles());
    }

    public function testAddProfileIdempotent(): void
    {
        $client = new Client();
        $profile = new Profile();

        $client->addProfile($profile);
        $client->addProfile($profile);

        $this->assertCount(1, $client->getProfiles());
    }

    public function testRemoveProfile(): void
    {
        $client = new Client();
        $profile = new Profile();

        $client->addProfile($profile);
        $this->assertCount(1, $client->getProfiles());

        $client->removeProfile($profile);
        $this->assertCount(0, $client->getProfiles());
    }

    public function testGenerateTokenReturns64CharHex(): void
    {
        $token = Client::generateToken();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateTokenIsUnique(): void
    {
        $token1 = Client::generateToken();
        $token2 = Client::generateToken();

        $this->assertNotSame($token1, $token2);
    }
}
