<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Bluesky;

use App\Model\SocialNetworkProfile;

class IdentifierParser
{
    private function __construct()
    {
    }

    public static function parse(SocialNetworkProfile $socialNetworkProfile): ?string
    {
        $identifier = trim($socialNetworkProfile->getIdentifier() ?? '');

        if ('' === $identifier) {
            return null;
        }

        // https://bsky.app/profile/handle.bsky.social
        if (preg_match('#https?://bsky\.app/profile/([a-zA-Z0-9._-]+(?:\.[a-zA-Z0-9._-]+)+)#i', $identifier, $matches)) {
            return $matches[1];
        }

        // Already a handle: handle.bsky.social or custom domain
        if (preg_match('/^[a-zA-Z0-9._-]+\.[a-zA-Z0-9._-]+$/', $identifier)) {
            return $identifier;
        }

        return null;
    }
}
