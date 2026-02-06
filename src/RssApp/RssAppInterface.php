<?php declare(strict_types=1);

namespace App\RssApp;

interface RssAppInterface
{
    public function getItems(string $feedId, int $count = 100): array;
    public function feedExists(string $feedId): bool;
    public function findRssAppFeedIdBySourceUrl(string $sourceUrl): ?string;
    public function createFeed(string $url): array;
    public function deleteFeed(string $feedId): void;
}