<?php declare(strict_types=1);

namespace App\ScrapeCreators;

/**
 * Extracts the account name from a profile identifier such as
 * https://www.instagram.com/name/ or https://www.threads.com/@name.
 */
class HandleParser
{
    public static function parse(?string $identifier): ?string
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return null;
        }

        $path = str_contains($identifier, '/') ? (string) parse_url($identifier, PHP_URL_PATH) : $identifier;
        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
        $handle = ltrim($segments[0] ?? '', '@');

        return preg_match('/^[A-Za-z0-9._]+$/', $handle) ? $handle : null;
    }
}
