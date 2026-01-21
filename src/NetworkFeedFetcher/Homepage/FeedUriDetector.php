<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Homepage;

use App\Model\Profile;
use Laminas\Feed\Reader\Reader;

class FeedUriDetector
{
    private function __construct()
    {

    }

    public static function findFeedLink(Profile $profile): ?string
    {
        $homepageAddress = $profile->getIdentifier();

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
