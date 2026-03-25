<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Bluesky;

use App\FeedFetcher\FetchInfo;
use App\Model\Profile;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BlueskyFeedFetcher extends AbstractNetworkFeedFetcher
{
    private const API_BASE_URL = 'https://public.api.bsky.app/xrpc';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    public function getNetworkIdentifier(): string
    {
        return 'bluesky_profile';
    }

    public function fetch(Profile $profile, FetchInfo $fetchInfo): array
    {
        $handle = IdentifierParser::parse($profile);

        if (!$handle) {
            $this->markAsFailed($profile, 'Bluesky-Handle konnte nicht aus Identifier ermittelt werden: ' . $profile->getIdentifier());
            return [];
        }

        try {
            $feedData = $this->fetchFeed($handle, $fetchInfo->getCount());

            return $this->convertFeed($profile, $feedData);
        } catch (\Exception $exception) {
            $this->markAsFailed($profile, sprintf('Failed to fetch Bluesky profile %d: %s', $profile->getId(), $exception->getMessage()));

            return [];
        }
    }

    private function fetchFeed(string $handle, int $count): array
    {
        $url = sprintf('%s/app.bsky.feed.getAuthorFeed?actor=%s&limit=%d', self::API_BASE_URL, urlencode($handle), $count);

        $response = $this->httpClient->request('GET', $url)->toArray();

        return $response['feed'] ?? [];
    }

    private function convertFeed(Profile $profile, array $feedData): array
    {
        $feedItemList = [];

        foreach ($feedData as $entry) {
            $feedItem = EntryConverter::convert($profile, $entry);

            if ($feedItem) {
                $feedItemList[] = $feedItem;
            }
        }

        return $feedItemList;
    }
}
