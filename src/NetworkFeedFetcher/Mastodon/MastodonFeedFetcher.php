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
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MastodonFeedFetcher extends AbstractNetworkFeedFetcher
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array
    {
        try {
            $account = IdentifierParser::parse($socialNetworkProfile);

            $accountInfo = $this->getAccountInfo($account);
            $timeline = $this->fetchTimeline($account, $accountInfo, $fetchInfo->getCount());

            return $this->convertTimeline($socialNetworkProfile, $timeline);
        } catch (\Exception $exception) {
            $this->markAsFailed($socialNetworkProfile, sprintf('Failed to fetch social network profile %d: %s', $socialNetworkProfile->getId(), $exception->getMessage()));

            return [];
        }
    }

    private function getAccountInfo(Account $account): AccountInfo
    {
        $url = sprintf('https://%s/api/v1/accounts/lookup?acct=%s', $account->getHostname(), $account->getUsername());

        $response = $this->httpClient->request('GET', $url)->getContent();

        return $this->serializer->deserialize($response, AccountInfo::class, 'json');
    }

    private function fetchTimeline(Account $account, AccountInfo $accountInfo, int $count): array
    {
        $url = sprintf('https://%s/api/v1/accounts/%s/statuses?limit=%d', $account->getHostname(), $accountInfo->getId(), $count);

        $response = $this->httpClient->request('GET', $url)->getContent();

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
