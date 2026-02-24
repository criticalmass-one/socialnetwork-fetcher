<?php declare(strict_types=1);

namespace App\Tests\NetworkFeedFetcher\Mastodon;

use App\Model\SocialNetworkProfile;
use App\NetworkFeedFetcher\Mastodon\IdentifierParser;
use PHPUnit\Framework\TestCase;

class IdentifierParserTest extends TestCase
{
    public function testParseHandleFormat(): void
    {
        $profile = (new SocialNetworkProfile())->setIdentifier('@criticalmass@mastodon.social');

        $account = IdentifierParser::parse($profile);

        $this->assertNotNull($account);
        $this->assertSame('mastodon.social', $account->getHostname());
        $this->assertSame('criticalmass', $account->getUsername());
    }

    public function testParseHandleFormatWithoutLeadingAt(): void
    {
        $profile = (new SocialNetworkProfile())->setIdentifier('criticalmass@mastodon.social');

        $account = IdentifierParser::parse($profile);

        $this->assertNotNull($account);
        $this->assertSame('mastodon.social', $account->getHostname());
        $this->assertSame('criticalmass', $account->getUsername());
    }

    public function testParseUrlFormat(): void
    {
        $profile = (new SocialNetworkProfile())->setIdentifier('https://mastodon.social/@criticalmass');

        $account = IdentifierParser::parse($profile);

        $this->assertNotNull($account);
        $this->assertSame('mastodon.social', $account->getHostname());
        $this->assertSame('criticalmass', $account->getUsername());
    }

    public function testParseUrlFormatWithTrailingSlash(): void
    {
        $profile = (new SocialNetworkProfile())->setIdentifier('https://chaos.social/@cm_hamburg/');

        $account = IdentifierParser::parse($profile);

        $this->assertNotNull($account);
        $this->assertSame('chaos.social', $account->getHostname());
        $this->assertSame('cm_hamburg', $account->getUsername());
    }

    public function testParseHandleWithSubdomain(): void
    {
        $profile = (new SocialNetworkProfile())->setIdentifier('@user@social.example.org');

        $account = IdentifierParser::parse($profile);

        $this->assertNotNull($account);
        $this->assertSame('social.example.org', $account->getHostname());
        $this->assertSame('user', $account->getUsername());
    }

    public function testParseReturnsNullForInvalidIdentifier(): void
    {
        $profile = (new SocialNetworkProfile())->setIdentifier('not-a-valid-identifier');

        $account = IdentifierParser::parse($profile);

        $this->assertNull($account);
    }

    public function testParseReturnsNullForEmptyString(): void
    {
        $profile = (new SocialNetworkProfile())->setIdentifier('');

        $account = IdentifierParser::parse($profile);

        $this->assertNull($account);
    }
}
