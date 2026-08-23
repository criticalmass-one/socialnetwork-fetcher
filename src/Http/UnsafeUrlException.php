<?php declare(strict_types=1);

namespace App\Http;

/**
 * Thrown when an outbound URL is rejected by {@see UrlGuard} — an unsupported
 * scheme, embedded credentials, an unresolvable host, or a host that resolves
 * into a private / loopback / link-local network.
 */
class UnsafeUrlException extends \RuntimeException
{
}
