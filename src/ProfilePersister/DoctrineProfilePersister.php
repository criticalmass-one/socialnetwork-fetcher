<?php declare(strict_types=1);

namespace App\ProfilePersister;

use App\Entity\Network;
use App\Entity\Profile as ProfileEntity;
use App\Model\Profile as ProfileModel;
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

    public function persistProfile(ProfileModel $profile): ProfileModel
    {
        $id = $profile->getId();
        $networkName = (string) $profile->getNetwork();
        $identifier = (string) $profile->getIdentifier();

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
                ->setCreatedAt($profile->getCreatedAt() ? \DateTimeImmutable::createFromInterface($profile->getCreatedAt()) : new \DateTimeImmutable());
        }

        $additionalData = $profile->getAdditionalData();

        $entity
            ->setAutoPublish($profile->isAutoPublish())
            ->setAutoFetch((bool) $profile->getAutoFetch())
            ->setLastFetchSuccessDateTime($profile->getLastFetchSuccessDateTime() ? \DateTimeImmutable::createFromInterface($profile->getLastFetchSuccessDateTime()) : null)
            ->setLastFetchFailureDateTime($profile->getLastFetchFailureDateTime() ? \DateTimeImmutable::createFromInterface($profile->getLastFetchFailureDateTime()) : null)
            ->setLastFetchFailureError($profile->getLastFetchFailureError())
            ->setAdditionalData($additionalData ? (array) json_decode($additionalData, true) : null);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $profile;
    }
}
