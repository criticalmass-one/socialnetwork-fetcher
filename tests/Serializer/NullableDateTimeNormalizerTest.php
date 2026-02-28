<?php declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Serializer\NullableDateTimeNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NullableDateTimeNormalizerTest extends TestCase
{
    private NullableDateTimeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new NullableDateTimeNormalizer();
    }

    #[DataProvider('nullableDataProvider')]
    public function testDenormalizeReturnsNullForEmptyData(mixed $data): void
    {
        $result = $this->normalizer->denormalize($data, \DateTime::class);

        $this->assertNull($result);
    }

    public static function nullableDataProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace' => ['   '],
        ];
    }

    public function testDenormalizeValidDateString(): void
    {
        $result = $this->normalizer->denormalize('2025-01-15T12:30:00+00:00', \DateTime::class);

        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertEquals('2025-01-15', $result->format('Y-m-d'));
    }

    public function testSupportsDenormalization(): void
    {
        $this->assertTrue($this->normalizer->supportsDenormalization('2025-01-01', \DateTime::class));
        $this->assertTrue($this->normalizer->supportsDenormalization(null, \DateTime::class));
        $this->assertFalse($this->normalizer->supportsDenormalization('2025-01-01', \stdClass::class));
    }
}
