<?php declare(strict_types=1);

namespace App\RssApp;

final class StaleFeed
{
    public function __construct(
        public readonly \DateTimeImmutable $newestItem,
        public readonly float $ageDays,
        public readonly float $medianGapDays,
        public readonly float $thresholdDays,
    ) {
    }
}
