<?php declare(strict_types=1);

namespace App\FeedItemPersister;

use App\Entity\Item as ItemEntity;
use App\Entity\Profile as ProfileEntity;
use App\FeedFetcher\FetchResult;
use App\Model\Item as ItemModel;
use App\Repository\ItemRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineFeedItemPersister implements FeedItemPersisterInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProfileRepository $profileRepository,
        private readonly ItemRepository $itemRepository,
    ) {
    }

    public function persistFeedItemList(array $feedItemList, ?FetchResult $fetchResult): FeedItemPersisterInterface
    {
        foreach ($feedItemList as $feedItem) {
            if ($feedItem instanceof ItemModel) {
                $this->persistFeedItem($feedItem, $fetchResult);
            }
        }

        return $this;
    }

    public function persistFeedItem(ItemModel $feedItem, ?FetchResult $fetchResult): FeedItemPersisterInterface
    {
        $profileEntity = $this->resolveProfileEntity($feedItem);
        if (!$profileEntity) {
            return $this;
        }

        $uniqueIdentifier = (string) $feedItem->getUniqueIdentifier();

        $entity = $this->itemRepository->findOneByProfileAndUniqueIdentifier($profileEntity, $uniqueIdentifier);

        if (!$entity) {
            $entity = new ItemEntity();
            $entity
                ->setProfile($profileEntity)
                ->setUniqueIdentifier($uniqueIdentifier);
        }

        $dateTime = $feedItem->getDateTime();
        $entity
            ->setPermalink($feedItem->getPermalink() ?: null)
            ->setTitle($feedItem->getTitle() ?: null)
            ->setText((string) $feedItem->getText())
            ->setDateTime($dateTime ? \DateTimeImmutable::createFromInterface($dateTime) : new \DateTimeImmutable())
            ->setHidden((bool) $feedItem->getHidden())
            ->setDeleted((bool) $feedItem->getDeleted())
            ->setRaw($feedItem->getRaw());

        $this->entityManager->persist($entity);

        return $this;
    }

    public function flush(): FeedItemPersisterInterface
    {
        $this->entityManager->flush();

        return $this;
    }

    private function resolveProfileEntity(ItemModel $feedItem): ?ProfileEntity
    {
        $profileId = $feedItem->getProfileId();
        if (!$profileId) {
            return null;
        }

        /** @var ProfileEntity|null $entity */
        $entity = $this->profileRepository->find($profileId);

        return $entity;
    }
}

