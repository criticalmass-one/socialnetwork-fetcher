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

    public static function convert(Profile $profile, Status $status, ?array $rawEntry = null): ?Item
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

                if ($rawEntry !== null) {
                    $feedItem->setRaw(json_encode($rawEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }

                return $feedItem;
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }
}
