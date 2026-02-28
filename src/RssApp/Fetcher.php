<?php declare(strict_types=1);

namespace App\RssApp;

use App\FeedFetcher\FetchInfo;
use App\Model\SocialNetworkProfile;
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

    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array
    {
        $sourceUrl = $socialNetworkProfile->getIdentifier();
        if (!$sourceUrl) {
            $this->markAsFailed($socialNetworkProfile, 'Kein Identifier vorhanden.');
            return [];
        }

        $additionalData = json_decode($socialNetworkProfile->getAdditionalData() ?? '{}', true);

        $feedId = $additionalData['rss_feed_id'] ?? null;

        if ($feedId && !$this->rssApp->feedExists($feedId)) {
            $feedId = null;
            unset($additionalData['rss_feed_id']);
        }

        if (!$feedId) {
            $feedId = $this->rssApp->findRssAppFeedIdBySourceUrl($sourceUrl);
            if ($feedId) {
                $additionalData['rss_feed_id'] = $feedId;
                $socialNetworkProfile->setAdditionalData(json_encode($additionalData, JSON_UNESCAPED_SLASHES));
            } else {
                $this->markAsFailed($socialNetworkProfile, 'Kein Feed bei RSS.app gefunden für ' . $sourceUrl);
                return [];
            }
        }

        try {
            $items = $this->rssApp->getItems($feedId, $fetchInfo->getCount());

            $feedItemList = [];

            foreach ($items as $item) {
                $feedItem = Converter::convert($socialNetworkProfile, $item);

                if ($feedItem) {
                    $feedItemList[] = $feedItem;
                }
            }

            return $feedItemList;

        } catch (\Throwable $e) {
            $this->markAsFailed($socialNetworkProfile, 'Fehler bei RSS.app: ' . $e->getMessage());
            return [];
        }
    }
}