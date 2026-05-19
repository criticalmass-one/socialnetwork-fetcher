<?php declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Logs a Client into the Web UI via a 64-char hex token submitted from the
 * second form on /login. Successful logins land on /groups (Client UX scope);
 * failures bounce back to /login with a flash error.
 */
class WebTokenAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    private const LOGIN_PATH = '/login/client-token';
    private const FAILURE_PATH = '/login';
    private const SUCCESS_PATH = '/groups';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ClientTokenUserProvider $clientTokenUserProvider,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST') && $request->getPathInfo() === self::LOGIN_PATH;
    }

    public function authenticate(Request $request): Passport
    {
        $token = trim((string) $request->request->get('client_token', ''));
        if ($token === '') {
            throw new CustomUserMessageAuthenticationException('Bitte Token angeben.');
        }
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            throw new CustomUserMessageAuthenticationException('Ungültiges Token-Format (64 Hex-Zeichen erwartet).');
        }

        return new SelfValidatingPassport(
            new UserBadge($token, fn(string $identifier) => $this->clientTokenUserProvider->loadUserByIdentifier($identifier)),
        );
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        $targetUrl = self::SUCCESS_PATH;

        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->getFlashBag()->add('success', sprintf(
                'Eingeloggt als Client „%s".',
                $token->getUser()?->getUserIdentifier() ?? 'unbekannt',
            ));
            $targetUrl = $this->getTargetPath($session, $firewallName) ?? $targetUrl;
        }

        return new RedirectResponse($targetUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('danger', sprintf(
                'Login mit Token fehlgeschlagen: %s',
                $exception instanceof CustomUserMessageAuthenticationException
                    ? $exception->getMessageKey()
                    : 'Token nicht gefunden oder Client deaktiviert.',
            ));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
