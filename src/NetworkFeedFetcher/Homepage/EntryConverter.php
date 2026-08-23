<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Homepage;

use App\Http\SafeHttpFetcher;
use App\Model\Item;
use App\Model\Profile;
use Laminas\Feed\Reader\Entry\EntryInterface;

class EntryConverter
{
    /** An HTML page we keep in Item::rawSource — no reason to accept more. */
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly SafeHttpFetcher $httpFetcher,
    ) {
    }

    public function convert(Profile $profile, EntryInterface $entry, bool $fetchSource = false): ?Item
    {
        $feedItem = new Item();
        $feedItem->setProfileId($profile->getId());

        try {
            $uniqueId = $entry->getId();
            $permalink = $entry->getPermalink();
            $title = $entry->getTitle();
            $text = $entry->getContent();
            $dateTime = $entry->getDateCreated();

            if ($uniqueId && $permalink && $title && $text && $dateTime) {
                $feedItem
                    ->setUniqueIdentifier($uniqueId)
                    ->setPermalink($permalink)
                    ->setTitle($title)
                    ->setText($text)
                    ->setDateTime($dateTime);

                if ($fetchSource && $permalink) {
                    $this->fetchPageSource($feedItem, $permalink);
                }

                return $feedItem;
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    private function fetchPageSource(Item $item, string $permalink): void
    {
        try {
            $html = $this->httpFetcher->get($permalink, self::MAX_BYTES)->content;
            $item->setRawSource($html);

            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_NOERROR);
            $articles = $dom->getElementsByTagName('article');

            if ($articles->length > 0) {
                $articleHtml = '';

                foreach ($articles as $article) {
                    $articleHtml .= $dom->saveHTML($article);
                }

                $item->setSource($articleHtml);
            }
        } catch (\Exception $e) {
            // Source fetching is best-effort, don't fail the entire item
        }
    }
}
