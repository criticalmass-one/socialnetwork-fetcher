<?php declare(strict_types=1);

namespace App\FeedItemPersister;

use App\Entity\SocialNetworkFeedItem as SocialNetworkFeedItemEntity;
use App\Entity\SocialNetworkProfile as SocialNetworkProfileEntity;
use App\FeedFetcher\FetchResult;
use App\Model\SocialNetworkFeedItem as SocialNetworkFeedItemModel;
use App\Repository\SocialNetworkFeedItemRepository;
use App\Repository\SocialNetworkProfileRepository;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineFeedItemPersister implements FeedItemPersisterInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SocialNetworkProfileRepository $profileRepository,
        private readonly SocialNetworkFeedItemRepository $feedItemRepository,
    ) {
    }

    public function persistFeedItemList(array $feedItemList, ?FetchResult $fetchResult): FeedItemPersisterInterface
    {
        foreach ($feedItemList as $feedItem) {
            if ($feedItem instanceof SocialNetworkFeedItemModel) {
                $this->persistFeedItem($feedItem, $fetchResult);
            }
        }

        return $this;
    }

    public function persistFeedItem(SocialNetworkFeedItemModel $feedItem, ?FetchResult $fetchResult): FeedItemPersisterInterface
    {
        $profileEntity = $this->resolveProfileEntity($feedItem);
        if (!$profileEntity) {
            // Ohne Profil können wir das Item nicht sinnvoll persistieren.
            return $this;
        }

        $uniqueIdentifier = (string) $feedItem->getUniqueIdentifier();

        $entity = $this->feedItemRepository->findOneByProfileAndUniqueIdentifier($profileEntity, $uniqueIdentifier);

        if (!$entity) {
            $entity = new SocialNetworkFeedItemEntity();
            $entity
                ->setSocialNetworkProfile($profileEntity)
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

    private function resolveProfileEntity(SocialNetworkFeedItemModel $feedItem): ?SocialNetworkProfileEntity
    {
        $profileId = $feedItem->getSocialNetworkProfileId();
        if (!$profileId) {
            return null;
        }

        // Wir kennen aktuell nur die externe ID; Profile werden separat über DoctrineProfilePersister upserted.
        // Daher versuchen wir hier den direkten PK-Lookup.
        /** @var SocialNetworkProfileEntity|null $entity */
        $entity = $this->profileRepository->find($profileId);

        return $entity;
    }
}

