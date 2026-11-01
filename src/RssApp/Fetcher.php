<?php declare(strict_types=1);

namespace App\RssApp;

use App\FeedFetcher\FetchInfo;
use App\Model\Profile;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use Psr\Log\LoggerInterface;

abstract class Fetcher extends AbstractNetworkFeedFetcher
{
    public function __construct(
        LoggerInterface $logger,
        private readonly RssAppInterface $rssApp
    ) {
        parent::__construct($logger);
    }

    public function fetch(Profile $profile, FetchInfo $fetchInfo): array
    {
        $sourceUrl = $profile->getIdentifier();
        if (!$sourceUrl) {
            $this->markAsFailed($profile, 'Kein Identifier vorhanden.');
            return [];
        }

        $feedId = $profile->getRssAppFeedId();

        if ($feedId && !$this->rssApp->feedExists($feedId)) {
            $this->logger->notice(sprintf('RSS.app Feed %s existiert nicht mehr für %s, suche neu.', $feedId, $sourceUrl));
            $feedId = null;
            $profile->setRssAppFeedId(null);
        }

        if (!$feedId) {
            $feedId = $this->rssApp->findRssAppFeedIdBySourceUrl($sourceUrl);
            if ($feedId) {
                $profile->setRssAppFeedId($feedId);
            } else {
                $this->markAsFailed($profile, 'Kein Feed bei RSS.app gefunden für ' . $sourceUrl);
                return [];
            }
        }

        try {
            $feed = $this->rssApp->getFeed($feedId, $fetchInfo->getCount());

            // RSS.app keeps serving a deactivated feed with HTTP 200 and its last
            // known items, so without this check the profile would silently look
            // healthy while no new items ever arrive.
            if (($feed['is_active'] ?? true) === false) {
                $this->markAsFailed($profile, sprintf(
                    'RSS.app hat den Feed %s für %s deaktiviert (is_active=false) – es kommen keine neuen Beiträge mehr. Feed bei RSS.app prüfen bzw. neu anlegen: https://rss.app/r/feed/%s',
                    $feedId,
                    $sourceUrl,
                    $feedId,
                ));
            }

            $feedItemList = [];

            foreach ($feed['items'] ?? [] as $item) {
                $feedItem = Converter::convert($profile, $item);

                if ($feedItem) {
                    $feedItemList[] = $feedItem;
                }
            }

            return $feedItemList;

        } catch (\Throwable $e) {
            $this->markAsFailed($profile, sprintf(
                'RSS.app-Fehler für %s (Feed-ID: %s, URL: https://api.rss.app/v1/feeds/%s): %s',
                $sourceUrl,
                $feedId,
                $feedId,
                $e->getMessage(),
            ));
            return [];
        }
    }
}