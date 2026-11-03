<?php declare(strict_types=1);

namespace App\Tests\Unit\RssApp;

use App\RssApp\StalenessDetector;
use PHPUnit\Framework\TestCase;

class StalenessDetectorTest extends TestCase
{
    private const DAY = 86400;

    private \DateTimeImmutable $now;
    private StalenessDetector $detector;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-01 10:00:00');
        $this->detector = new StalenessDetector();
    }

    public function testDailyPosterSilentForTenDaysIsStale(): void
    {
        // Posted daily until 10 days ago (the torsten.franz.lg case).
        $stale = $this->detector->detect($this->series(newestDaysAgo: 10, gapDays: 1, count: 20), $this->now);

        self::assertNotNull($stale);
        self::assertEqualsWithDelta(10.0, $stale->ageDays, 0.01);
        self::assertEqualsWithDelta(1.0, $stale->medianGapDays, 0.01);
        self::assertEqualsWithDelta(7.0, $stale->thresholdDays, 0.01, 'MIN_DAYS floor applies');
    }

    public function testDailyPosterSilentForFiveDaysIsNotStale(): void
    {
        self::assertNull($this->detector->detect($this->series(5, 1, 20), $this->now));
    }

    public function testWeeklyPosterUsesRhythmBasedThreshold(): void
    {
        // Weekly rhythm → threshold 35 days.
        self::assertNull($this->detector->detect($this->series(30, 7, 20), $this->now));
        self::assertNotNull($this->detector->detect($this->series(40, 7, 20), $this->now));
    }

    public function testTooFewItemsYieldNoVerdict(): void
    {
        self::assertNull($this->detector->detect($this->series(100, 1, StalenessDetector::MIN_ITEMS - 1), $this->now));
    }

    public function testOrderOfTimestampsDoesNotMatter(): void
    {
        $series = $this->series(10, 1, 20);
        shuffle($series);

        self::assertNotNull($this->detector->detect($series, $this->now));
    }

    public function testOnlyMostRecentSampleIsUsedForRhythm(): void
    {
        // 20 recent daily posts, preceded by years of monthly posts: rhythm stays daily.
        $series = array_merge(
            $this->series(10, 1, 20),
            $this->series(400, 30, 30),
        );

        $stale = $this->detector->detect($series, $this->now);

        self::assertNotNull($stale);
        self::assertEqualsWithDelta(1.0, $stale->medianGapDays, 0.01);
    }

    public function testDetectFromRssAppItemsSkipsMissingOrInvalidDates(): void
    {
        $items = array_map(
            fn (int $ts): array => ['date_published' => (new \DateTimeImmutable())->setTimestamp($ts)->format(DATE_ATOM)],
            $this->series(10, 1, 10),
        );
        $items[] = ['title' => 'no date'];
        $items[] = ['date_published' => 'not a date'];

        self::assertNotNull($this->detector->detectFromRssAppItems($items, $this->now));
    }

    /** @return list<int> */
    private function series(int $newestDaysAgo, int $gapDays, int $count): array
    {
        $newest = $this->now->getTimestamp() - $newestDaysAgo * self::DAY;

        return array_map(static fn (int $i): int => $newest - $i * $gapDays * self::DAY, range(0, $count - 1));
    }
}
