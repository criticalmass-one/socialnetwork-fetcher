<?php declare(strict_types=1);

namespace App\Tests\NetworkFeedFetcher\Mastodon;

use App\Model\SocialNetworkProfile;
use App\NetworkFeedFetcher\Mastodon\IdentifierParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentifierParserTest extends TestCase
{
    #[DataProvider('handleFormatProvider')]
    public function testParseHandleFormat(string $identifier, string $expectedHostname, string $expectedUsername): void
    {
        $profile = (new SocialNetworkProfile())->setIdentifier($identifier);

        $account = IdentifierParser::parse($profile);

        $this->assertNotNull($account, sprintf('Expected Account for identifier "%s", got null', $identifier));
        $this->assertSame($expectedHostname, $account->getHostname());
        $this->assertSame($expectedUsername, $account->getUsername());
    }

    public static function handleFormatProvider(): array
    {
        return [
            'standard handle' => ['@criticalmass@mastodon.social', 'mastodon.social', 'criticalmass'],
            'without leading @' => ['criticalmass@mastodon.social', 'mastodon.social', 'criticalmass'],
            'subdomain instance' => ['@user@social.example.org', 'social.example.org', 'user'],
            'dots in username' => ['@critical.mass@mastodon.social', 'mastodon.social', 'critical.mass'],
            'numbers in username' => ['@cm2023@mastodon.social', 'mastodon.social', 'cm2023'],
            'long TLD' => ['@user@mastodon.technology', 'mastodon.technology', 'user'],
            'short TLD' => ['@user@mas.to', 'mas.to', 'user'],
            'country TLD' => ['@radfahren@social.dev.de', 'social.dev.de', 'radfahren'],
            'uppercase handle' => ['@CriticalMass@Mastodon.Social', 'Mastodon.Social', 'CriticalMass'],
            'mixed case' => ['@CM_Hamburg@chaos.social', 'chaos.social', 'CM_Hamburg'],
            'hyphen in username' => ['@critical-mass@mastodon.social', 'mastodon.social', 'critical-mass'],
            'plus in username' => ['@user+tag@mastodon.social', 'mastodon.social', 'user+tag'],
            'percent in username' => ['@user%40@mastodon.social', 'mastodon.social', 'user%40'],
        ];
    }

    #[DataProvider('urlFormatProvider')]
    public function testParseUrlFormat(string $identifier, string $expectedHostname, string $expectedUsername): void
    {
        $profile = (new SocialNetworkProfile())->setIdentifier($identifier);

        $account = IdentifierParser::parse($profile);

        $this->assertNotNull($account, sprintf('Expected Account for identifier "%s", got null', $identifier));
        $this->assertSame($expectedHostname, $account->getHostname());
        $this->assertSame($expectedUsername, $account->getUsername());
    }

    public static function urlFormatProvider(): array
    {
        return [
            'https with @' => ['https://mastodon.social/@criticalmass', 'mastodon.social', 'criticalmass'],
            'https with trailing slash' => ['https://chaos.social/@cm_hamburg/', 'chaos.social', 'cm_hamburg'],
            'http scheme' => ['http://mastodon.social/@user', 'mastodon.social', 'user'],
            'subdomain URL' => ['https://social.example.org/@user', 'social.example.org', 'user'],
            'without @ in path' => ['https://mastodon.social/criticalmass', 'mastodon.social', 'criticalmass'],
        ];
    }

    #[DataProvider('invalidIdentifierProvider')]
    public function testParseReturnsNullForInvalidIdentifier(string $identifier): void
    {
        $profile = (new SocialNetworkProfile())->setIdentifier($identifier);

        $account = IdentifierParser::parse($profile);

        $this->assertNull($account, sprintf('Expected null for identifier "%s"', $identifier));
    }

    public static function invalidIdentifierProvider(): array
    {
        return [
            'empty string' => [''],
            'plain text' => ['not-a-valid-identifier'],
            'just a username' => ['criticalmass'],
            'single @' => ['@criticalmass'],
            'just a domain' => ['mastodon.social'],
        ];
    }
}
