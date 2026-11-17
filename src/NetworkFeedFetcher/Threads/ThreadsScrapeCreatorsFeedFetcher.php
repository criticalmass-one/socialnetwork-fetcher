<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Threads;

use App\Model\Item;
use App\Model\Profile;
use App\ScrapeCreators\Fetcher;
use App\ScrapeCreators\PostConverter;

class ThreadsScrapeCreatorsFeedFetcher extends Fetcher
{
    public function getNetworkIdentifier(): string
    {
        return 'threads_profile';
    }

    protected function loadPosts(string $handle, Profile $profile): array
    {
        return $this->scrapeCreators->getThreadsPosts($handle);
    }

    protected function convert(Profile $profile, array $post): ?Item
    {
        return PostConverter::convertThreads($profile, $post);
    }
}
