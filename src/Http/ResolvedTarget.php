<?php declare(strict_types=1);

namespace App\Http;

/**
 * A URL that passed {@see UrlGuard}, together with the IP address the host
 * resolved to. Pinning that IP for the actual request (Symfony HttpClient's
 * `resolve` option) closes the window between the check and the connect, so a
 * host cannot answer with a public address during validation and a private one
 * a moment later (DNS rebinding).
 */
final readonly class ResolvedTarget
{
    public function __construct(
        public string $url,
        public string $host,
        public string $ip,
    ) {
    }

    /** @return array<string, string> Host-to-IP map for HttpClient's `resolve` option. */
    public function resolveOption(): array
    {
        return [$this->host => $this->ip];
    }
}
