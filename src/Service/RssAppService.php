<?php declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RssAppService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $rssAppApiKey,
        private readonly string $rssAppApiSecret,
    ) {
    }

    public function createFeed(string $url): array
    {
        $response = $this->httpClient->request('POST', 'https://api.rss.app/v1/feeds', [
            'headers' => [
                'Authorization' => sprintf('Bearer %s:%s', $this->rssAppApiKey, $this->rssAppApiSecret),
                'Content-Type' => 'application/json',
            ],
            'json' => ['url' => $url],
        ]);

        return $response->toArray();
    }

    public function deleteFeed(string $feedId): void
    {
        $this->httpClient->request('DELETE', sprintf('https://api.rss.app/v1/feeds/%s', $feedId), [
            'headers' => [
                'Authorization' => sprintf('Bearer %s:%s', $this->rssAppApiKey, $this->rssAppApiSecret),
            ],
        ]);
    }
}
