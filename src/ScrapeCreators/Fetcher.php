<?php declare(strict_types=1);

namespace App\ScrapeCreators;

use App\FeedFetcher\FetchInfo;
use App\Model\Item;
use App\Model\Profile;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use Psr\Log\LoggerInterface;

/**
 * Base for fetchers that read a network through ScrapeCreators instead of
 * RSS.app. They only take over profiles whose fetchProvider is set to
 * "scrapecreators"; every other profile of the network stays with RSS.app.
 *
 * Every request costs a credit, while the networks are polled every few minutes.
 * A profile is therefore only taken again once its last attempt is older than
 * the minimum interval; in between no fetcher supports it and the run skips it.
 * A manual fetch passes a profile without fetch dates and is never held back.
 */
abstract class Fetcher extends AbstractNetworkFeedFetcher
{
    public function __construct(
        LoggerInterface $logger,
        protected readonly ScrapeCreatorsInterface $scrapeCreators,
        private readonly int $scrapeCreatorsMinIntervalHours = 24,
    ) {
        parent::__construct($logger);
    }

    public function supports(Profile $profile): bool
    {
        return parent::supports($profile)
            && $profile->getFetchProvider() === PostConverter::PROVIDER
            && $this->isDue($profile);
    }

    private function isDue(Profile $profile): bool
    {
        $lastAttempt = max($profile->getLastFetchSuccessDateTime(), $profile->getLastFetchFailureDateTime());

        if ($lastAttempt === null || $this->scrapeCreatorsMinIntervalHours <= 0) {
            return true;
        }

        return $lastAttempt <= new \DateTimeImmutable(sprintf('-%d hours', $this->scrapeCreatorsMinIntervalHours));
    }

    public function fetch(Profile $profile, FetchInfo $fetchInfo): array
    {
        $handle = HandleParser::parse($profile->getIdentifier());

        if ($handle === null) {
            $this->markAsFailed($profile, sprintf('Kein Kontoname im Identifier erkennbar: %s', (string) $profile->getIdentifier()));

            return [];
        }

        try {
            $posts = $this->loadPosts($handle);
        } catch (ScrapeCreatorsException $e) {
            $this->markAsFailed($profile, sprintf('ScrapeCreators-Fehler für %s: %s', $handle, $e->getMessage()));

            return [];
        }

        $feedItemList = [];

        foreach ($posts as $post) {
            $feedItem = $this->convert($profile, $post);

            if ($feedItem) {
                $feedItemList[] = $feedItem;
            }
        }

        return $feedItemList;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ScrapeCreatorsException
     */
    abstract protected function loadPosts(string $handle): array;

    abstract protected function convert(Profile $profile, array $post): ?Item;
}
