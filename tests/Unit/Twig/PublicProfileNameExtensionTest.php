<?php declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Entity\Profile;
use App\Twig\PublicProfileNameExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PublicProfileNameExtensionTest extends TestCase
{
    private PublicProfileNameExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new PublicProfileNameExtension();
    }

    public function testPrefersExplicitTitle(): void
    {
        $profile = (new Profile())
            ->setIdentifier('https://www.instagram.com/torsten.franz.lg/')
            ->setTitle('Torsten Franz');

        self::assertSame('Torsten Franz', $this->extension->publicName($profile));
    }

    public function testBlankTitleFallsBackToHandle(): void
    {
        $profile = (new Profile())
            ->setIdentifier('https://www.instagram.com/torsten.franz.lg/')
            ->setTitle('   ');

        self::assertSame('torsten.franz.lg', $this->extension->publicName($profile));
    }

    #[DataProvider('identifierProvider')]
    public function testDerivesHandleFromIdentifier(string $identifier, string $expected): void
    {
        $profile = (new Profile())->setIdentifier($identifier);

        self::assertSame($expected, $this->extension->publicName($profile));
    }

    public static function identifierProvider(): iterable
    {
        yield 'instagram trailing slash' => ['https://www.instagram.com/torsten.franz.lg/', 'torsten.franz.lg'];
        yield 'instagram no trailing slash' => ['https://www.instagram.com/cl_kalisch', 'cl_kalisch'];
        yield 'facebook page' => ['https://www.facebook.com/GrueneLueneburg', 'GrueneLueneburg'];
        yield 'url with query' => ['https://www.instagram.com/foo/?hl=de', 'foo'];
        yield 'bare handle without scheme' => ['onlya.bsky.social', 'onlya.bsky.social'];
    }

    public function testNullProfileReturnsPlaceholder(): void
    {
        self::assertSame('?', $this->extension->publicName(null));
    }

    public function testEmptyIdentifierReturnsPlaceholder(): void
    {
        $profile = (new Profile())->setIdentifier('');

        self::assertSame('?', $this->extension->publicName($profile));
    }
}
