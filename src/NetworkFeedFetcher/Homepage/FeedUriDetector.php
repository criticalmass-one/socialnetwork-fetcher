<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Homepage;

use App\Model\SocialNetworkProfile;
use Laminas\Feed\Reader\Reader;

class FeedUriDetector
{
    private function __construct()
    {

    }

    public static function findFeedLink(SocialNetworkProfile $socialNetworkProfile): ?string
    {
        $homepageAddress = $socialNetworkProfile->getIdentifier();

        $links = Reader::findFeedLinks($homepageAddress);

        if (isset($links->rdf)) {
            return $links->rdf;
        }

        if (isset($links->rss)) {
            return $links->rss;
        }

        if (isset($links->atom)) {
            return $links->atom;
        }

        return null;
    }
}
