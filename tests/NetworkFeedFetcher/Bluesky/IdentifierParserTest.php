<?php declare(strict_types=1);

namespace App\Tests\NetworkFeedFetcher\Bluesky;

use App\Model\SocialNetworkProfile;
use App\NetworkFeedFetcher\Bluesky\IdentifierParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentifierParserTest extends TestCase
{
    #[DataProvider('validIdentifierProvider')]
    public function testParseValidIdentifier(string $identifier, string $expectedHandle): void
    {
        $profile = new SocialNetworkProfile();
        $profile->setIdentifier($identifier);

        $this->assertEquals($expectedHandle, IdentifierParser::parse($profile));
    }

    public static function validIdentifierProvider(): array
    {
        return [
            'bsky.app URL' => [
                'https://bsky.app/profile/criticalmass.bsky.social',
                'criticalmass.bsky.social',
            ],
            'bsky.app URL with trailing slash' => [
                'https://bsky.app/profile/criticalmass.bsky.social/',
                'criticalmass.bsky.social',
            ],
            'custom domain handle in URL' => [
                'https://bsky.app/profile/jay.bsky.team',
                'jay.bsky.team',
            ],
            'bare handle' => [
                'criticalmass.bsky.social',
                'criticalmass.bsky.social',
            ],
            'bare custom domain handle' => [
                'example.com',
                'example.com',
            ],
        ];
    }

    #[DataProvider('invalidIdentifierProvider')]
    public function testParseInvalidIdentifier(?string $identifier): void
    {
        $profile = new SocialNetworkProfile();

        if (null !== $identifier) {
            $profile->setIdentifier($identifier);
        }

        $this->assertNull(IdentifierParser::parse($profile));
    }

    public static function invalidIdentifierProvider(): array
    {
        return [
            'empty string' => [''],
            'just a word' => ['bluesky'],
        ];
    }
}
