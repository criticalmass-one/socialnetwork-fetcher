<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Client;
use App\Entity\Item;
use App\MediaDownloader\MediaDownloadService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Queues a (re)download of a single item's media. The item is loaded by the
 * default item provider, so the client-scoping Doctrine extension already
 * enforces 404 for items the client may not see.
 *
 * @implements ProcessorInterface<Item, Item>
 */
class ItemMediaDownloadProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly MediaDownloadService $mediaDownloadService,
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

        if (!$profile || (!$profile->isSavePhotos() && !$profile->isSaveVideos())) {
            throw new UnprocessableEntityHttpException(
                'Enable savePhotos and/or saveVideos on the item\'s profile before triggering a media download.',
            );
        }

        $this->mediaDownloadService->queueItem($data);

        return $data;
    }
}
