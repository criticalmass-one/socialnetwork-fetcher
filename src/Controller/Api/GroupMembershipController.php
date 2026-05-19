<?php declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Client;
use App\Entity\Group;
use App\Repository\GroupRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class GroupMembershipController
{
    public function __construct(
        private readonly Security $security,
        private readonly GroupRepository $groupRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/groups/{id}/profiles', name: 'app_api_group_membership_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        summary: 'Add one or more profiles to a group (idempotent).',
        description: 'Body: `{"profileIds": [42, 43]}` *or* `{"profiles": ["/api/profiles/42", "/api/profiles/43"]}`. Each profile must already be linked to the authenticated client. Already-member profiles are silently kept.',
        tags: ['Group'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'profileIds', type: 'array', items: new OA\Items(type: 'integer'), example: [42, 43]),
                    new OA\Property(property: 'profiles', type: 'array', items: new OA\Items(type: 'string', format: 'iri'), example: ['/api/profiles/42', '/api/profiles/43']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profiles added. Returns the resulting profile id list.'),
            new OA\Response(response: 400, description: 'Body missing profile ids or referenced profile not linked to client.'),
            new OA\Response(response: 404, description: 'Group not found or not owned by client.'),
        ],
    )]
    public function add(int $id, Request $request): JsonResponse
    {
        $client = $this->requireClient();
        $group = $this->requireOwnedGroup($id, $client);

        $payload = $this->decodeJson($request);
        $profileIds = $this->extractProfileIds($payload);

        if ($profileIds === []) {
            throw new BadRequestHttpException('Request must include profileIds or profiles.');
        }

        foreach ($profileIds as $profileId) {
            $profile = $this->profileRepository->find($profileId);
            if ($profile === null) {
                throw new BadRequestHttpException(sprintf('Profile %d not found.', $profileId));
            }
            if (!$profile->getClients()->contains($client)) {
                throw new BadRequestHttpException(sprintf('Profile %d is not linked to your client.', $profileId));
            }
            $group->addProfile($profile);
        }

        $this->em->flush();

        return new JsonResponse([
            'id' => $group->getId(),
            'profileIds' => array_values(array_map(fn($p) => $p->getId(), $group->getProfiles()->toArray())),
        ]);
    }

    #[Route('/api/groups/{id}/profiles/{profileId}', name: 'app_api_group_membership_remove', requirements: ['id' => '\d+', 'profileId' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Remove a profile from a group.',
        description: 'Removes a single profile membership. The profile itself stays untouched; only the link to this group is removed.',
        tags: ['Group'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'profileId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Removed (or was not a member to begin with).'),
            new OA\Response(response: 404, description: 'Group not found or not owned by client.'),
        ],
    )]
    public function remove(int $id, int $profileId): Response
    {
        $client = $this->requireClient();
        $group = $this->requireOwnedGroup($id, $client);

        $profile = $this->profileRepository->find($profileId);
        if ($profile !== null) {
            $group->removeProfile($profile);
            $this->em->flush();
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function requireClient(): Client
    {
        $client = $this->security->getUser();
        if (!$client instanceof Client) {
            throw new AccessDeniedHttpException();
        }
        return $client;
    }

    private function requireOwnedGroup(int $id, Client $client): Group
    {
        $group = $this->groupRepository->find($id);
        if ($group === null || $group->getClient()?->getId() !== $client->getId()) {
            throw new NotFoundHttpException('Group not found.');
        }
        return $group;
    }

    /** @return array<string, mixed> */
    private function decodeJson(Request $request): array
    {
        $raw = $request->getContent();
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Body must be valid JSON: ' . $e->getMessage());
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<int>
     */
    private function extractProfileIds(array $payload): array
    {
        $ids = [];

        if (isset($payload['profileIds']) && is_array($payload['profileIds'])) {
            foreach ($payload['profileIds'] as $value) {
                $id = (int) $value;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        if (isset($payload['profiles']) && is_array($payload['profiles'])) {
            foreach ($payload['profiles'] as $value) {
                if (!is_string($value)) {
                    continue;
                }
                if (preg_match('#/api/profiles/(\d+)$#', $value, $m)) {
                    $ids[] = (int) $m[1];
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
