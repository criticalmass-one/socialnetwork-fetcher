<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Renders post captions for the public page: escapes the raw text, then turns
 * URLs, #hashtags and @mentions into markup. Escaping happens first, so the
 * result is safe to emit raw.
 */
class PublicLinkifyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('public_linkify', $this->linkify(...), ['is_safe' => ['html']]),
        ];
    }

    public function linkify(?string $text): string
    {
        $escaped = htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $escaped = preg_replace(
            '#(https?://[^\s<]+)#',
            '<a href="$1" target="_blank" rel="noopener nofollow">$1</a>',
            $escaped,
        ) ?? $escaped;

        $escaped = preg_replace(
            '/(^|\s)(#[\wäöüÄÖÜß]+)/u',
            '$1<span class="hashtag">$2</span>',
            $escaped,
        ) ?? $escaped;

        $escaped = preg_replace(
            '/(^|\s)(@[\w.]+)/u',
            '$1<span class="hashtag">$2</span>',
            $escaped,
        ) ?? $escaped;

        return $escaped;
    }
}
