<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Client;
use App\Entity\Profile;
use App\Profile\IdentifierChangeException;
use App\Profile\IdentifierChanger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Changes a profile's network identifier and, for RSS.app-based networks,
 * re-links its RSS.app feed. The new identifier is read from the request body
 * ({"identifier": "…"}). The profile is loaded by the default provider, so
 * client-scoping enforces 404 for profiles the client is not linked to.
 *
 * @implements ProcessorInterface<Profile, Profile>
 */
class ProfileChangeIdentifierProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly IdentifierChanger $identifierChanger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Profile
    {
        if (!$this->security->getUser() instanceof Client) {
            throw new BadRequestHttpException('Authentication required.');
        }

        if (!$data instanceof Profile) {
            throw new NotFoundHttpException('Profile not found.');
        }

        $request = $context['request'] ?? null;

        if (!$request instanceof Request) {
            throw new BadRequestHttpException('Request context is required.');
        }

        $payload = json_decode($request->getContent(), true);
        $identifier = is_array($payload) ? ($payload['identifier'] ?? null) : null;

        if (!is_string($identifier) || trim($identifier) === '') {
            throw new BadRequestHttpException('A non-empty "identifier" field is required.');
        }

        try {
            $this->identifierChanger->change($data, $identifier);
        } catch (IdentifierChangeException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }

        return $data;
    }
}
