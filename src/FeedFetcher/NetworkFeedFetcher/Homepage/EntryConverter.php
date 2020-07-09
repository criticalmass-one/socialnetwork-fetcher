<?php declare(strict_types=1);

namespace App\FeedFetcher\NetworkFeedFetcher\Homepage;

use App\Model\SocialNetworkFeedItem;
use App\Model\SocialNetworkProfile;
use Zend\Feed\Reader\Entry\EntryInterface;

class EntryConverter
{
    private function __construct()
    {

    }

    public static function convert(SocialNetworkProfile $socialNetworkProfile, EntryInterface $entry): ?SocialNetworkFeedItem
    {
        $feedItem = new SocialNetworkFeedItem();
        $feedItem->setSocialNetworkProfileId($socialNetworkProfile->getId());

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