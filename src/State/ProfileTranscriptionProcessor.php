<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Client;
use App\Entity\Profile;
use App\Transcription\TranscriptionService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Queues a (re)transcription of every (new or previously failed) video item of a
 * profile. Pass ?force=true to re-transcribe all videos. The profile is loaded by
 * the default provider, so client-scoping enforces 404 for profiles the client is
 * not linked to.
 *
 * @implements ProcessorInterface<Profile, Profile>
 */
class ProfileTranscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly TranscriptionService $transcriptionService,
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

        if (!$data->isTranscribeVideos()) {
            throw new UnprocessableEntityHttpException(
                'Enable transcribeVideos on the profile before triggering a transcription.',
            );
        }

        $request = $context['request'] ?? null;
        $force = $request instanceof Request
            && filter_var($request->query->get('force'), FILTER_VALIDATE_BOOL);

        $this->transcriptionService->queueProfile($data, $force);

        return $data;
    }
}
