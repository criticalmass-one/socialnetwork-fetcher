<?php declare(strict_types=1);

namespace App\FeedFetcher;

use App\NetworkFeedFetcher\NetworkFeedFetcherInterface;
use App\Model\Profile;

class FeedFetcher extends AbstractFeedFetcher
{
    protected function getFeedFetcherForProfile(Profile $profile): ?NetworkFeedFetcherInterface
    {
        /** @var NetworkFeedFetcherInterface $fetcher */
        foreach ($this->networkFetcherList as $fetcher) {
            if ($fetcher->supports($profile)) {
                return $fetcher;
            }
        }

        return null;
    }

    public function fetch(FetchInfo $fetchInfo, callable $callback): FeedFetcherInterface
    {
        $profileList = $this->getProfiles($fetchInfo);

        /** @var Profile $profile */
        foreach ($profileList as $profile) {
            // Erst Profil upserten, damit es für FK-Lookups durch FeedItems existiert.
            $this->profilePersister->persistProfile($profile);

            $fetcher = $this->getFeedFetcherForProfile($profile);

            if ($fetcher) {
                $feedItemList = $fetcher->fetch($profile, $fetchInfo);

                foreach ($feedItemList as $feedItem) {
                    $this->sourceFetcher->fetch($feedItem, $profile);
                }

                $fetchResult = new FetchResult();
                $fetchResult
                    ->setProfile($profile)
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
