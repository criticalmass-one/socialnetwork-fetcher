<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\Profile;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Display name for a profile on the public page: the explicit title when set,
 * otherwise the account handle derived from the identifier (the last path
 * segment of the profile URL), falling back to the raw identifier. Keeps the
 * public feed from showing full URLs like "https://www.instagram.com/foo/"
 * under the post when a profile has no title configured.
 */
class PublicProfileNameExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('public_profile_name', $this->publicName(...)),
        ];
    }

    public function publicName(?Profile $profile): string
    {
        if ($profile === null) {
            return '?';
        }

        $title = $profile->getTitle();
        if ($title !== null && trim($title) !== '') {
            return trim($title);
        }

        return $this->handleFromIdentifier($profile->getIdentifier());
    }

    private function handleFromIdentifier(?string $identifier): string
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '') {
            return '?';
        }

        // Prefer the URL path (drops scheme/host/query), then take the last
        // non-empty segment — the account handle.
        $path = parse_url($identifier, PHP_URL_PATH);
        $candidate = is_string($path) && $path !== '' ? $path : $identifier;

        $segments = array_values(array_filter(
            explode('/', $candidate),
            static fn (string $segment): bool => trim($segment) !== '',
        ));

        $handle = end($segments);

        return $handle !== false && $handle !== '' ? $handle : $identifier;
    }
}
