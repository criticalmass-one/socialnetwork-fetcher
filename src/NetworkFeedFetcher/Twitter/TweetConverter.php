<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Twitter;

use App\Model\SocialNetworkFeedItem;
use App\Model\SocialNetworkProfile;

class TweetConverter
{
    private function __construct()
    {

    }

    public static function convert(SocialNetworkProfile $socialNetworkProfile, \stdClass $tweet): ?SocialNetworkFeedItem
    {
        $feedItem = new SocialNetworkFeedItem();
        $feedItem->setSocialNetworkProfileId($socialNetworkProfile->getId());

        try {
            $permalink = PermalinkGenerator::generatePermalink($socialNetworkProfile, $tweet);

            $text = $tweet->full_text;
            $dateTime = new \DateTime($tweet->created_at);

            if ($permalink && $text && $dateTime) {
                $feedItem
                    ->setUniqueIdentifier($permalink)
                    ->setPermalink($permalink)
                    ->setText($text)
                    ->setDateTime($dateTime)
                    ->setRaw(json_encode((array)$tweet));

                return $feedItem;
            }

            return $feedItem;
        } catch (\Exception $e) {
            return null;
        }
    }
}