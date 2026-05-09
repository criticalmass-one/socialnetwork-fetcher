<?php declare(strict_types=1);

namespace App\RssApp;

final class RegistrationResult
{
    public function __construct(
        public readonly bool $registered,
        public readonly bool $linkedToExistingFeed,
        public readonly int $importedItems,
    ) {
    }

    public static function notApplicable(): self
    {
        return new self(false, false, 0);
    }

    public static function newlyCreated(): self
    {
        return new self(true, false, 0);
    }

    public static function linkedToExisting(int $importedItems): self
    {
        return new self(true, true, $importedItems);
    }
}
