<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<Group, Group|null> */
class ClientScopedGroupProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Group
    {
        $client = $this->security->getUser();
        if (!$client instanceof Client) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        if ($operation instanceof Delete) {
            return $this->handleDelete($client, (int) $uriVariables['id']);
        }

        return $this->handleUpsert($client, $data);
    }

    private function handleUpsert(Client $client, Group $group): Group
    {
        $existingClient = $group->getClient();

        if ($existingClient !== null && $existingClient->getId() !== $client->getId()) {
            // Someone tried to PUT/PATCH a foreign client's group; the extension
            // should have prevented loading it, but double-check defensively.
            throw new NotFoundHttpException('Group not found.');
        }

        // POSTed groups arrive without a client. Always set / override to the
        // authenticated client — clients can never create groups for others.
        $group->setClient($client);

        $this->ensureProfilesBelongToClient($client, $group);

        $this->em->persist($group);
        $this->em->flush();

        return $group;
    }

    private function handleDelete(Client $client, int $groupId): null
    {
        $group = $this->em->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getClient()?->getId() !== $client->getId()) {
            throw new NotFoundHttpException('Group not found.');
        }

        $this->em->remove($group);
        $this->em->flush();

        return null;
    }

    private function ensureProfilesBelongToClient(Client $client, Group $group): void
    {
        foreach ($group->getProfiles() as $profile) {
            assert($profile instanceof Profile);
            if (!$profile->getClients()->contains($client)) {
                throw new BadRequestHttpException(sprintf(
                    'Profile "%s" (id=%d) is not linked to your client. Link it via POST /api/profiles first.',
                    $profile->getDisplayName(),
                    $profile->getId() ?? 0,
                ));
            }
        }
    }
}
