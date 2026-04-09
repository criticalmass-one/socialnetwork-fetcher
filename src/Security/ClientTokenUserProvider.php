<?php declare(strict_types=1);

namespace App\Security;

use App\Repository\ClientRepository;
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
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        return $client;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === \App\Entity\Client::class;
    }
}
