<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\ApiTokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class ApiTokenAuthenticatorTest extends TestCase
{
    private ApiTokenAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new ApiTokenAuthenticator();
    }

    public function testSupportsReturnsTrueWithBearerHeader(): void
    {
        $request = Request::create('/api/profiles');
        $request->headers->set('Authorization', 'Bearer sometoken');

        $this->assertTrue($this->authenticator->supports($request));
    }

    public function testSupportsReturnsFalseWithoutAuthHeader(): void
    {
        $request = Request::create('/api/profiles');

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testSupportsReturnsFalseWithNonBearerAuth(): void
    {
        $request = Request::create('/api/profiles');
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testAuthenticateExtractsToken(): void
    {
        $request = Request::create('/api/profiles');
        $request->headers->set('Authorization', 'Bearer my-api-token');

        $passport = $this->authenticator->authenticate($request);
        $badge = $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class);

        $this->assertSame('my-api-token', $badge->getUserIdentifier());
    }

    public function testAuthenticateThrowsOnEmptyToken(): void
    {
        $request = Request::create('/api/profiles');
        $request->headers->set('Authorization', 'Bearer ');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->authenticator->authenticate($request);
    }

    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $request = Request::create('/api/profiles');
        $token = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);

        $this->assertNull($this->authenticator->onAuthenticationSuccess($request, $token, 'api'));
    }

    public function testOnAuthenticationFailureReturnsUnauthorizedJson(): void
    {
        $request = Request::create('/api/profiles');
        $exception = new CustomUserMessageAuthenticationException('Invalid token.');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Invalid token.', $response->getContent());
    }
}
