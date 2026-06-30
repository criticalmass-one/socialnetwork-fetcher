<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Client;
use App\Entity\Profile;
use App\MediaDownloader\MediaDownloadService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Queues a (re)download of media for every (new or previously failed) item of a
 * profile. Pass ?force=true to re-queue all items, e.g. to renew expired media.
 * The profile is loaded by the default provider, so client-scoping enforces 404
 * for profiles the client is not linked to.
 *
 * @implements ProcessorInterface<Profile, Profile>
 */
class ProfileMediaDownloadProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly MediaDownloadService $mediaDownloadService,
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

        if (!$data->isSavePhotos() && !$data->isSaveVideos()) {
            throw new UnprocessableEntityHttpException(
                'Enable savePhotos and/or saveVideos on the profile before triggering a media download.',
            );
        }

        $request = $context['request'] ?? null;
        $force = $request instanceof Request
            && filter_var($request->query->get('force'), FILTER_VALIDATE_BOOL);

        $this->mediaDownloadService->queueProfile($data, $force);

        return $data;
    }
}
