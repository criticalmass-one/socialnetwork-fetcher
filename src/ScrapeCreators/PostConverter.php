<?php declare(strict_types=1);

namespace App\ScrapeCreators;

use App\Model\Item;
use App\Model\Profile;

/**
 * Converts a post as returned by ScrapeCreators (Instagram's own media JSON,
 * used by Threads as well) into a feed item.
 *
 * The permalink has exactly the form RSS.app used, so items that were fetched
 * through RSS.app before are updated instead of duplicated.
 */
class PostConverter
{
    public const PROVIDER = 'scrapecreators';

    private const TITLE_LENGTH = 120;

    public static function convertInstagram(Profile $profile, array $post): ?Item
    {
        $code = $post['code'] ?? null;

        if (!is_string($code) || $code === '') {
            return null;
        }

        return self::convert($profile, $post, 'instagram', 'https://www.instagram.com/p/' . $code);
    }

    public static function convertThreads(Profile $profile, array $post): ?Item
    {
        $code = $post['code'] ?? null;
        $username = $post['user']['username'] ?? null;

        if (!is_string($code) || $code === '' || !is_string($username) || $username === '') {
            return null;
        }

        return self::convert($profile, $post, 'threads', sprintf('https://threads.net/@%s/post/%s', $username, $code));
    }

    private static function convert(Profile $profile, array $post, string $network, string $permalink): ?Item
    {
        $takenAt = $post['taken_at'] ?? null;

        if (!is_numeric($takenAt)) {
            return null;
        }

        $text = is_array($post['caption'] ?? null) ? (string) ($post['caption']['text'] ?? '') : '';
        $media = self::extractMedia($post);

        // The original payload is 20–100 kB per post (every image in a dozen
        // sizes, tracking tokens). Only what the app needs is kept; the media
        // URLs are short-lived CDN links and get refreshed with every fetch.
        $raw = [
            'provider' => self::PROVIDER,
            'network' => $network,
            'id' => (string) ($post['pk'] ?? $post['id'] ?? ''),
            'code' => $post['code'],
            'url' => $permalink,
            'taken_at' => (int) $takenAt,
            'owner' => $post['user']['username'] ?? null,
            'owner_name' => $post['user']['full_name'] ?? null,
            'caption' => $text,
            'product_type' => $post['product_type'] ?? null,
            'like_count' => $post['like_count'] ?? null,
            'comment_count' => $post['comment_count'] ?? null,
            'location' => $post['location']['name'] ?? null,
            'thumbnail' => self::firstUrl($media, 'image'),
            'media' => $media,
        ];

        $item = new Item();
        $item
            ->setUniqueIdentifier($permalink)
            ->setProfileId($profile->getId())
            ->setPermalink($permalink)
            ->setTitle(self::buildTitle($text))
            ->setText($text)
            ->setDateTime(new \DateTime('@' . (int) $takenAt))
            ->setRaw(json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE))
        ;

        return $item;
    }

    /**
     * One entry per picture or video, carousel posts in their order. A video
     * carries its cover as "thumbnail".
     *
     * @return list<array{type: string, url: string, thumbnail?: string}>
     */
    private static function extractMedia(array $post): array
    {
        $nodes = is_array($post['carousel_media'] ?? null) && $post['carousel_media'] !== []
            ? $post['carousel_media']
            : [$post];

        $media = [];

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $image = self::largest($node['image_versions2']['candidates'] ?? null);
            $video = self::largest($node['video_versions'] ?? null);

            if ($video !== null) {
                $entry = ['type' => 'video', 'url' => $video];

                if ($image !== null) {
                    $entry['thumbnail'] = $image;
                }

                $media[] = $entry;
            } elseif ($image !== null) {
                $media[] = ['type' => 'image', 'url' => $image];
            }
        }

        return $media;
    }

    private static function largest(mixed $candidates): ?string
    {
        if (!is_array($candidates)) {
            return null;
        }

        $best = null;
        $bestSize = -1;

        foreach ($candidates as $candidate) {
            $url = $candidate['url'] ?? null;

            if (!is_string($url) || $url === '') {
                continue;
            }

            $size = (int) ($candidate['width'] ?? 0) * (int) ($candidate['height'] ?? 0);

            if ($size > $bestSize) {
                $best = $url;
                $bestSize = $size;
            }
        }

        return $best;
    }

    private static function firstUrl(array $media, string $type): ?string
    {
        foreach ($media as $entry) {
            if ($entry['type'] === $type) {
                return $entry['url'];
            }
        }

        return $media[0]['thumbnail'] ?? null;
    }

    private static function buildTitle(string $text): string
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($line) <= self::TITLE_LENGTH) {
            return $line;
        }

        return mb_substr($line, 0, self::TITLE_LENGTH) . '...';
    }
}
