<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<\App\Entity\Client> */
class ClientTokenUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $client = $this->clientRepository->findOneByToken($identifier);

        if (!$client) {
            $exception = new UserNotFoundException('Invalid API token.');
            // Never put the presented token itself on the exception: it ends up
            // in the security log and in the failure response. A short digest
            // is enough to correlate repeated attempts.
            $exception->setUserIdentifier(self::fingerprint($identifier));
            throw $exception;
        }

        return $client;
    }

    /**
     * Re-read the client on every session refresh, so disabling it or rotating
     * its token ends an existing Web-UI session instead of leaving the
     * deserialized copy authenticated forever.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Client) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier((string) $user->getToken());
    }

    private static function fingerprint(string $token): string
    {
        return 'client-token#' . substr(hash('sha256', $token), 0, 12);
    }

    public function supportsClass(string $class): bool
    {
        return $class === \App\Entity\Client::class;
    }
}
