<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher;

use App\FeedFetcher\FetchInfo;
use App\Model\Profile;

interface NetworkFeedFetcherInterface
{
    public function fetch(Profile $profile, FetchInfo $fetchInfo): array;

    public function supports(Profile $profile): bool;

    public function supportsNetwork(string $network): bool;

    public function getNetworkIdentifier(): string;
}
