<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Facebook;

use App\RssApp\Fetcher;

class FacebookFeedFetcher extends Fetcher
{
    public function getNetworkIdentifier(): string
    {
        return 'facebook_page';
    }
}
