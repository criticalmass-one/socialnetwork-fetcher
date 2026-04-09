<?php declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\AbstractApiTestCase;

class AuthenticationTest extends AbstractApiTestCase
{
    public function testValidTokenReturns200(): void
    {
        $this->requestAsClientA('GET', '/api/profiles');

        $this->assertResponseIsSuccessful();
    }

    public function testInvalidTokenReturns401(): void
    {
        $this->requestWithToken('GET', '/api/profiles', self::TOKEN_INVALID);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDisabledClientReturns401(): void
    {
        $this->requestWithToken('GET', '/api/profiles', self::TOKEN_DISABLED);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testMissingAuthHeaderReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/profiles');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testApiDocsAccessibleWithoutAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/docs', ['headers' => ['Accept' => 'application/ld+json']]);

        $this->assertResponseIsSuccessful();
    }
}
