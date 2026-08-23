<?php declare(strict_types=1);

namespace App\SourceFetcher;

use App\Http\SafeHttpFetcher;
use App\Model\Item;
use App\Model\Profile;

class SourceFetcher
{
    /** An HTML page we keep in Item::rawSource — no reason to accept more. */
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly SafeHttpFetcher $httpFetcher,
    ) {
    }

    public function fetch(Item $item, Profile $profile): void
    {
        if (!$profile->isFetchSource()) {
            return;
        }

        $permalink = $item->getPermalink();

        if (!$permalink) {
            return;
        }

        // The permalink comes out of a third-party feed, so it is guarded
        // against pointing back into our own network before it is fetched.
        try {
            $html = $this->httpFetcher->get($permalink, self::MAX_BYTES)->content;
        } catch (\Throwable) {
            return;
        }

        $item->setRawSource($html);

        $articleContent = $this->parseArticle($html);

        if ($articleContent !== null) {
            $item->setParsedSource($articleContent);
        }
    }

    private function parseArticle(string $html): ?string
    {
        $previousUseErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML(mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, -1], 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        $articles = $dom->getElementsByTagName('article');

        if ($articles->length === 0) {
            return null;
        }

        $article = $articles->item(0);
        $innerHTML = '';

        foreach ($article->childNodes as $child) {
            $innerHTML .= $dom->saveHTML($child);
        }

        return trim($innerHTML);
    }
}
