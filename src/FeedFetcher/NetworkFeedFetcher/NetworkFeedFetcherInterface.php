<?php declare(strict_types=1);

namespace App\FeedFetcher\NetworkFeedFetcher;

use App\FeedFetcher\FetchInfo;
use App\Entity\SocialNetworkProfile;

interface NetworkFeedFetcherInterface
{
    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array;

    public function supports(SocialNetworkProfile $socialNetworkProfile): bool;

    public function supportsNetwork(string $network): bool;

    public function getNetworkIdentifier(): string;
}
