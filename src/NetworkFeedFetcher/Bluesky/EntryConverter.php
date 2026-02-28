<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Bluesky;

use App\Model\SocialNetworkFeedItem;
use App\Model\SocialNetworkProfile;

class EntryConverter
{
    private function __construct()
    {
    }

    public static function convert(SocialNetworkProfile $socialNetworkProfile, array $entry): ?SocialNetworkFeedItem
    {
        try {
            $post = $entry['post'] ?? null;

            if (!$post) {
                return null;
            }

            $record = $post['record'] ?? [];
            $author = $post['author'] ?? [];

            $text = $record['text'] ?? null;
            $createdAt = $record['createdAt'] ?? null;
            $uri = $post['uri'] ?? null;
            $handle = $author['handle'] ?? null;

            if (!$text || !$createdAt || !$uri || !$handle) {
                return null;
            }

            $permalink = self::buildPermalink($handle, $uri);

            $feedItem = new SocialNetworkFeedItem();
            $feedItem
                ->setSocialNetworkProfileId($socialNetworkProfile->getId())
                ->setUniqueIdentifier($uri)
                ->setPermalink($permalink)
                ->setText($text)
                ->setDateTime(new \DateTime($createdAt))
                ->setRaw(json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ;

            return $feedItem;
        } catch (\Exception) {
            return null;
        }
    }

    private static function buildPermalink(string $handle, string $atUri): string
    {
        // at://did:plc:xxx/app.bsky.feed.post/yyy -> https://bsky.app/profile/handle/post/yyy
        $parts = explode('/', $atUri);
        $postId = end($parts);

        return sprintf('https://bsky.app/profile/%s/post/%s', $handle, $postId);
    }
}
