<?php declare(strict_types=1);

namespace App\Tests\Unit\ScrapeCreators;

use App\ScrapeCreators\HandleParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HandleParserTest extends TestCase
{
    #[DataProvider('identifiers')]
    public function testParse(?string $identifier, ?string $expected): void
    {
        self::assertSame($expected, HandleParser::parse($identifier));
    }

    public static function identifiers(): iterable
    {
        yield 'instagram with slash' => ['https://www.instagram.com/criticalmass.lahore/', 'criticalmass.lahore'];
        yield 'instagram without www' => ['https://instagram.com/criticalmassantwerpen', 'criticalmassantwerpen'];
        yield 'instagram with query' => ['https://www.instagram.com/cm_hh/?hl=de', 'cm_hh'];
        yield 'threads' => ['https://www.threads.com/@edcriticalmass', 'edcriticalmass'];
        yield 'threads.net' => ['https://threads.net/@lacriticalmass/', 'lacriticalmass'];
        yield 'bare handle' => ['@cm_hh', 'cm_hh'];
        yield 'host only' => ['https://www.instagram.com/', null];
        yield 'empty' => ['', null];
        yield 'null' => [null, null];
    }
}
