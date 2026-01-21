<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon;

use App\Model\Profile;
use App\NetworkFeedFetcher\Mastodon\Model\Account;

class IdentifierParser
{
    private function __construct()
    {

    }

    public static function parse(Profile $profile): ?Account
    {
        preg_match('/@?\b([A-Z0-9._%+-]+)@([A-Z0-9.-]+\.[A-Z]{2,})/U', $profile->getIdentifier(), $matches);

        if (0 !== count($matches)) {
            dd($matches);
        }

        $hostname = parse_url($profile->getIdentifier(), PHP_URL_HOST);
        $username = parse_url($profile->getIdentifier(), PHP_URL_PATH);

        return new Account($hostname, trim($username, '/'));
    }
}
