<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Instagram;

use App\Model\Item;
use App\Model\Profile;
use App\ScrapeCreators\Fetcher;
use App\ScrapeCreators\PostConverter;
use App\ScrapeCreators\ScrapeCreatorsException;

class InstagramScrapeCreatorsFeedFetcher extends Fetcher
{
    /** Pages per fetch at most, the first one included – each page costs a credit. */
    public const MAX_PAGES = 4;

    /** The first page starts with up to three pinned posts that may be arbitrarily old. */
    private const PINNED_SLOTS = 3;

    /** Margin before the last successful fetch, so a post published during the fetch is not missed. */
    private const OVERLAP_SECONDS = 86400;

    public function getNetworkIdentifier(): string
    {
        return 'instagram_profile';
    }

    /**
     * Reads the newest page and turns further pages only while a page holds
     * nothing older than the last successful fetch – then posts would be lost
     * between two fetches (a profile posting more than twelve times a day, or
     * a profile coming back after a long gap). A profile never fetched before
     * gets the first page only.
     */
    protected function loadPosts(string $handle, Profile $profile): array
    {
        $lastSuccess = $profile->getLastFetchSuccessDateTime();
        $cutoff = $lastSuccess === null ? null : $lastSuccess->getTimestamp() - self::OVERLAP_SECONDS;

        $page = $this->scrapeCreators->getInstagramPostsPage($handle);
        $posts = $page->posts;

        for ($pageNumber = 1; $pageNumber < self::MAX_PAGES; ++$pageNumber) {
            if ($cutoff === null || !$page->hasMore()) {
                break;
            }

            $comparable = $pageNumber === 1 ? array_slice($page->posts, self::PINNED_SLOTS) : $page->posts;
            $oldest = $this->oldestTimestamp($comparable);

            if ($oldest === null || $oldest < $cutoff) {
                break;
            }

            try {
                $page = $this->scrapeCreators->getInstagramPostsPage($handle, $page->nextCursor);
            } catch (ScrapeCreatorsException $e) {
                // Keep what the earlier pages brought; the next fetch tries again.
                $this->logger->warning('ScrapeCreators: weitere Seite für {handle} fehlgeschlagen: {message}', [
                    'handle' => $handle,
                    'message' => $e->getMessage(),
                ]);

                break;
            }

            array_push($posts, ...$page->posts);
        }

        return $posts;
    }

    protected function convert(Profile $profile, array $post): ?Item
    {
        return PostConverter::convertInstagram($profile, $post);
    }

    /**
     * @param list<array<string, mixed>> $posts
     */
    private function oldestTimestamp(array $posts): ?int
    {
        $timestamps = array_filter(array_map(
            static fn (array $post): ?int => is_numeric($post['taken_at'] ?? null) ? (int) $post['taken_at'] : null,
            $posts,
        ), static fn (?int $timestamp): bool => $timestamp !== null);

        return $timestamps === [] ? null : min($timestamps);
    }
}
