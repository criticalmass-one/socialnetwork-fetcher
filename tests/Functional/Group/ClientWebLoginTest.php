<?php declare(strict_types=1);

namespace App\Tests\Functional\Group;

use App\Tests\Functional\AbstractApiTestCase;

class ClientWebLoginTest extends AbstractApiTestCase
{
    public function testValidClientTokenLogsInAndRedirectsToGroups(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/login/client-token', $this->formBody(['client_token' => self::TOKEN_A]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/groups', $this->location($response));
    }

    public function testInvalidClientTokenRedirectsBackToLogin(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/login/client-token', $this->formBody(['client_token' => str_repeat('z', 64)]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $this->location($response));
    }

    public function testMalformedTokenRedirectsBackToLogin(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/login/client-token', $this->formBody(['client_token' => 'too-short']));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $this->location($response));
    }

    public function testDisabledClientTokenIsRejected(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/login/client-token', $this->formBody(['client_token' => self::TOKEN_DISABLED]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $this->location($response));
    }

    private function location(\Symfony\Contracts\HttpClient\ResponseInterface $response): string
    {
        $headers = $response->getHeaders(throw: false);
        return $headers['location'][0] ?? '';
    }

    /** @param array<string, string> $fields */
    private function formBody(array $fields): array
    {
        return [
            'extra' => ['parameters' => $fields],
        ];
    }
}
