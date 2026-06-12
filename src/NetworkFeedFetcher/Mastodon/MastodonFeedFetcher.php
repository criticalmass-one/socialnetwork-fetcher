<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon;

use App\FeedFetcher\FetchInfo;
use App\Model\Profile;
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

    public function fetch(Profile $profile, FetchInfo $fetchInfo): array
    {
        try {
            $account = IdentifierParser::parse($profile);

            $accountInfo = $this->getAccountInfo($account);
            $response = $this->fetchTimeline($account, $accountInfo, $fetchInfo->getCount());

            $timeline = $this->serializer->deserialize($response, sprintf('%s[]', Status::class), 'json');
            $rawEntries = json_decode($response, true) ?: [];

            return $this->convertTimeline($profile, $timeline, $rawEntries);
        } catch (\Exception $exception) {
            $this->markAsFailed($profile, sprintf('Failed to fetch social network profile %d: %s', $profile->getId(), $exception->getMessage()));

            return [];
        }
    }

    private function getAccountInfo(Account $account): AccountInfo
    {
        $url = sprintf('https://%s/api/v1/accounts/lookup?acct=%s', $account->getHostname(), $account->getUsername());

        $response = $this->httpClient->request('GET', $url)->getContent();

        return $this->serializer->deserialize($response, AccountInfo::class, 'json');
    }

    private function fetchTimeline(Account $account, AccountInfo $accountInfo, int $count): string
    {
        $url = sprintf('https://%s/api/v1/accounts/%s/statuses?limit=%d', $account->getHostname(), $accountInfo->getId(), $count);

        return $this->httpClient->request('GET', $url)->getContent();
    }

    private function convertTimeline(Profile $profile, array $timeline, array $rawEntries): array
    {
        $feedItemList = [];

        foreach ($timeline as $index => $status) {
            $rawEntry = $rawEntries[$index] ?? null;
            $feedItem = EntryConverter::convert($profile, $status, is_array($rawEntry) ? $rawEntry : null);

            if ($feedItem) {
                $feedItemList[] = $feedItem;
            }
        }

        return $feedItemList;
    }
}
