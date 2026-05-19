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
        $url = sprintf('https://api.rss.app/v1/feeds/%s?limit=%d', $feedId, $count);

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

    public function listFeeds(): array
    {
        $allFeeds = [];
        $offset = 0;
        $limit = 100;

        do {
            $response = $this->httpClient->request('GET', "https://api.rss.app/v1/feeds?limit=$limit&offset=$offset", [
                'headers' => ['Authorization' => $this->bearer]
            ]);

            $data = $response->toArray();
            $feeds = $data['data'] ?? [];

            foreach ($feeds as $feed) {
                $allFeeds[] = $feed;
            }

            $offset += $limit;
        } while (($data['total'] ?? 0) > $offset);

        return $allFeeds;
    }

    public function findRssAppFeedIdBySourceUrl(string $sourceUrl): ?string
    {
        foreach ($this->listFeeds() as $feed) {
            if (($feed['source_url'] ?? '') === $sourceUrl) {
                return $feed['id'];
            }
        }

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

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            // Pull the body without triggering toArray's auto-throw, so we can surface
            // RSS.app's actual error message (e.g. "Unsupported source URL") instead of
            // a generic "HTTP 400 returned" from Symfony's HttpClient.
            $body = $response->getContent(throw: false);
            throw new RssAppException(sprintf(
                'RSS.app rejected URL "%s" with HTTP %d: %s',
                $url,
                $statusCode,
                $this->extractMessage($body) ?: 'no body',
            ));
        }

        return $response->toArray();
    }

    private function extractMessage(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            foreach (['message', 'error', 'detail', 'description'] as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key])) {
                    return $decoded[$key];
                }
            }
        }

        // Fall back to the raw body, trimmed to keep flash messages readable.
        return mb_substr(trim($body), 0, 200);
    }

    public function deleteFeed(string $feedId): void
    {
        $this->httpClient->request('DELETE', sprintf('https://api.rss.app/v1/feeds/%s', $feedId), [
            'headers' => ['Authorization' => $this->bearer],
        ]);
    }
}
