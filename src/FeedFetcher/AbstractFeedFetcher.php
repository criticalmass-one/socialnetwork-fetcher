<?php declare(strict_types=1);

namespace App\FeedFetcher;

use App\NetworkFeedFetcher\NetworkFeedFetcherInterface;
use App\FeedItemPersister\FeedItemPersisterInterface;
use App\ProfileFetcher\ProfileFetcherInterface;
use App\ProfilePersister\ProfilePersisterInterface;

abstract class AbstractFeedFetcher implements FeedFetcherInterface
{
    protected array $networkFetcherList = [];

    protected array $fetchableNetworkList = [];

    protected array $feedItemList = [];

    protected FeedItemPersisterInterface $feedItemPersister;

    protected ProfileFetcherInterface $profileFetcher;

    protected ProfilePersisterInterface $profilePersister;

    public function __construct(FeedItemPersisterInterface $feedItemPersister, ProfileFetcherInterface $profileFetcher, ProfilePersisterInterface $profilePersister)
    {
        $this->feedItemPersister = $feedItemPersister;
        $this->profileFetcher = $profileFetcher;
        $this->profilePersister = $profilePersister;
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
        return $this->profileFetcher->fetchByFetchInfo($fetchInfo);
    }

    public function getFeedItemList(): array
    {
        return $this->feedItemList;
    }
}
