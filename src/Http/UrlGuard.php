<?php declare(strict_types=1);

namespace App\Http;

/**
 * Guards every outbound URL the app fetches on its own initiative.
 *
 * Most URLs this app requests are not ours: item permalinks and media URLs come
 * out of third-party feed payloads, profile identifiers come from the API. An
 * unchecked fetch of such a URL is a server-side request forgery primitive —
 * the response is stored (Item::rawSource, downloaded media) and readable
 * again through the API, which turns "fetch a page" into "read anything the
 * server can reach", including the loopback interface and the private network
 * the app is deployed in.
 *
 * The guard therefore only allows http(s) URLs without embedded credentials
 * whose host resolves to a routable public address.
 */
class UrlGuard
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * IPv4 ranges that are never a legitimate fetch target. filter_var()'s
     * NO_PRIV_RANGE / NO_RES_RANGE already cover RFC1918, loopback, link-local
     * and 240.0.0.0/4; these close the remaining holes.
     *
     * @var list<array{string, int}>
     */
    private const EXTRA_BLOCKED_V4 = [
        ['100.64.0.0', 10],  // RFC 6598 carrier-grade NAT
        ['192.0.0.0', 24],   // IETF protocol assignments
        ['192.0.2.0', 24],   // TEST-NET-1
        ['198.18.0.0', 15],  // benchmarking
        ['198.51.100.0', 24],// TEST-NET-2
        ['203.0.113.0', 24], // TEST-NET-3
        ['224.0.0.0', 4],    // multicast
    ];

    public function __construct(
        /**
         * Escape hatch for the test environment (and only for it), where
         * fixtures legitimately point at 127.0.0.1 / localhost.
         */
        private readonly bool $allowPrivateNetworks = false,
    ) {
    }

    /**
     * Cheap check for URLs that are handed to an external process (yt-dlp)
     * rather than fetched by us: it must be a real http(s) URL, which also
     * means it can never be mistaken for a command-line option.
     *
     * @throws UnsafeUrlException
     */
    public function assertFetchableUrl(string $url): void
    {
        $this->hostOf($url);
    }

    /**
     * Full check: scheme, credentials, and the addresses the host resolves to.
     *
     * @throws UnsafeUrlException
     */
    public function resolve(string $url): ResolvedTarget
    {
        $host = $this->hostOf($url);

        $ips = $this->resolveHost($host);

        if ($ips === []) {
            throw new UnsafeUrlException(sprintf('Host "%s" could not be resolved.', $host));
        }

        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw new UnsafeUrlException(sprintf(
                    'Host "%s" resolves into a non-routable network and will not be fetched.',
                    $host,
                ));
            }
        }

        return new ResolvedTarget($url, $host, $ips[0]);
    }

    public function isSafe(string $url): bool
    {
        try {
            $this->resolve($url);

            return true;
        } catch (UnsafeUrlException) {
            return false;
        }
    }

    /**
     * @throws UnsafeUrlException
     */
    private function hostOf(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new UnsafeUrlException('Empty URL.');
        }

        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new UnsafeUrlException(sprintf('"%s" is not an absolute URL.', $url));
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new UnsafeUrlException(sprintf('Scheme "%s" is not allowed.', $scheme));
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException('URLs with embedded credentials are not allowed.');
        }

        $host = trim((string) $parts['host'], '[]');

        if ($host === '') {
            throw new UnsafeUrlException('URL has no host.');
        }

        return $host;
    }

    /** @return list<string> */
    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private function isBlockedIp(string $ip): bool
    {
        if ($this->allowPrivateNetworks) {
            return false;
        }

        $routable = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if ($routable === false) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            foreach (self::EXTRA_BLOCKED_V4 as [$network, $prefix]) {
                if ($this->inV4Range($ip, $network, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function inV4Range(string $ip, string $network, int $prefix): bool
    {
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);

        if ($ipLong === false || $networkLong === false) {
            return false;
        }

        $mask = $prefix === 0 ? 0 : (-1 << (32 - $prefix)) & 0xFFFFFFFF;

        return ($ipLong & $mask) === ($networkLong & $mask);
    }
}
