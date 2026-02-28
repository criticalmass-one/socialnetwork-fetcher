<?php declare(strict_types=1);

namespace App\ProfileFetcher;

use App\FeedFetcher\FetchInfo;
use App\Model\Profile as ModelProfile;
use App\Entity\Profile as EntityProfile;
use App\Repository\ProfileRepository;
use App\Repository\NetworkRepository;

class DoctrineProfileFetcher implements ProfileFetcherInterface
{
    public function __construct(
        private readonly ProfileRepository $profileRepository,
        private readonly NetworkRepository $networkRepository,
    ) {
    }

    public function fetchByNetworkIdentifier(string $networkIdentifier): array
    {
        $network = $this->networkRepository->findOneBy(['identifier' => $networkIdentifier]);

        if (!$network) {
            return [];
        }

        $entities = $this->profileRepository->findBy(['network' => $network]);

        return array_map($this->convertToModel(...), $entities);
    }

    public function fetchByNetworkIdentifiers(array $networkIdentifiers = []): array
    {
        $profileList = [];

        foreach ($networkIdentifiers as $networkIdentifier) {
            $profileList = array_merge($profileList, $this->fetchByNetworkIdentifier($networkIdentifier));
        }

        return $profileList;
    }

    public function fetchByFetchInfo(FetchInfo $fetchInfo): array
    {
        if ($fetchInfo->hasNetworkList()) {
            return $this->fetchByNetworkIdentifiers($fetchInfo->getNetworkList());
        }

        $entities = $this->profileRepository->findAll();

        return array_map($this->convertToModel(...), $entities);
    }

    private function convertToModel(EntityProfile $entity): ModelProfile
    {
        $model = new ModelProfile();
        $model->setId($entity->getId());
        $model->setIdentifier($entity->getIdentifier());
        $model->setNetwork($entity->getNetwork()->getIdentifier());
        $model->setAutoPublish($entity->isAutoPublish());
        $model->setAutoFetch($entity->isAutoFetch());

        if ($entity->getLastFetchSuccessDateTime()) {
            $model->setLastFetchSuccessDateTime(\DateTime::createFromInterface($entity->getLastFetchSuccessDateTime()));
        }

        if ($entity->getLastFetchFailureDateTime()) {
            $model->setLastFetchFailureDateTime(\DateTime::createFromInterface($entity->getLastFetchFailureDateTime()));
        }

        $model->setLastFetchFailureError($entity->getLastFetchFailureError());
        $model->setFetchSource($entity->isFetchSource());

        $additionalData = $entity->getAdditionalData();
        if ($additionalData) {
            $model->setAdditionalData(json_encode($additionalData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return $model;
    }
}
