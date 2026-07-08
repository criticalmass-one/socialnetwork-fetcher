<?php declare(strict_types=1);

namespace App\Profile;

/**
 * Outcome of an identifier change on a profile, including the RSS.app re-link
 * result for RSS.app-based networks.
 */
final class IdentifierChangeResult
{
    public function __construct(
        public readonly bool $changed,
        public readonly bool $rssAppApplicable,
        public readonly bool $oldFeedRemoved,
        public readonly bool $linkedToExistingFeed,
        public readonly int $importedItems,
        public readonly ?string $relinkError,
    ) {
    }

    /** The new identifier equals the current one — nothing was done. */
    public static function unchanged(): self
    {
        return new self(false, false, false, false, 0, null);
    }

    /** Identifier updated on a non-RSS.app network — no feed re-link necessary. */
    public static function identifierOnly(): self
    {
        return new self(true, false, false, false, 0, null);
    }

    /** Identifier updated and the RSS.app feed successfully re-linked. */
    public static function relinked(bool $oldFeedRemoved, bool $linkedToExistingFeed, int $importedItems): self
    {
        return new self(true, true, $oldFeedRemoved, $linkedToExistingFeed, $importedItems, null);
    }

    /** Identifier updated, but re-creating the RSS.app feed failed; the profile now has no feed. */
    public static function relinkFailed(bool $oldFeedRemoved, string $error): self
    {
        return new self(true, true, $oldFeedRemoved, false, 0, $error);
    }
}
