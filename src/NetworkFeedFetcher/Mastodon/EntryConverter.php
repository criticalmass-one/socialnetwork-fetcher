<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon;

use App\Model\Item;
use App\Model\Profile;
use App\NetworkFeedFetcher\Mastodon\Model\Status;

class EntryConverter
{
    private function __construct()
    {

    }

    public static function convert(Profile $profile, Status $status): ?Item
    {
        $feedItem = new Item();
        $feedItem->setProfileId($profile->getId());

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
