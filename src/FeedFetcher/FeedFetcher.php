<?php declare(strict_types=1);

namespace App\FeedFetcher;

use App\NetworkFeedFetcher\NetworkFeedFetcherInterface;
use App\Model\SocialNetworkProfile;

class FeedFetcher extends AbstractFeedFetcher
{
    protected function getFeedFetcherForNetworkProfile(SocialNetworkProfile $socialNetworkProfile): ?NetworkFeedFetcherInterface
    {
        /** @var NetworkFeedFetcherInterface $fetcher */
        foreach ($this->networkFetcherList as $fetcher) {
            if ($fetcher->supports($socialNetworkProfile)) {
                return $fetcher;
            }
        }

        return null;
    }

    public function fetch(FetchInfo $fetchInfo, callable $callback): FeedFetcherInterface
    {
        $profileList = $this->getSocialNetworkProfiles($fetchInfo);

        /** @var SocialNetworkProfile $profile */
        foreach ($profileList as $profile) {
            // Erst Profil upserten, damit es für FK-Lookups durch FeedItems existiert.
            $this->profilePersister->persistProfile($profile);

            $fetcher = $this->getFeedFetcherForNetworkProfile($profile);

            if ($fetcher) {
                $feedItemList = $fetcher->fetch($profile, $fetchInfo);

                $fetchResult = new FetchResult();
                $fetchResult
                    ->setSocialNetworkProfile($profile)
                    ->setCounterFetched(count($feedItemList));

                $this->feedItemPersister->persistFeedItemList($feedItemList, $fetchResult)->flush();

                $callback($fetchResult);
            }
        }

        return $this;
    }

    protected function stripNetworkList(FetchInfo $fetchInfo): FeedFetcher
    {
        if (count($this->fetchableNetworkList) === 0) {
            return $this;
        }

        /** @var NetworkFeedFetcherInterface $fetcher */
        foreach ($this->networkFetcherList as $key => $fetcher) {
            if (!in_array($fetcher->getNetworkIdentifier(), $this->fetchableNetworkList)) {
                unset($this->networkFetcherList[$key]);
            }
        }

        return $this;
    }

    public function persist(): FeedFetcherInterface
    {
        return $this;
    }
}
