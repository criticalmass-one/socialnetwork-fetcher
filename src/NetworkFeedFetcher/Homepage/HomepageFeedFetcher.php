<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Homepage;

use App\FeedFetcher\FetchInfo;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use App\Model\SocialNetworkProfile;
use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Reader;

class HomepageFeedFetcher extends AbstractNetworkFeedFetcher
{
    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array
    {
        try {
            return $this->fetchFeed($socialNetworkProfile);
        } catch (\Exception $exception) {
            $this->markAsFailed($socialNetworkProfile, sprintf('Failed to fetch social network profile %d: %s', $socialNetworkProfile->getId(), $exception->getMessage()));

            return [];
        }
    }

    protected function fetchFeed(SocialNetworkProfile $socialNetworkProfile): array
    {
        $feedItemList = [];

        $feedLink = FeedUriDetector::findFeedLink($socialNetworkProfile);

        if (!$feedLink) {
            return [];
        }

        $this->logger->info(sprintf('Now quering %s', $feedLink));

        $feed = Reader::import($feedLink);

        /** @var EntryInterface $entry */
        foreach ($feed as $entry) {
            $feedItem = EntryConverter::convert($socialNetworkProfile, $entry);

            if ($feedItem) {
                $feedItemList[] = $feedItem;

                $this->logger->info(sprintf('Fetched website %s', $feedItem->getPermalink()));
            }
        }

        return $feedItemList;
    }

    protected function markAsFailed(SocialNetworkProfile $socialNetworkProfile, string $errorMessage): SocialNetworkProfile
    {
        $socialNetworkProfile
            ->setLastFetchFailureDateTime(new \DateTime())
            ->setLastFetchFailureError($errorMessage);

        $this
            ->logger
            ->notice($errorMessage);

        return $socialNetworkProfile;
    }
}
