<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon;

use App\FeedFetcher\FetchInfo;
use App\Model\SocialNetworkProfile;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use App\NetworkFeedFetcher\Mastodon\Model\Account;
use App\NetworkFeedFetcher\Mastodon\Model\AccountInfo;
use JMS\Serializer\SerializerInterface;

class MastodonFeedFetcher extends AbstractNetworkFeedFetcher
{
    public function __construct(
        private readonly SerializerInterface $serializer
    )
    {

    }

    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array
    {
        $account = IdentifierParser::parse($socialNetworkProfile);

        $accountInfo = $this->getAccountInfo($account);
        $timeline = $this->fetchTimeline($account, $accountInfo);



        dd($timeline);
        return $data;
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

        return $this->serializer->deserialize($response, 'array<App\NetworkFeedFetcher\Mastodon\Model\Status>', 'json');
    }
}