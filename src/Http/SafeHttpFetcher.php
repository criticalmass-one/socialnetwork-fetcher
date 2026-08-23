<?php declare(strict_types=1);

namespace App\Http;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * GETs a URL that the app does not control — an item permalink, a media URL out
 * of a feed payload — with every safety net {@see UrlGuard} needs to actually
 * hold:
 *
 *  - redirects are followed by hand so that *every* hop is guarded, not just
 *    the URL we started with (otherwise a public URL redirecting to
 *    http://127.0.0.1/ walks straight past the check),
 *  - the validated IP is pinned for the connection,
 *  - TLS verification is explicit,
 *  - the response body is capped, so a hostile server cannot exhaust memory.
 */
class SafeHttpFetcher
{
    public const DEFAULT_MAX_BYTES = 25 * 1024 * 1024;

    private const MAX_REDIRECTS = 5;
    private const TIMEOUT = 15.0;
    private const MAX_DURATION = 120.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGuard $urlGuard,
    ) {
    }

    /**
     * @throws UnsafeUrlException     if the URL (or a redirect target) is not fetchable
     * @throws HttpExceptionInterface on transport/HTTP errors
     */
    public function get(string $url, int $maxBytes = self::DEFAULT_MAX_BYTES): FetchedResource
    {
        $currentUrl = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; ++$hop) {
            $target = $this->urlGuard->resolve($currentUrl);

            $response = $this->httpClient->request('GET', $currentUrl, [
                'max_redirects' => 0,
                'resolve' => $target->resolveOption(),
                'timeout' => self::TIMEOUT,
                'max_duration' => self::MAX_DURATION,
                'verify_peer' => true,
                'verify_host' => true,
            ]);

            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders(false);

            if ($statusCode >= 300 && $statusCode < 400) {
                $location = $headers['location'][0] ?? null;
                $response->cancel();

                if ($location === null || trim($location) === '') {
                    throw new UnsafeUrlException(sprintf('Redirect from "%s" without a Location header.', $currentUrl));
                }

                $currentUrl = $this->absolutize($currentUrl, trim($location));

                continue;
            }

            // Any non-2xx surfaces as the usual HttpClient exception.
            $response->getHeaders();

            return new FetchedResource(
                url: $currentUrl,
                statusCode: $statusCode,
                content: $this->readCapped($response, $maxBytes, $currentUrl),
                headers: $headers,
            );
        }

        throw new UnsafeUrlException(sprintf('Too many redirects while fetching "%s".', $url));
    }

    private function readCapped(ResponseInterface $response, int $maxBytes, string $url): string
    {
        $content = '';

        foreach ($this->httpClient->stream($response) as $chunk) {
            $content .= $chunk->getContent();

            if (\strlen($content) > $maxBytes) {
                $response->cancel();

                throw new UnsafeUrlException(sprintf(
                    'Response from "%s" exceeds the %d byte limit.',
                    $url,
                    $maxBytes,
                ));
            }
        }

        return $content;
    }

    /** Resolve a possibly relative Location header against the URL it came from. */
    private function absolutize(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new UnsafeUrlException(sprintf('Cannot resolve redirect "%s" against "%s".', $location, $base));
        }

        $origin = sprintf(
            '%s://%s%s',
            $parts['scheme'],
            $parts['host'],
            isset($parts['port']) ? ':' . $parts['port'] : '',
        );

        if (str_starts_with($location, '//')) {
            return $parts['scheme'] . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $path = $parts['path'] ?? '/';
        $directory = substr($path, 0, (int) strrpos($path, '/') + 1);

        return $origin . ($directory === '' ? '/' : $directory) . $location;
    }
}
