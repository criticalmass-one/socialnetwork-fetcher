<?php declare(strict_types=1);

namespace App\FeedFetcher;

use App\FeedItemPersister\FeedItemPersisterInterface;
use App\MediaDownloader\MediaDownloadService;
use App\NetworkFeedFetcher\NetworkFeedFetcherInterface;
use App\Model\Profile;
use App\ProfileFetcher\ProfileFetcherInterface;
use App\ProfilePersister\ProfilePersisterInterface;
use App\Push\GroupPushNotifier;
use App\Repository\ProfileRepository;
use App\SourceFetcher\SourceFetcher;

class FeedFetcher extends AbstractFeedFetcher
{
    private MediaDownloadService $mediaDownloadService;
    private ProfileRepository $profileRepository;
    private GroupPushNotifier $groupPushNotifier;

    public function __construct(
        FeedItemPersisterInterface $feedItemPersister,
        ProfileFetcherInterface $profileFetcher,
        ProfilePersisterInterface $profilePersister,
        SourceFetcher $sourceFetcher,
        MediaDownloadService $mediaDownloadService,
        ProfileRepository $profileRepository,
        GroupPushNotifier $groupPushNotifier,
    ) {
        parent::__construct($feedItemPersister, $profileFetcher, $profilePersister, $sourceFetcher);
        $this->mediaDownloadService = $mediaDownloadService;
        $this->profileRepository = $profileRepository;
        $this->groupPushNotifier = $groupPushNotifier;
    }
    protected function getFeedFetcherForProfile(Profile $profile): ?NetworkFeedFetcherInterface
    {
        /** @var NetworkFeedFetcherInterface $fetcher */
        foreach ($this->networkFetcherList as $fetcher) {
            if ($fetcher->supports($profile)) {
                return $fetcher;
            }
        }

        return null;
    }

    public function fetch(FetchInfo $fetchInfo, callable $callback): FeedFetcherInterface
    {
        $profileList = $this->getProfiles($fetchInfo);

        /** @var Profile $profile */
        foreach ($profileList as $profile) {
            // Erst Profil upserten, damit es für FK-Lookups durch FeedItems existiert.
            $this->profilePersister->persistProfile($profile);

            $fetcher = $this->getFeedFetcherForProfile($profile);

            if ($fetcher) {
                try {
                    $failureErrorBefore = $profile->getLastFetchFailureError();
                    $feedItemList = $fetcher->fetch($profile, $fetchInfo);

                    // Check if the fetcher marked the profile as failed (e.g. invalid identifier)
                    $fetcherMarkedAsFailed = $profile->getLastFetchFailureError() !== $failureErrorBefore;

                    foreach ($feedItemList as $feedItem) {
                        $this->sourceFetcher->fetch($feedItem, $profile);
                    }

                    $fetchResult = new FetchResult();
                    $fetchResult
                        ->setProfile($profile)
                        ->setCounterFetched(count($feedItemList));

                    $newCountBefore = $this->feedItemPersister->getNewCount();
                    $this->feedItemPersister->persistFeedItemList($feedItemList, $fetchResult)->flush();
                    $newItemsForProfile = $this->feedItemPersister->getNewCount() - $newCountBefore;

                    if (!$fetcherMarkedAsFailed) {
                        $profile->setLastFetchSuccessDateTime(new \DateTimeImmutable());
                        $profile->setLastFetchFailureDateTime(null);
                        $profile->setLastFetchFailureError(null);
                    }

                    $this->profilePersister->persistProfile($profile);

                    // Download media if profile has savePhotos or saveVideos enabled
                    $entityProfile = $this->profileRepository->find($profile->getId());

                    if ($entityProfile && ($entityProfile->isSavePhotos() || $entityProfile->isSaveVideos())) {
                        $this->mediaDownloadService->downloadNewItemsForProfile($entityProfile);
                    }

                    if ($entityProfile) {
                        // Accumulate per group; bundled notifications go out after the run.
                        $this->groupPushNotifier->recordNewItems($entityProfile, $newItemsForProfile);
                    }

                    $callback($fetchResult);
                } catch (\Throwable $e) {
                    $profile->setLastFetchFailureDateTime(new \DateTimeImmutable());
                    $profile->setLastFetchFailureError($e->getMessage());
                    $this->profilePersister->persistProfile($profile);
                }
            }
        }

        // Send bundled push notifications for all groups that gained new items.
        $this->groupPushNotifier->dispatch();

        return $this;
    }

    protected function stripNetworkList(FetchInfo $fetchInfo): FeedFetcher
    {
        if (count($this->fetchableNetworkList) === 0) {
            return $this;
        }

        /** @var NetworkFeedFetcherInterface $fetcher */
        foreach ($this->networkFetcherList as $key => $fetcher) {
            if (!in_array($fetcher->getNetworkIdentifier(), $this->fetchableNetworkList)) {
                unset($this->networkFetcherList[$key]);
            }
        }

        return $this;
    }

    public function persist(): FeedFetcherInterface
    {
        return $this;
    }
}
