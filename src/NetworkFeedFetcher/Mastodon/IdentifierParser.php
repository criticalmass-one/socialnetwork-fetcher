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
        preg_match('/@?\b([A-Z0-9._%+-]+)@([A-Z0-9.-]+\.[A-Z]{2,})/i', $profile->getIdentifier(), $matches);

        if (3 === count($matches)) {
            return new Account($matches[2], $matches[1]);
        }

        $hostname = parse_url($profile->getIdentifier(), PHP_URL_HOST);
        $path = parse_url($profile->getIdentifier(), PHP_URL_PATH);

        if (!$hostname || !$path) {
            return null;
        }

        // Only the account segment counts: a post URL (/@user/110747383693611484) names the
        // same account, a hashtag page (/tags/…) none at all.
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => '' !== $segment));
        $username = match (true) {
            [] === $segments => null,
            'users' === $segments[0] => $segments[1] ?? null,
            in_array($segments[0], ['tags', 'explore', 'public', 'web', 'about'], true) => null,
            default => ltrim($segments[0], '@'),
        };

        if (null === $username || '' === $username) {
            return null;
        }

        return new Account($hostname, $username);
    }
}
