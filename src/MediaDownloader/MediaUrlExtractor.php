<?php declare(strict_types=1);

namespace App\MediaDownloader;

use App\Entity\Item;

class MediaUrlExtractor
{
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

        // Bluesky format: embed.images array
        if (isset($data['embed']['images']) && is_array($data['embed']['images'])) {
            $urls = [];

            foreach ($data['embed']['images'] as $image) {
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
        return $item->getPermalink();
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
