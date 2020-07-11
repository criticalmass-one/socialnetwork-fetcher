<?php declare(strict_types=1);

namespace App\ProfileFetcher;

use App\FeedFetcher\FetchInfo;

interface ProfileFetcherInterface
{
    public function fetchByNetworkIdentifier(string $networkIdentifier, string $citySlug = null): array;
    public function fetchByNetworkIdentifiers(array $networkIdentifiers = [], string $citySlug = null): array;
    public function fetchByFetchInfo(FetchInfo $fetchInfo): array;
}