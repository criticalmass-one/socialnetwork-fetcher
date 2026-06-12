<?php declare(strict_types=1);

namespace App\MediaDownloader;

use App\Entity\Item;

class MediaUrlExtractor
{
    /**
     * Networks whose raw payload reliably reveals whether a post contains a
     * video. For these we only hand the permalink to yt-dlp when the raw data
     * actually shows a video attachment — for all other networks (RSS.app,
     * homepage, …) the permalink is probed blindly as before.
     */
    private const VIDEO_DETECTABLE_NETWORKS = ['mastodon', 'bluesky_profile'];

    /**
     * @return list<string>
     */
    public function extractPhotoUrls(Item $item): array
    {
        $raw = $item->getRaw();

        if ($raw === null) {
            return [];
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return [];
        }

        // RSS.app format: extract images from description_html (carousel support + original quality)
        $htmlUrls = $this->extractImageUrlsFromHtml($data['description_html'] ?? '');

        if (!empty($htmlUrls)) {
            return $htmlUrls;
        }

        // RSS.app fallback: single thumbnail field
        if (isset($data['thumbnail']) && is_string($data['thumbnail']) && $data['thumbnail'] !== '') {
            return [$data['thumbnail']];
        }

        // Bluesky format: embed view images (nested under post.embed in fresh
        // raw payloads, top-level embed kept for older payloads)
        $embed = $this->resolveBlueskyEmbed($data);
        $images = $embed['images'] ?? $embed['media']['images'] ?? null;

        if (is_array($images)) {
            $urls = [];

            foreach ($images as $image) {
                if (isset($image['fullsize'])) {
                    $urls[] = $image['fullsize'];
                } elseif (isset($image['thumb'])) {
                    $urls[] = $image['thumb'];
                }
            }

            if (!empty($urls)) {
                return $urls;
            }
        }

        // Mastodon format: media_attachments array
        if (isset($data['media_attachments']) && is_array($data['media_attachments'])) {
            $urls = [];

            foreach ($data['media_attachments'] as $attachment) {
                if (isset($attachment['type']) && $attachment['type'] === 'image' && isset($attachment['url'])) {
                    $urls[] = $attachment['url'];
                }
            }

            if (!empty($urls)) {
                return $urls;
            }
        }

        return [];
    }

    public function extractVideoUrl(Item $item): ?string
    {
        $permalink = $item->getPermalink();

        if (!$permalink) {
            return null;
        }

        $networkIdentifier = $item->getProfile()?->getNetwork()?->getIdentifier();

        if (in_array($networkIdentifier, self::VIDEO_DETECTABLE_NETWORKS, true) && !$this->rawContainsVideo($item)) {
            return null;
        }

        return $permalink;
    }

    private function rawContainsVideo(Item $item): bool
    {
        $raw = $item->getRaw();

        if ($raw === null) {
            return false;
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return false;
        }

        // Mastodon format: media_attachments with type video/gifv
        $attachments = $data['media_attachments'] ?? null;

        if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (in_array($attachment['type'] ?? null, ['video', 'gifv'], true)) {
                    return true;
                }
            }
        }

        // Bluesky format: embed view of type app.bsky.embed.video (playlist
        // holds the HLS URL), also when wrapped in recordWithMedia
        $embed = $this->resolveBlueskyEmbed($data);

        if ($embed !== null) {
            foreach ([$embed['$type'] ?? null, $embed['media']['$type'] ?? null] as $type) {
                if (is_string($type) && str_contains($type, 'embed.video')) {
                    return true;
                }
            }

            if (isset($embed['playlist']) || isset($embed['media']['playlist'])) {
                return true;
            }
        }

        return false;
    }

    private function resolveBlueskyEmbed(array $data): ?array
    {
        $embed = $data['post']['embed'] ?? $data['embed'] ?? null;

        return is_array($embed) ? $embed : null;
    }

    /**
     * @return list<string>
     */
    private function extractImageUrlsFromHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        if (!preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
            return [];
        }

        $urls = [];

        foreach ($matches[1] as $url) {
            if (!str_starts_with($url, 'http')) {
                continue;
            }

            $urls[] = $url;
        }

        return array_values(array_unique($urls));
    }
}
