<?php declare(strict_types=1);

namespace App\RssApp;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RssApp implements RssAppInterface
{
    private readonly string $bearer;

    public function __construct(
        private readonly HttpClientInterface $httpClient
    )
    {
        $apiKey = $_ENV['RSS_APP_API_KEY'] ?? null;
        $apiSecret = $_ENV['RSS_APP_API_SECRET'] ?? null;
        $this->bearer = sprintf('Bearer %s:%s', $apiKey, $apiSecret);
    }

    public function getItems(string $feedId, int $count = 100): array
    {
        $url = sprintf('https://api.rss.app/v1/feeds/%s?limit=%d', $feedId, $count);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['Authorization' => $this->bearer]
        ]);

        $data = $response->toArray();
        $items = $data['items'] ?? [];

        return $items;
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
}
