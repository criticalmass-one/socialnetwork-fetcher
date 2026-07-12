<?php declare(strict_types=1);

namespace App\Group;

use App\Repository\GroupRepository;

/**
 * Generates unguessable, URL-safe slugs for public group pages and guarantees
 * uniqueness against the profile_group.public_slug column.
 */
class PublicSlugGenerator
{
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const LENGTH = 16;
    private const MAX_ATTEMPTS = 10;

    public function __construct(private readonly GroupRepository $groupRepository)
    {
    }

    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $slug = $this->randomSlug();
            if ($this->groupRepository->findOneBy(['publicSlug' => $slug]) === null) {
                return $slug;
            }
        }

        throw new \RuntimeException('Could not generate a unique public slug after ' . self::MAX_ATTEMPTS . ' attempts.');
    }

    private function randomSlug(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $slug = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $slug .= self::ALPHABET[random_int(0, $max)];
        }

        return $slug;
    }
}
