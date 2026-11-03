<?php declare(strict_types=1);

namespace App\RssApp;

use App\FeedFetcher\FetchInfo;
use App\Model\Profile;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use Psr\Log\LoggerInterface;

abstract class Fetcher extends AbstractNetworkFeedFetcher
{
    private readonly StalenessDetector $stalenessDetector;

    public function __construct(
        LoggerInterface $logger,
        private readonly RssAppInterface $rssApp,
        ?StalenessDetector $stalenessDetector = null,
    ) {
        parent::__construct($logger);

        $this->stalenessDetector = $stalenessDetector ?? new StalenessDetector();
    }

    public function fetch(Profile $profile, FetchInfo $fetchInfo): array
    {
        $sourceUrl = $profile->getIdentifier();
        if (!$sourceUrl) {
            $this->markAsFailed($profile, 'Kein Identifier vorhanden.');
            return [];
        }

        $feedId = $profile->getRssAppFeedId();

        if ($feedId && !$this->rssApp->feedExists($feedId)) {
            $this->logger->notice(sprintf('RSS.app Feed %s existiert nicht mehr für %s, suche neu.', $feedId, $sourceUrl));
            $feedId = null;
            $profile->setRssAppFeedId(null);
        }

        if (!$feedId) {
            $feedId = $this->rssApp->findRssAppFeedIdBySourceUrl($sourceUrl);
            if ($feedId) {
                $profile->setRssAppFeedId($feedId);
            } else {
                $this->markAsFailed($profile, 'Kein Feed bei RSS.app gefunden für ' . $sourceUrl);
                return [];
            }
        }

        try {
            $feed = $this->rssApp->getFeed($feedId, $fetchInfo->getCount());

            // RSS.app keeps serving a deactivated feed with HTTP 200 and its last
            // known items, so without this check the profile would silently look
            // healthy while no new items ever arrive.
            if (($feed['is_active'] ?? true) === false) {
                $this->markAsFailed($profile, sprintf(
                    'RSS.app hat den Feed %s für %s deaktiviert (is_active=false) – es kommen keine neuen Beiträge mehr. Feed bei RSS.app prüfen bzw. neu anlegen: https://rss.app/r/feed/%s',
                    $feedId,
                    $sourceUrl,
                    $feedId,
                ));
            } else {
                $this->warnIfStale($profile, $feedId, $feed['items'] ?? []);
            }

            $feedItemList = [];

            foreach ($feed['items'] ?? [] as $item) {
                $feedItem = Converter::convert($profile, $item);

                if ($feedItem) {
                    $feedItemList[] = $feedItem;
                }
            }

            return $feedItemList;

        } catch (\Throwable $e) {
            $this->markAsFailed($profile, sprintf(
                'RSS.app-Fehler für %s (Feed-ID: %s, URL: https://api.rss.app/v1/feeds/%s): %s',
                $sourceUrl,
                $feedId,
                $feedId,
                $e->getMessage(),
            ));
            return [];
        }
    }

    /**
     * An "active" RSS.app feed can still be stuck on its last known items. That
     * is not reliably distinguishable from a profile that simply went quiet, so
     * it is only logged — `app:rssapp:stale-feeds` lists such feeds.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function warnIfStale(Profile $profile, string $feedId, array $items): void
    {
        $stale = $this->stalenessDetector->detectFromRssAppItems($items, new \DateTimeImmutable());

        if ($stale === null) {
            return;
        }

        $this->logger->warning(sprintf(
            'RSS.app-Feed %s für %s liefert vermutlich nichts Neues mehr: neuester Beitrag vom %s (vor %.1f Tagen), üblicher Abstand %.1f Tage.',
            $feedId,
            $profile->getIdentifier(),
            $stale->newestItem->format('Y-m-d H:i'),
            $stale->ageDays,
            $stale->medianGapDays,
        ));
    }
}
