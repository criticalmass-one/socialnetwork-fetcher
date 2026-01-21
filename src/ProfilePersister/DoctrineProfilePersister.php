<?php declare(strict_types=1);

namespace App\ProfilePersister;

use App\Entity\SocialNetwork;
use App\Entity\SocialNetworkProfile as SocialNetworkProfileEntity;
use App\Model\SocialNetworkProfile as SocialNetworkProfileModel;
use App\Repository\SocialNetworkProfileRepository;
use App\Repository\SocialNetworkRepository;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineProfilePersister implements ProfilePersisterInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SocialNetworkProfileRepository $repository,
        private readonly SocialNetworkRepository $socialNetworkRepository,
    ) {
    }

    public function persistProfile(SocialNetworkProfileModel $socialNetworkProfile): SocialNetworkProfileModel
    {
        $id = $socialNetworkProfile->getId();
        $networkName = (string) $socialNetworkProfile->getNetwork();
        $identifier = (string) $socialNetworkProfile->getIdentifier();

        $socialNetwork = $this->socialNetworkRepository->findOneByName($networkName);

        if (!$socialNetwork) {
            throw new \RuntimeException(sprintf('SocialNetwork "%s" not found', $networkName));
        }

        $entity = null;

        if ($id) {
            $entity = $this->repository->find($id);
        }

        if (!$entity && $socialNetwork && $identifier) {
            $entity = $this->repository->findOneBySocialNetworkAndIdentifier($socialNetwork, $identifier);
        }

        if (!$entity) {
            $entity = new SocialNetworkProfileEntity();
            if ($id) {
                $entity->setId($id);
            }
            $entity
                ->setSocialNetwork($socialNetwork)
                ->setIdentifier($identifier)
                ->setCreatedAt($socialNetworkProfile->getCreatedAt() ? \DateTimeImmutable::createFromInterface($socialNetworkProfile->getCreatedAt()) : new \DateTimeImmutable());
        }

        $additionalData = $socialNetworkProfile->getAdditionalData();

        $entity
            ->setAutoPublish($socialNetworkProfile->isAutoPublish())
            ->setAutoFetch((bool) $socialNetworkProfile->getAutoFetch())
            ->setLastFetchSuccessDateTime($socialNetworkProfile->getLastFetchSuccessDateTime() ? \DateTimeImmutable::createFromInterface($socialNetworkProfile->getLastFetchSuccessDateTime()) : null)
            ->setLastFetchFailureDateTime($socialNetworkProfile->getLastFetchFailureDateTime() ? \DateTimeImmutable::createFromInterface($socialNetworkProfile->getLastFetchFailureDateTime()) : null)
            ->setLastFetchFailureError($socialNetworkProfile->getLastFetchFailureError())
            ->setAdditionalData($additionalData ? (array) json_decode($additionalData, true) : null);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $socialNetworkProfile;
    }
}
