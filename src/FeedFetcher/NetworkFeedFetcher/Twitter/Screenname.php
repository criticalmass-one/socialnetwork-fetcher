<?php declare(strict_types=1);

namespace App\FeedFetcher\NetworkFeedFetcher\Twitter;

use App\Model\SocialNetworkProfile;

class Screenname
{
    private function __construct()
    {

    }

    public static function extractScreenname(SocialNetworkProfile $socialNetworkProfile): ?string
    {
        $identifierParts = explode('/', $socialNetworkProfile->getIdentifier());

        return array_pop($identifierParts);
    }

    public static function isValidScreenname(string $screenname): bool
    {
        return (bool)preg_match('/^@?(\w){1,15}$/', $screenname);
    }
}