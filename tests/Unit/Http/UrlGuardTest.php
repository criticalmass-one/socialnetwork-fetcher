<?php declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\UnsafeUrlException;
use App\Http\UrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Only IP literals are used here, so the tests never depend on DNS.
 */
class UrlGuardTest extends TestCase
{
    #[DataProvider('blockedUrls')]
    public function testRejectsUnsafeUrls(string $url): void
    {
        $this->expectException(UnsafeUrlException::class);

        (new UrlGuard())->resolve($url);
    }

    /** @return iterable<string, array{string}> */
    public static function blockedUrls(): iterable
    {
        yield 'loopback v4' => ['http://127.0.0.1/admin'];
        yield 'loopback name-less v6' => ['http://[::1]/'];
        yield 'private 10/8' => ['http://10.0.0.5/'];
        yield 'private 192.168/16' => ['https://192.168.1.1/status'];
        yield 'private 172.16/12' => ['http://172.16.0.1/'];
        yield 'link-local metadata' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'carrier-grade nat' => ['http://100.64.0.1/'];
        yield 'multicast' => ['http://224.0.0.1/'];
        yield 'ipv4-mapped loopback' => ['http://[::ffff:127.0.0.1]/'];
        yield 'unique local v6' => ['http://[fd00::1]/'];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'gopher scheme' => ['gopher://example.com/'];
        yield 'no scheme' => ['/etc/passwd'];
        yield 'yt-dlp option' => ['--exec=touch /tmp/pwned'];
        yield 'embedded credentials' => ['http://user:pass@8.8.8.8/'];
        yield 'empty' => [''];
    }

    public function testAcceptsPublicAddress(): void
    {
        $target = (new UrlGuard())->resolve('https://8.8.8.8/some/path');

        $this->assertSame('8.8.8.8', $target->host);
        $this->assertSame('8.8.8.8', $target->ip);
        $this->assertSame(['8.8.8.8' => '8.8.8.8'], $target->resolveOption());
    }

    public function testPrivateNetworksCanBeAllowedForTests(): void
    {
        $this->assertTrue((new UrlGuard(allowPrivateNetworks: true))->isSafe('http://127.0.0.1:8000/'));
    }

    public function testSchemeCheckStillAppliesWhenPrivateNetworksAreAllowed(): void
    {
        $this->expectException(UnsafeUrlException::class);

        (new UrlGuard(allowPrivateNetworks: true))->assertFetchableUrl('--exec=id');
    }
}
