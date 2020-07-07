<?php declare(strict_types=1);

namespace App\FeedFetcher;

use App\FeedFetcher\NetworkFeedFetcher\NetworkFeedFetcherInterface;
use App\FeedItemPersister\FeedItemPersisterInterface;
use App\Entity\SocialNetworkProfile;
use Doctrine\Persistence\ManagerRegistry;

abstract class AbstractFeedFetcher implements FeedFetcherInterface
{
    protected array $networkFetcherList = [];

    protected array $fetchableNetworkList = [];

    protected ManagerRegistry $doctrine;

    protected array $feedItemList = [];

    protected FeedItemPersisterInterface $feedItemPersister;

    public function __construct(ManagerRegistry $doctrine, FeedItemPersisterInterface $feedItemPersister)
    {
        $this->doctrine = $doctrine;
        $this->feedItemPersister = $feedItemPersister;
    }

    public function addNetworkFeedFetcher(NetworkFeedFetcherInterface $networkFeedFetcher): FeedFetcherInterface
    {
        $this->networkFetcherList[] = $networkFeedFetcher;

        return $this;
    }

    public function addFetchableNetwork(string $network): FeedFetcherInterface
    {
        $this->fetchableNetworkList[] = $network;

        return $this;
    }

    public function getNetworkFetcherList(): array
    {
        return $this->networkFetcherList;
    }

    protected function getSocialNetworkProfiles(FetchInfo $fetchInfo): array
    {
        return $this
            ->doctrine
            ->getRepository(SocialNetworkProfile::class)
            ->findByFetchInfo($fetchInfo);
    }

    public function getFeedItemList(): array
    {
        return $this->feedItemList;
    }
}
