<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Threads;

use App\RssApp\Fetcher;

class ThreadFeedFetcher extends Fetcher
{
    public function getNetworkIdentifier(): string
    {
        return 'threads_profile';
    }
}
