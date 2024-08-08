<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon;

use App\Model\SocialNetworkFeedItem;
use App\Model\SocialNetworkProfile;
use App\NetworkFeedFetcher\Mastodon\Model\Status;

class EntryConverter
{
    private function __construct()
    {

    }

    public static function convert(SocialNetworkProfile $socialNetworkProfile, Status $status): ?SocialNetworkFeedItem
    {
        $feedItem = new SocialNetworkFeedItem();
        $feedItem->setSocialNetworkProfileId($socialNetworkProfile->getId());

        try {
            $uniqueId = $status->getUrl();
            $permalink = $status->getUrl();
            $text = $status->getContent();
            $dateTime = $status->getCreatedAt();

            if ($uniqueId && $permalink && $text && $dateTime) {
                $feedItem
                    ->setUniqueIdentifier($uniqueId)
                    ->setPermalink($permalink)
                    ->setText($text)
                    ->setDateTime($dateTime)
                ;

                return $feedItem;
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }
}
