<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon;

use App\Model\SocialNetworkProfile;
use App\NetworkFeedFetcher\Mastodon\Model\Account;

class IdentifierParser
{
    private function __construct()
    {

    }

    public static function parse(SocialNetworkProfile $socialNetworkProfile): ?Account
    {
        preg_match('/@?\b([A-Z0-9._%+-]+)@([A-Z0-9.-]+\.[A-Z]{2,})/Ui', $socialNetworkProfile->getIdentifier(), $matches);

        if (3 === count($matches)) {
            return new Account($matches[2], $matches[1]);
        }

        $hostname = parse_url($socialNetworkProfile->getIdentifier(), PHP_URL_HOST);
        $username = parse_url($socialNetworkProfile->getIdentifier(), PHP_URL_PATH);

        if (!$hostname || !$username) {
            return null;
        }

        return new Account($hostname, trim($username, '/@'));
    }
}
