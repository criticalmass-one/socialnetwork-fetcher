<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Bluesky;

use App\FeedFetcher\FetchInfo;
use App\Model\SocialNetworkProfile;
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
        return 'bluesky';
    }

    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array
    {
        $handle = IdentifierParser::parse($socialNetworkProfile);

        if (!$handle) {
            $this->markAsFailed($socialNetworkProfile, 'Bluesky-Handle konnte nicht aus Identifier ermittelt werden: ' . $socialNetworkProfile->getIdentifier());
            return [];
        }

        try {
            $feedData = $this->fetchFeed($handle, $fetchInfo->getCount());

            return $this->convertFeed($socialNetworkProfile, $feedData);
        } catch (\Exception $exception) {
            $this->markAsFailed($socialNetworkProfile, sprintf('Failed to fetch Bluesky profile %d: %s', $socialNetworkProfile->getId(), $exception->getMessage()));

            return [];
        }
    }

    private function fetchFeed(string $handle, int $count): array
    {
        $url = sprintf('%s/app.bsky.feed.getAuthorFeed?actor=%s&limit=%d', self::API_BASE_URL, urlencode($handle), $count);

        $response = $this->httpClient->request('GET', $url)->toArray();

        return $response['feed'] ?? [];
    }

    private function convertFeed(SocialNetworkProfile $socialNetworkProfile, array $feedData): array
    {
        $feedItemList = [];

        foreach ($feedData as $entry) {
            $feedItem = EntryConverter::convert($socialNetworkProfile, $entry);

            if ($feedItem) {
                $feedItemList[] = $feedItem;
            }
        }

        return $feedItemList;
    }
}
