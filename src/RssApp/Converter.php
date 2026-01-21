<?php declare(strict_types=1);

namespace App\RssApp;

use App\Model\Item;
use App\Model\Profile;

class Converter
{
    public static function convert(Profile $profile, array $rssItem): ?Item
    {
        $item = new Item();

        $item
            ->setUniqueIdentifier($rssItem['url'])
            ->setProfileId($profile->getId())
            ->setPermalink($rssItem['url'])
            ->setTitle($rssItem['title'] ?? '')
            ->setText($rssItem['description_text'] ?? $rssItem['description_html'] ?? '')
            ->setDateTime(new \DateTime($rssItem['date_published']))
            ->setRaw(json_encode($rssItem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        ;

        return $item;
    }
}
