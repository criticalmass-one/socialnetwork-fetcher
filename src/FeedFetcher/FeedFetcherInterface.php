<?php declare(strict_types=1);

namespace App\FeedFetcher;

use App\NetworkFeedFetcher\NetworkFeedFetcherInterface;

interface FeedFetcherInterface
{
    public function addNetworkFeedFetcher(NetworkFeedFetcherInterface $networkFeedFetcher): FeedFetcherInterface;

    public function getNetworkFetcherList(): array;

    public function fetch(FetchInfo $fetchInfo, callable $callback): FeedFetcherInterface;

    public function persist(): FeedFetcherInterface;
}
