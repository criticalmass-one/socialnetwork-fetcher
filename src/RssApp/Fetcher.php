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
            $items = $this->rssApp->getItems($feedId, $fetchInfo->getCount());

            $feedItemList = [];

            foreach ($items as $item) {
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