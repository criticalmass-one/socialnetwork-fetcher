<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Instagram;

use App\RssApp\Fetcher;

class InstagramFeedFetcher extends Fetcher
{
    public function getNetworkIdentifier(): string
    {
        return 'instagram_profile';
    }
}
