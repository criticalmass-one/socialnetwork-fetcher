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
        return $this->request('/v2/instagram/user/posts', $handle, 'items');
    }

    public function getThreadsPosts(string $handle): array
    {
        return $this->request('/v1/threads/user/posts', $handle, 'posts');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function request(string $path, string $handle, string $listKey): array
    {
        if (!$this->isConfigured()) {
            throw new ScrapeCreatorsException('Kein API-Schlüssel gesetzt (SCRAPECREATORS_API_KEY).');
        }

        try {
            $response = $this->httpClient->request('GET', self::BASE_URI . $path, [
                'headers' => ['x-api-key' => $this->scrapeCreatorsApiKey],
                'query' => ['handle' => $handle],
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

        $list = $data[$listKey] ?? [];

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }
}
