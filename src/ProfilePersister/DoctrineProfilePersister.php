<?php declare(strict_types=1);

namespace App\ProfilePersister;

use App\Entity\Network;
use App\Entity\Profile as ProfileEntity;
use App\Model\SocialNetworkProfile as SocialNetworkProfileModel;
use App\Repository\ProfileRepository;
use App\Repository\NetworkRepository;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineProfilePersister implements ProfilePersisterInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProfileRepository $repository,
        private readonly NetworkRepository $networkRepository,
    ) {
    }

    public function persistProfile(SocialNetworkProfileModel $socialNetworkProfile): SocialNetworkProfileModel
    {
        $id = $socialNetworkProfile->getId();
        $networkName = (string) $socialNetworkProfile->getNetwork();
        $identifier = (string) $socialNetworkProfile->getIdentifier();

        $network = $this->networkRepository->findOneByName($networkName);

        if (!$network) {
            throw new \RuntimeException(sprintf('Network "%s" not found', $networkName));
        }

        $entity = null;

        if ($id) {
            $entity = $this->repository->find($id);
        }

        if (!$entity && $network && $identifier) {
            $entity = $this->repository->findOneByNetworkAndIdentifier($network, $identifier);
        }

        if (!$entity) {
            $entity = new ProfileEntity();
            if ($id) {
                $entity->setId($id);
            }
            $entity
                ->setNetwork($network)
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
