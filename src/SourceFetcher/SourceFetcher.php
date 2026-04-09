<?php declare(strict_types=1);

namespace App\SourceFetcher;

use App\Model\Item;
use App\Model\Profile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SourceFetcher
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
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

        try {
            $response = $this->httpClient->request('GET', $permalink);
            $html = $response->getContent();
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
