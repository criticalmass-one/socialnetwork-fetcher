<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Homepage;

use App\FeedFetcher\FetchInfo;
use App\Http\UrlGuard;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use App\Model\Profile;
use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Reader;
use Psr\Log\LoggerInterface;

class HomepageFeedFetcher extends AbstractNetworkFeedFetcher
{
    public function __construct(
        LoggerInterface $logger,
        private readonly EntryConverter $entryConverter,
        private readonly UrlGuard $urlGuard,
    ) {
        parent::__construct($logger);
    }

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

        // The identifier is client-supplied and the feed link is taken from the
        // page it points at — both must stay outside our own network.
        $this->urlGuard->resolve((string) $profile->getIdentifier());

        $feedLink = FeedUriDetector::findFeedLink($profile);

        if (!$feedLink) {
            return [];
        }

        $this->urlGuard->resolve($feedLink);

        $this->logger->info(sprintf('Now quering %s', $feedLink));

        $feed = Reader::import($feedLink);

        $fetchSource = $profile->isFetchSource();

        /** @var EntryInterface $entry */
        foreach ($feed as $entry) {
            $feedItem = $this->entryConverter->convert($profile, $entry, $fetchSource);

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
            ->setLastFetchFailureDateTime(new \DateTimeImmutable())
            ->setLastFetchFailureError($errorMessage);

        $this
            ->logger
            ->notice($errorMessage);

        return $profile;
    }
}
