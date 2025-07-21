<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Instagram;

use App\FeedFetcher\FetchInfo;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use App\Model\SocialNetworkProfile;
use InstagramScraper\Exception\InstagramNotFoundException;
use InstagramScraper\Instagram;
use InstagramScraper\Model\Media;
use Psr\Log\LoggerInterface;

class InstagramFeedFetcher extends AbstractNetworkFeedFetcher
{
    protected Instagram $instagram;

    public function __construct(
        LoggerInterface $logger,
        private HttpClientInterface $httpClient
    ) {
        parent::__construct($logger);
    }

    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array
    {
        $sourceUrl = $socialNetworkProfile->getIdentifier();
        if (!$sourceUrl) {
            $this->markAsFailed($socialNetworkProfile, 'Kein identifier (Instagram-URL) vorhanden.');
            return [];
        }

        $apiKey = $_ENV['RSS_APP_API_KEY'] ?? null;
        $apiSecret = $_ENV['RSS_APP_API_SECRET'] ?? null;
        $bearer = 'Bearer ' . $apiKey . ':' . $apiSecret;

        $additionalData = json_decode($socialNetworkProfile->getAdditionalData() ?? '{}', true);

        $feedId = $additionalData['rss_feed_id'] ?? null;

        if ($feedId && !$this->feedExists($feedId, $bearer)) {
            $feedId = null;
            unset($additionalData['rss_feed_id']);
        }

        if (!$feedId) {
            $feedId = $this->findRssAppFeedIdBySourceUrl($sourceUrl, $bearer);
            if ($feedId) {
                $additionalData['rss_feed_id'] = $feedId;
                $socialNetworkProfile->setAdditionalData(json_encode($additionalData, JSON_UNESCAPED_SLASHES));
                // Wichtig: Entity muss später persistiert werden!
            } else {
                $this->markAsFailed($socialNetworkProfile, 'Kein Feed bei RSS.app gefunden für ' . $sourceUrl);
                return [];
            }
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.rss.app/v1/feeds/' . $feedId, [
                'headers' => ['Authorization' => $bearer]
            ]);

            $data = $response->toArray();
            $items = $data['items'] ?? [];

            $feedItemList = [];

            foreach ($items as $item) {
                $feedItem = RssAppMediaConverter::convert($socialNetworkProfile, $item);
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

    private function feedExists(string $feedId, string $bearer): bool
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.rss.app/v1/feeds/' . $feedId, [
                'headers' => ['Authorization' => $bearer]
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    private function findRssAppFeedIdBySourceUrl(string $sourceUrl, string $bearer): ?string
    {
        $offset = 0;
        $limit = 100;

        do {
            $response = $this->httpClient->request('GET', "https://api.rss.app/v1/feeds?limit=$limit&offset=$offset", [
                'headers' => ['Authorization' => $bearer]
            ]);

            $data = $response->toArray();
            $feeds = $data['data'] ?? [];

            foreach ($feeds as $feed) {
                if (($feed['source_url'] ?? '') === $sourceUrl) {
                    return $feed['id'];
                }
            }

            $offset += $limit;
        } while (($data['total'] ?? 0) > $offset);

        return null;
    }


    public function getNetworkIdentifier(): string
    {
        return 'instagram_profile';
    }
}
