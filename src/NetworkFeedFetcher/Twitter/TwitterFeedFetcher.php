<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Twitter;

use App\RssApp\Fetcher;

class TwitterFeedFetcher extends Fetcher
{
    public function getNetworkIdentifier(): string
    {
        return 'twitter';
    }
}
