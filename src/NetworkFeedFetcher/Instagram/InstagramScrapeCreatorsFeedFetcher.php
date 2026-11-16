<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Instagram;

use App\Model\Item;
use App\Model\Profile;
use App\ScrapeCreators\Fetcher;
use App\ScrapeCreators\PostConverter;

class InstagramScrapeCreatorsFeedFetcher extends Fetcher
{
    public function getNetworkIdentifier(): string
    {
        return 'instagram_profile';
    }

    protected function loadPosts(string $handle): array
    {
        return $this->scrapeCreators->getInstagramPosts($handle);
    }

    protected function convert(Profile $profile, array $post): ?Item
    {
        return PostConverter::convertInstagram($profile, $post);
    }
}
