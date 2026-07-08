<?php declare(strict_types=1);

namespace App\RssApp;

use App\Entity\Profile;
use App\FeedFetcher\FeedFetcherInterface;
use App\FeedFetcher\FetchInfo;
use App\FeedFetcher\FetchResult;
use App\FeedItemPersister\FeedItemPersisterInterface;
use App\Model\Profile as ModelProfile;
use App\Profile\IdentifierChangeResult;
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

        if ($profile->getRssAppFeedId() !== null) {
            return RegistrationResult::notApplicable();
        }

        $sourceUrl = $profile->getIdentifier();

        if ($sourceUrl === null) {
            return RegistrationResult::notApplicable();
        }

        try {
            $existingFeedId = $this->rssApp->findRssAppFeedIdBySourceUrl($sourceUrl);

            if ($existingFeedId !== null) {
                $profile->setRssAppFeedId($existingFeedId);

                $importedCount = $this->importInitialItems($profile);

                return RegistrationResult::linkedToExisting($importedCount);
            }

            $feedData = $this->rssApp->createFeed($sourceUrl);
            $profile->setRssAppFeedId($feedData['id']);

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
        $profile->setRssAppFeedId($feedId);

        return $this->importInitialItems($profile);
    }

    /**
     * Re-links the profile's RSS.app feed after its identifier has changed.
     *
     * RSS.app cannot re-point an existing feed to a new source URL, so the old
     * feed is deleted and a fresh feed is created (or an already-existing feed
     * for the new URL is adopted) and its current items are imported. The
     * profile is expected to already carry the new identifier; its stored items
     * are untouched, so no historical data is lost.
     */
    public function relinkRssAppFeed(Profile $profile): IdentifierChangeResult
    {
        $networkIdentifier = $profile->getNetwork()?->getIdentifier();

        if (!in_array($networkIdentifier, self::RSS_APP_NETWORKS, true)) {
            return IdentifierChangeResult::identifierOnly();
        }

        $sourceUrl = $profile->getIdentifier();

        if ($sourceUrl === null) {
            return IdentifierChangeResult::identifierOnly();
        }

        $oldFeedId = $profile->getRssAppFeedId();
        $oldFeedRemoved = false;

        if ($oldFeedId !== null) {
            try {
                $this->rssApp->deleteFeed($oldFeedId);
                $oldFeedRemoved = true;
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to delete old RSS.app feed {feedId} while re-linking profile {id}: {message}', [
                    'feedId' => $oldFeedId,
                    'id' => $profile->getId(),
                    'message' => $e->getMessage(),
                ]);
            }

            $profile->setRssAppFeedId(null);
        }

        try {
            $existingFeedId = $this->rssApp->findRssAppFeedIdBySourceUrl($sourceUrl);

            if ($existingFeedId !== null) {
                $importedCount = $this->linkExistingFeedAndImport($profile, $existingFeedId);

                return IdentifierChangeResult::relinked($oldFeedRemoved, true, $importedCount);
            }

            $feedData = $this->rssApp->createFeed($sourceUrl);
            $profile->setRssAppFeedId($feedData['id']);

            $importedCount = $this->importInitialItems($profile);

            return IdentifierChangeResult::relinked($oldFeedRemoved, false, $importedCount);
        } catch (\Throwable $e) {
            $this->logger->error('RSS.app feed re-link failed for {identifier}: {message}', [
                'identifier' => $sourceUrl,
                'message' => $e->getMessage(),
            ]);

            $profile->setRssAppFeedId(null);

            return IdentifierChangeResult::relinkFailed($oldFeedRemoved, $e->getMessage());
        }
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
        $modelProfile->setRssAppFeedId($profile->getRssAppFeedId());

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
