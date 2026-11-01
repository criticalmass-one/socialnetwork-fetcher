<?php declare(strict_types=1);

namespace App\RssApp;

interface RssAppInterface
{
    public function getItems(string $feedId, int $count = 100): array;

    /**
     * Returns the full feed resource (incl. `is_active` and `items`).
     *
     * RSS.app deactivates feeds whose source it can no longer scrape. Such a
     * feed still answers with HTTP 200 and its last known items, but
     * `is_active` is false and no new items will ever show up.
     */
    public function getFeed(string $feedId, int $count = 100): array;
    public function feedExists(string $feedId): bool;
    public function findRssAppFeedIdBySourceUrl(string $sourceUrl): ?string;
    public function listFeeds(): array;
    public function createFeed(string $url): array;
    public function deleteFeed(string $feedId): void;
}