<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Client;
use App\Entity\Item;
use App\Transcription\TranscriptionService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Queues a (re)transcription of a single item's video. The item is loaded by the
 * default item provider, so the client-scoping Doctrine extension already
 * enforces 404 for items the client may not see.
 *
 * @implements ProcessorInterface<Item, Item>
 */
class ItemTranscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly TranscriptionService $transcriptionService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Item
    {
        if (!$this->security->getUser() instanceof Client) {
            throw new BadRequestHttpException('Authentication required.');
        }

        if (!$data instanceof Item) {
            throw new NotFoundHttpException('Item not found.');
        }

        $profile = $data->getProfile();

        if (!$profile || !$profile->isTranscribeVideos()) {
            throw new UnprocessableEntityHttpException(
                'Enable transcribeVideos on the item\'s profile before triggering a transcription.',
            );
        }

        if (!$data->hasVideo()) {
            throw new UnprocessableEntityHttpException(
                'The item has no downloaded video to transcribe.',
            );
        }

        $this->transcriptionService->queueItem($data);

        return $data;
    }
}
