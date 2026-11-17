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
 */
abstract class Fetcher extends AbstractNetworkFeedFetcher
{
    public function __construct(
        LoggerInterface $logger,
        protected readonly ScrapeCreatorsInterface $scrapeCreators,
    ) {
        parent::__construct($logger);
    }

    public function supports(Profile $profile): bool
    {
        return parent::supports($profile) && $profile->getFetchProvider() === PostConverter::PROVIDER;
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
