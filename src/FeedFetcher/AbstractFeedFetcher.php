<?php declare(strict_types=1);

namespace App\FeedFetcher;

use App\NetworkFeedFetcher\NetworkFeedFetcherInterface;
use App\FeedItemPersister\FeedItemPersisterInterface;
use App\ProfileFetcher\ProfileFetcherInterface;
use App\ProfilePersister\ProfilePersisterInterface;
use App\SourceFetcher\SourceFetcher;

abstract class AbstractFeedFetcher implements FeedFetcherInterface
{
    protected array $networkFetcherList = [];

    protected array $fetchableNetworkList = [];

    protected array $feedItemList = [];

    protected FeedItemPersisterInterface $feedItemPersister;

    protected ProfileFetcherInterface $profileFetcher;

    protected ProfilePersisterInterface $profilePersister;

    protected SourceFetcher $sourceFetcher;

    public function __construct(FeedItemPersisterInterface $feedItemPersister, ProfileFetcherInterface $profileFetcher, ProfilePersisterInterface $profilePersister, SourceFetcher $sourceFetcher)
    {
        $this->feedItemPersister = $feedItemPersister;
        $this->profileFetcher = $profileFetcher;
        $this->profilePersister = $profilePersister;
        $this->sourceFetcher = $sourceFetcher;
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

    protected function getProfiles(FetchInfo $fetchInfo): array
    {
        // A profile with autoFetch switched off stays listed and editable, but is not fetched.
        return array_values(array_filter(
            $this->profileFetcher->fetchByFetchInfo($fetchInfo),
            static fn ($profile): bool => false !== $profile->getAutoFetch(),
        ));
    }

    public function getFeedItemList(): array
    {
        return $this->feedItemList;
    }
}
