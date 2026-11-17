<?php declare(strict_types=1);

namespace App\ScrapeCreators;

/**
 * One page of posts plus the cursor for the next one (null when there is none).
 */
final class PostPage
{
    /**
     * @param list<array<string, mixed>> $posts
     */
    public function __construct(
        public readonly array $posts,
        public readonly ?string $nextCursor = null,
    ) {
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null && $this->nextCursor !== '';
    }
}
