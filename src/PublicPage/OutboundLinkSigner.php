<?php declare(strict_types=1);

namespace App\PublicPage;

/**
 * Signs outbound public-page links so the click-tracking redirect can only be
 * used for URLs the page itself generated — preventing it from becoming an open
 * redirect.
 */
class OutboundLinkSigner
{
    public function __construct(private readonly string $appSecret)
    {
    }

    public function sign(string $url): string
    {
        return substr(hash_hmac('sha256', $url, $this->appSecret), 0, 16);
    }

    public function verify(string $url, string $signature): bool
    {
        return $signature !== '' && hash_equals($this->sign($url), $signature);
    }
}
