<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon;

use App\FeedFetcher\FetchInfo;
use App\Model\SocialNetworkProfile;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use App\NetworkFeedFetcher\Mastodon\Model\Account;
use App\NetworkFeedFetcher\Mastodon\Model\AccountInfo;
use App\NetworkFeedFetcher\Mastodon\Model\Status;
use App\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

class MastodonFeedFetcher extends AbstractNetworkFeedFetcher
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        LoggerInterface $logger
    )
    {
        parent::__construct($logger);
    }

    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array
    {
        try {
            $account = IdentifierParser::parse($socialNetworkProfile);

            $accountInfo = $this->getAccountInfo($account);
            $timeline = $this->fetchTimeline($account, $accountInfo);

            $feedItemList = $this->convertTimeline($socialNetworkProfile, $timeline);

            return $feedItemList;
        } catch (\Exception $exception) {
            $this->markAsFailed($socialNetworkProfile, sprintf('Failed to fetch social network profile %d: %s', $socialNetworkProfile->getId(), $exception->getMessage()));

            return [];
        }
    }

    private function getAccountInfo(Account $account): ?AccountInfo
    {
        $accountInfoUri = sprintf('https://%s/api/v1/accounts/lookup?acct=%s', $account->getHostname(), $account->getUsername());

        $response = file_get_contents($accountInfoUri);

        return $this->serializer->deserialize($response, AccountInfo::class, 'json');
    }

    private function fetchTimeline(Account $account, AccountInfo $accountInfo): array
    {
        $feedUri = sprintf('https://%s/api/v1/accounts/%s/statuses', $account->getHostname(), $accountInfo->getId());

        $response = file_get_contents($feedUri);

        return $this->serializer->deserialize($response, sprintf('%s[]', Status::class), 'json');
    }

    private function convertTimeline(SocialNetworkProfile $socialnetworkProfile, array $timeline): array
    {
        $feedItemList = [];

        foreach ($timeline as $status) {
            $feedItem = EntryConverter::convert($socialnetworkProfile, $status);

            if ($feedItem) {
                $feedItemList[] = $feedItem;
            }
        }

        return $feedItemList;
    }
}
