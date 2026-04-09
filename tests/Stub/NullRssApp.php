<?php declare(strict_types=1);

namespace App\Tests\Stub;

use App\RssApp\RssAppInterface;

class NullRssApp implements RssAppInterface
{
    public function getItems(string $feedId, int $count = 100): array
    {
        return [];
    }

    public function feedExists(string $feedId): bool
    {
        return false;
    }

    public function findRssAppFeedIdBySourceUrl(string $sourceUrl): ?string
    {
        return null;
    }

    public function listFeeds(): array
    {
        return [];
    }

    public function createFeed(string $url): array
    {
        return ['id' => 'null-feed-id'];
    }

    public function deleteFeed(string $feedId): void
    {
    }
}
