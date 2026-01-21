<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Homepage;

use App\FeedFetcher\FetchInfo;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use App\Model\Profile;
use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Reader;

class HomepageFeedFetcher extends AbstractNetworkFeedFetcher
{
    public function fetch(Profile $profile, FetchInfo $fetchInfo): array
    {
        try {
            return $this->fetchFeed($profile);
        } catch (\Exception $exception) {
            $this->markAsFailed($profile, sprintf('Failed to fetch social network profile %d: %s', $profile->getId(), $exception->getMessage()));

            return [];
        }
    }

    protected function fetchFeed(Profile $profile): array
    {
        $feedItemList = [];

        $feedLink = FeedUriDetector::findFeedLink($profile);

        if (!$feedLink) {
            return [];
        }

        $this->logger->info(sprintf('Now quering %s', $feedLink));

        $feed = Reader::import($feedLink);

        /** @var EntryInterface $entry */
        foreach ($feed as $entry) {
            $feedItem = EntryConverter::convert($profile, $entry);

            if ($feedItem) {
                $feedItemList[] = $feedItem;

                $this->logger->info(sprintf('Fetched website %s', $feedItem->getPermalink()));
            }
        }

        return $feedItemList;
    }

    protected function markAsFailed(Profile $profile, string $errorMessage): Profile
    {
        $profile
            ->setLastFetchFailureDateTime(new \DateTime())
            ->setLastFetchFailureError($errorMessage);

        $this
            ->logger
            ->notice($errorMessage);

        return $profile;
    }
}
