<?php declare(strict_types=1);

namespace App\RssApp;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class RssApp implements RssAppInterface
{
    /** Total attempts (1 initial + retries) for a rate-limited (429) request. */
    private const MAX_ATTEMPTS = 4;

    /** Never block a request longer than this per wait — surface the 429 instead. */
    private const MAX_RETRY_WAIT_SECONDS = 8.0;

    private readonly string $bearer;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $rssAppApiKey,
        string $rssAppApiSecret,
    ) {
        $this->bearer = sprintf('Bearer %s:%s', $rssAppApiKey, $rssAppApiSecret);
    }

    /**
     * RSS.app enforces a per-window API rate limit; registering a profile alone
     * fans out into several calls (paginated feed listing + create), so a burst
     * can trip a 429. Retry a rate-limited request a few times with backoff,
     * honouring a numeric Retry-After when present, but give up (returning the
     * 429 response) rather than blocking the caller for too long.
     *
     * @param array<string, mixed> $options
     */
    private function requestWithRetry(string $method, string $url, array $options): ResponseInterface
    {
        for ($attempt = 1; ; ++$attempt) {
            $response = $this->httpClient->request($method, $url, $options);

            // getStatusCode() forces the transfer, so a 429 is detectable here.
            if ($response->getStatusCode() !== 429 || $attempt >= self::MAX_ATTEMPTS) {
                return $response;
            }

            $wait = $this->retryDelaySeconds($response, $attempt);
            if ($wait > self::MAX_RETRY_WAIT_SECONDS) {
                // Would block the caller too long — surface the 429 instead.
                return $response;
            }

            if ($wait > 0.0) {
                usleep((int) ($wait * 1_000_000));
            }
        }
    }

    private function retryDelaySeconds(ResponseInterface $response, int $attempt): float
    {
        $headers = $response->getHeaders(throw: false);
        $retryAfter = $headers['retry-after'][0] ?? null;

        if ($retryAfter !== null && is_numeric($retryAfter)) {
            return (float) $retryAfter;
        }

        // Exponential backoff: 1s, 2s, 4s, ...
        return (float) (2 ** ($attempt - 1));
    }

    public function getItems(string $feedId, int $count = 100): array
    {
        $url = sprintf('https://api.rss.app/v1/feeds/%s?limit=%d', $feedId, $count);

        $response = $this->requestWithRetry('GET', $url, [
            'headers' => ['Authorization' => $this->bearer]
        ]);

        $data = $response->toArray();

        return $data['items'] ?? [];
    }

    public function feedExists(string $feedId): bool
    {
        $url = sprintf('https://api.rss.app/v1/feeds/%s', $feedId);

        try {
            $response = $this->requestWithRetry('GET', $url, [
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
            $response = $this->requestWithRetry('GET', "https://api.rss.app/v1/feeds?limit=$limit&offset=$offset", [
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
        $response = $this->requestWithRetry('POST', 'https://api.rss.app/v1/feeds', [
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
        $this->requestWithRetry('DELETE', sprintf('https://api.rss.app/v1/feeds/%s', $feedId), [
            'headers' => ['Authorization' => $this->bearer],
        ]);
    }
}
