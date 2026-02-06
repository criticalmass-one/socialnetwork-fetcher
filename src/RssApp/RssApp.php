<?php declare(strict_types=1);

namespace App\RssApp;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RssApp implements RssAppInterface
{
    private readonly string $bearer;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $rssAppApiKey,
        string $rssAppApiSecret,
    ) {
        $this->bearer = sprintf('Bearer %s:%s', $rssAppApiKey, $rssAppApiSecret);
    }

    public function getItems(string $feedId, int $count = 100): array
    {
        $url = sprintf('https://api.rss.app/v1/feeds/%s?count=%d', $feedId, $count);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['Authorization' => $this->bearer]
        ]);

        $data = $response->toArray();

        return $data['items'] ?? [];
    }

    public function feedExists(string $feedId): bool
    {
        $url = sprintf('https://api.rss.app/v1/feeds/%s', $feedId);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['Authorization' => $this->bearer]
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function findRssAppFeedIdBySourceUrl(string $sourceUrl): ?string
    {
        $offset = 0;
        $limit = 100;

        do {
            $response = $this->httpClient->request('GET', "https://api.rss.app/v1/feeds?limit=$limit&offset=$offset", [
                'headers' => ['Authorization' => $this->bearer]
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

    public function createFeed(string $url): array
    {
        $response = $this->httpClient->request('POST', 'https://api.rss.app/v1/feeds', [
            'headers' => [
                'Authorization' => $this->bearer,
                'Content-Type' => 'application/json',
            ],
            'json' => ['url' => $url],
        ]);

        return $response->toArray();
    }

    public function deleteFeed(string $feedId): void
    {
        $this->httpClient->request('DELETE', sprintf('https://api.rss.app/v1/feeds/%s', $feedId), [
            'headers' => ['Authorization' => $this->bearer],
        ]);
    }
}
