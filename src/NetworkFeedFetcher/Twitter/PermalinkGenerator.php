<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Twitter;

use App\Model\SocialNetworkProfile;

class PermalinkGenerator
{
    private function __construct()
    {

    }

    public static function generatePermalink(SocialNetworkProfile $socialNetworkProfile, \stdClass $tweet): string
    {
        $screenName = Screenname::extractScreenname($socialNetworkProfile);
        $tweetId = $tweet->id;

        return sprintf('https://twitter.com/%s/status/%d', $screenName, $tweetId);
    }
}