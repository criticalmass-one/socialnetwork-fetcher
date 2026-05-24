<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\TikTok;

use App\RssApp\Fetcher;

class TikTokFeedFetcher extends Fetcher
{
    public function getNetworkIdentifier(): string
    {
        return 'tiktok';
    }
}
