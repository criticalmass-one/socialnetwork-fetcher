<?php declare(strict_types=1);

namespace App\RssApp;

use App\Entity\Profile;
use App\FeedFetcher\FeedFetcherInterface;
use App\FeedFetcher\FetchInfo;
use App\FeedFetcher\FetchResult;
use App\FeedItemPersister\FeedItemPersisterInterface;
use App\Model\Profile as ModelProfile;
use Psr\Log\LoggerInterface;

class FeedRegistrar
{
    public const RSS_APP_NETWORKS = ['instagram_profile', 'facebook_profile', 'facebook_page', 'threads_profile', 'thread', 'twitter'];
    public const INITIAL_IMPORT_COUNT = 100;

    public function __construct(
        private readonly RssAppInterface $rssApp,
        private readonly LoggerInterface $logger,
        private readonly FeedFetcherInterface $feedFetcher,
        private readonly FeedItemPersisterInterface $feedItemPersister,
    ) {
    }

    public function registerIfNeeded(Profile $profile): RegistrationResult
    {
        $networkIdentifier = $profile->getNetwork()?->getIdentifier();

        if (!in_array($networkIdentifier, self::RSS_APP_NETWORKS, true)) {
            return RegistrationResult::notApplicable();
        }

        $additionalData = $profile->getAdditionalData() ?? [];

        if (isset($additionalData['rss_feed_id'])) {
            return RegistrationResult::notApplicable();
        }

        $sourceUrl = $profile->getIdentifier();

        if ($sourceUrl === null) {
            return RegistrationResult::notApplicable();
        }

        try {
            $existingFeedId = $this->rssApp->findRssAppFeedIdBySourceUrl($sourceUrl);

            if ($existingFeedId !== null) {
                $additionalData['rss_feed_id'] = $existingFeedId;
                $profile->setAdditionalData($additionalData);

                $importedCount = $this->importInitialItems($profile);

                return RegistrationResult::linkedToExisting($importedCount);
            }

            $feedData = $this->rssApp->createFeed($sourceUrl);
            $additionalData['rss_feed_id'] = $feedData['id'];
            $profile->setAdditionalData($additionalData);

            return RegistrationResult::newlyCreated();
        } catch (\Throwable $e) {
            $this->logger->error('RSS.app feed registration failed for {identifier}: {message}', [
                'identifier' => $sourceUrl,
                'message' => $e->getMessage(),
            ]);

            return RegistrationResult::notApplicable();
        }
    }

    public function linkExistingFeedAndImport(Profile $profile, string $feedId): int
    {
        $additionalData = $profile->getAdditionalData() ?? [];
        $additionalData['rss_feed_id'] = $feedId;
        $profile->setAdditionalData($additionalData);

        return $this->importInitialItems($profile);
    }

    private function importInitialItems(Profile $profile): int
    {
        if ($profile->getId() === null) {
            $this->logger->warning('Cannot import initial RSS.app items: profile has no ID yet ({identifier}).', [
                'identifier' => $profile->getIdentifier(),
            ]);

            return 0;
        }

        $modelProfile = new ModelProfile();
        $modelProfile->setId($profile->getId());
        $modelProfile->setIdentifier($profile->getIdentifier());
        $modelProfile->setNetwork($profile->getNetwork()->getIdentifier());
        $modelProfile->setAdditionalData($profile->getAdditionalData());

        $fetcher = null;
        foreach ($this->feedFetcher->getNetworkFetcherList() as $networkFetcher) {
            if ($networkFetcher->supports($modelProfile)) {
                $fetcher = $networkFetcher;
                break;
            }
        }

        if ($fetcher === null) {
            $this->logger->warning('No fetcher available for network "{network}" — skipping initial RSS.app import.', [
                'network' => $modelProfile->getNetwork(),
            ]);

            return 0;
        }

        $fetchInfo = new FetchInfo();
        $fetchInfo->setCount(self::INITIAL_IMPORT_COUNT);

        try {
            $feedItemList = $fetcher->fetch($modelProfile, $fetchInfo);
        } catch (\Throwable $e) {
            $this->logger->error('Initial RSS.app import failed for {identifier}: {message}', [
                'identifier' => $profile->getIdentifier(),
                'message' => $e->getMessage(),
            ]);

            return 0;
        }

        $fetchResult = new FetchResult();
        $fetchResult->setProfile($modelProfile)->setCounterFetched(count($feedItemList));

        $this->feedItemPersister->persistFeedItemList($feedItemList, $fetchResult)->flush();

        return count($feedItemList);
    }
}
