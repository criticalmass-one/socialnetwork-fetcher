<?php declare(strict_types=1);

namespace App\ProfileFetcher;

interface ProfileFetcherInterface
{
    public function fetchByNetworkIdentifier(string $networkIdentifier): array;
    public function fetchByNetworkIdentifiers(array $networkIdentifiers = []): array;
}