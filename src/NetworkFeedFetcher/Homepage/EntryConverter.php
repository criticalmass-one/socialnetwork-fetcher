<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Homepage;

use App\Model\Item;
use App\Model\Profile;
use Laminas\Feed\Reader\Entry\EntryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EntryConverter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
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
            $html = $this->httpClient->request('GET', $permalink)->getContent();
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
