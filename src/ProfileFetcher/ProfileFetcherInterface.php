<?php declare(strict_types=1);

namespace App\ProfileFetcher;

use App\FeedFetcher\FetchInfo;

interface ProfileFetcherInterface
{
    public function fetchByNetworkIdentifier(string $networkIdentifier): array;
    public function fetchByNetworkIdentifiers(array $networkIdentifiers = []): array;
    public function fetchByFetchInfo(FetchInfo $fetchInfo): array;
}