<?php declare(strict_types=1);

namespace App\ScrapeCreators;

interface ScrapeCreatorsInterface
{
    /** False without an API key – the fetchers then fail the profile instead of calling the API. */
    public function isConfigured(): bool;

    /**
     * The newest posts of an Instagram profile (one page, usually twelve, pinned first).
     *
     * @return list<array<string, mixed>>
     *
     * @throws ScrapeCreatorsException
     */
    public function getInstagramPosts(string $handle): array;

    /**
     * The newest posts of a Threads profile (one page).
     *
     * @return list<array<string, mixed>>
     *
     * @throws ScrapeCreatorsException
     */
    public function getThreadsPosts(string $handle): array;
}
