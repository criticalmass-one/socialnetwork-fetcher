<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Instagram;
use App\Model\SocialNetworkFeedItem;
use App\Model\SocialNetworkProfile;

class InstagramConverter
{
    public static function convert(SocialNetworkProfile $profile, array $rssItem): ?SocialNetworkFeedItem
    {
        $item = new SocialNetworkFeedItem();

        $item
            ->setUniqueIdentifier($rssItem['url'])
            ->setSocialNetworkProfileId($profile->getId())
            ->setPermalink($rssItem['url'])
            ->setTitle($rssItem['title'] ?? '')
            ->setText($rssItem['description_text'] ?? $rssItem['description_html'] ?? '')
            ->setDateTime(new \DateTime($rssItem['date_published']))
            ->setRaw(json_encode($rssItem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        ;

        return $item;
    }
}
