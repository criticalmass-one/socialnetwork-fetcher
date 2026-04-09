<?php declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<InMemoryUser> */
class WebUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly string $webAdminUsername,
        private readonly string $webAdminPasswordHash,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if ($identifier !== $this->webAdminUsername) {
            throw new UserNotFoundException();
        }

        return new InMemoryUser(
            $this->webAdminUsername,
            $this->webAdminPasswordHash,
            ['ROLE_ADMIN'],
        );
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === InMemoryUser::class;
    }
}
