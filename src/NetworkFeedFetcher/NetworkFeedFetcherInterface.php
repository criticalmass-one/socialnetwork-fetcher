<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher;

use App\FeedFetcher\FetchInfo;
use App\Model\SocialNetworkProfile;

interface NetworkFeedFetcherInterface
{
    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array;

    public function supports(SocialNetworkProfile $socialNetworkProfile): bool;

    public function supportsNetwork(string $network): bool;

    public function getNetworkIdentifier(): string;
}
