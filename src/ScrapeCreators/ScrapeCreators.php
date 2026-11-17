<?php declare(strict_types=1);

namespace App\ScrapeCreators;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for https://api.scrapecreators.com – a paid scraper API that returns
 * Instagram's and Threads' own JSON. One request costs one credit; failed
 * requests (unknown, private or deleted profiles) are not charged.
 */
class ScrapeCreators implements ScrapeCreatorsInterface
{
    private const BASE_URI = 'https://api.scrapecreators.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $scrapeCreatorsApiKey = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->scrapeCreatorsApiKey !== '';
    }

    public function getInstagramPosts(string $handle): array
    {
        return $this->getInstagramPostsPage($handle)->posts;
    }

    public function getInstagramPostsPage(string $handle, ?string $nextMaxId = null): PostPage
    {
        $query = ['handle' => $handle];

        if ($nextMaxId !== null && $nextMaxId !== '') {
            $query['next_max_id'] = $nextMaxId;
        }

        $data = $this->request('/v2/instagram/user/posts', $query);
        $cursor = $data['next_max_id'] ?? null;
        $more = ($data['more_available'] ?? true) !== false;

        return new PostPage(
            $this->extractList($data, 'items'),
            $more && is_string($cursor) && $cursor !== '' ? $cursor : null,
        );
    }

    public function getThreadsPosts(string $handle): array
    {
        return $this->extractList($this->request('/v1/threads/user/posts', ['handle' => $handle]), 'posts');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractList(array $data, string $listKey): array
    {
        $list = $data[$listKey] ?? [];

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function request(string $path, array $query): array
    {
        if (!$this->isConfigured()) {
            throw new ScrapeCreatorsException('Kein API-Schlüssel gesetzt (SCRAPECREATORS_API_KEY).');
        }

        try {
            $response = $this->httpClient->request('GET', self::BASE_URI . $path, [
                'headers' => ['x-api-key' => $this->scrapeCreatorsApiKey],
                'query' => $query,
                'timeout' => 60,
            ]);

            $status = $response->getStatusCode();
            $data = json_decode($response->getContent(false), true);
        } catch (ExceptionInterface $e) {
            throw new ScrapeCreatorsException($e->getMessage(), 0, $e);
        }

        if (!is_array($data)) {
            throw new ScrapeCreatorsException(sprintf('HTTP %d, keine JSON-Antwort', $status));
        }

        // Errors come back as JSON too, partly with "success": true (e.g. a
        // private profile: HTTP 404, error "not_found", message "Profile is restricted").
        if ($status >= 400 || isset($data['error']) || ($data['success'] ?? true) === false) {
            throw new ScrapeCreatorsException(sprintf(
                'HTTP %d: %s',
                $status,
                trim(($data['error'] ?? '') . ' ' . ($data['message'] ?? '')) ?: 'unbekannter Fehler',
            ));
        }

        return $data;
    }
}
