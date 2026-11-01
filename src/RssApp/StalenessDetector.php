<?php declare(strict_types=1);

namespace App\RssApp;

/**
 * Detects RSS.app feeds that have silently stopped updating.
 *
 * RSS.app sometimes keeps a feed "active" while its scraper can no longer read
 * the source (e.g. an Instagram profile it gets "No result found" for). The
 * feed then keeps returning its last known items forever. `is_active` does not
 * catch that, so we compare the age of the newest item with the profile's own
 * posting rhythm: a profile that usually posts every 1–2 days but has been
 * silent for 10 days is very likely not being scraped anymore.
 */
class StalenessDetector
{
    /** Number of most recent items used to estimate the posting rhythm. */
    public const SAMPLE_SIZE = 20;

    /** Below this many dated items, no rhythm can be estimated. */
    public const MIN_ITEMS = 5;

    /** The newest item must be older than FACTOR × median gap … */
    public const FACTOR = 5;

    /** … and at least this many days old, so quiet weekends never trigger. */
    public const MIN_DAYS = 7;

    /**
     * @param array<int, array<string, mixed>> $items RSS.app items (`date_published`)
     */
    public function detectFromRssAppItems(array $items, \DateTimeImmutable $now): ?StaleFeed
    {
        $timestamps = [];

        foreach ($items as $item) {
            $date = $item['date_published'] ?? null;

            if (!is_string($date) || $date === '') {
                continue;
            }

            try {
                $timestamps[] = (new \DateTimeImmutable($date))->getTimestamp();
            } catch (\Exception) {
                continue;
            }
        }

        return $this->detect($timestamps, $now);
    }

    /**
     * @param list<int> $timestamps Unix timestamps of the profile's items, any order
     */
    public function detect(array $timestamps, \DateTimeImmutable $now): ?StaleFeed
    {
        rsort($timestamps);
        $timestamps = array_slice($timestamps, 0, self::SAMPLE_SIZE);

        if (count($timestamps) < self::MIN_ITEMS) {
            return null;
        }

        $gaps = [];
        for ($i = 1, $n = count($timestamps); $i < $n; ++$i) {
            $gaps[] = $timestamps[$i - 1] - $timestamps[$i];
        }

        $medianGap = self::median($gaps);
        $threshold = max(self::MIN_DAYS * 86400, (int) round(self::FACTOR * $medianGap));
        $age = $now->getTimestamp() - $timestamps[0];

        if ($age <= $threshold) {
            return null;
        }

        return new StaleFeed(
            newestItem: (new \DateTimeImmutable())->setTimestamp($timestamps[0]),
            ageDays: $age / 86400,
            medianGapDays: $medianGap / 86400,
            thresholdDays: $threshold / 86400,
        );
    }

    /** @param list<int> $values */
    private static function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (float) $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
