<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Twitter;

use App\FeedFetcher\FetchInfo;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use App\Model\SocialNetworkProfile;
use Codebird\Codebird;
use Psr\Log\LoggerInterface;

class TwitterFeedFetcher extends AbstractNetworkFeedFetcher
{
    protected Codebird $codebird;

    public function __construct(LoggerInterface $logger, string $twitterClientId, string $twitterSecret)
    {
        Codebird::setConsumerKey($twitterClientId, $twitterSecret);
        $this->codebird = Codebird::getInstance();

        parent::__construct($logger);
    }

    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array
    {
        if (!$socialNetworkProfile->getCityId()) {
            return [];
        }

        try {
            return $this->fetchFeed($socialNetworkProfile, $fetchInfo);
        } catch (\Exception $exception) {
            $this->markAsFailed($socialNetworkProfile, sprintf('Failed to fetch social network profile %d: %s', $socialNetworkProfile->getId(), $exception->getMessage()));
        }

        return [];
    }

    protected function fetchFeed(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array
    {
        $feedItemList = [];

        $screenname = Screenname::extractScreenname($socialNetworkProfile);

        if (!$screenname || !Screenname::isValidScreenname($screenname)) {
            $this->markAsFailed($socialNetworkProfile, sprintf('Skipping %s cause it is not a valid twitter handle.', $screenname));

            return [];
        }

        $this->logger->info(sprintf('Now quering @%s', $screenname));

        $queryString = QueryBuilder::build($socialNetworkProfile, $fetchInfo);

        $reply = $this->codebird->statuses_userTimeline($queryString, true);
        $data = (array)$reply;

        $lastTweetId = null;

        foreach ($data as $tweet) {
            if (!is_object($tweet)) {
                $this->logger->info('Tweet did not contain usable data. Skipping.');

                continue;
            }

            if (property_exists($tweet, 'limit')) {
                $this->logger->info('Got cursor, skipping.');

                continue;
            }

            $feedItem = TweetConverter::convert($socialNetworkProfile, $tweet);

            if ($feedItem) {
                $this->logger->info(sprintf('Parsed and added tweet #%s', $feedItem->getUniqueIdentifier()));

                $feedItemList[] = $feedItem;

                if (!$lastTweetId || $lastTweetId < $tweet->id) {
                    $lastTweetId = $tweet->id;
                }
            }
        }

        if ($lastTweetId) {
            $socialNetworkProfile->setAdditionalData(['lastTweetId' => $lastTweetId]);
        }

        $socialNetworkProfile->setLastFetchSuccessDateTime(new \DateTime());

        return $feedItemList;
    }
}
