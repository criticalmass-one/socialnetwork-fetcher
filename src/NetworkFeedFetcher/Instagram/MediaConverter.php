<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Instagram;

use App\Model\SocialNetworkFeedItem;
use App\Model\SocialNetworkProfile;
use InstagramScraper\Model\Media;

class MediaConverter
{
    private function __construct()
    {

    }

    public static function convert(SocialNetworkProfile $socialNetworkProfile, Media $media): ?SocialNetworkFeedItem
    {
        $item = new SocialNetworkFeedItem();
        $item
            ->setSocialNetworkProfileId($socialNetworkProfile->getId())
            ->setRaw(self::serializeRawMedia($media))
            ->setDateTime(new \DateTime(sprintf('@%d', $media->getCreatedTime())))
            ->setText($media->getCaption())
            ->setPermalink($media->getLink())
            ->setUniqueIdentifier($media->getLink());

        return $item;
    }

    protected static function serializeRawMedia(Media $media): string
    {
        $serializer = SerializerBuilder::create()->build();

        return $serializer->serialize($media, 'json');
    }
}