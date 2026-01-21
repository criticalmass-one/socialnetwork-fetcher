<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Homepage;

use App\Model\Item;
use App\Model\Profile;
use Laminas\Feed\Reader\Entry\EntryInterface;

class EntryConverter
{
    private function __construct()
    {

    }

    public static function convert(Profile $profile, EntryInterface $entry): ?Item
    {
        $feedItem = new Item();
        $feedItem->setProfileId($profile->getId());

        try {
            $uniqueId = $entry->getId();
            $permalink = $entry->getPermalink();
            $title = $entry->getTitle();
            $text = $entry->getContent();
            $dateTime = $entry->getDateCreated();

            if ($uniqueId && $permalink && $title && $text && $dateTime) {
                $feedItem
                    ->setUniqueIdentifier($uniqueId)
                    ->setPermalink($permalink)
                    ->setTitle($title)
                    ->setText($text)
                    ->setDateTime($dateTime);

                return $feedItem;
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }
}
