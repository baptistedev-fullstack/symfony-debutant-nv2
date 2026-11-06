<?php

namespace App\Tests\Conversion;

use App\Conversion\LinearConverter;
use App\Conversion\UnsupportedConversionException;
use PHPUnit\Framework\TestCase;

final class LinearConverterTest extends TestCase
{
    private LinearConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new LinearConverter([
            'longueur' => ['m' => 1, 'km' => 1000, 'mi' => 1609.344],
            'masse' => ['g' => 1, 'kg' => 1000],
        ]);
    }

    public function testConvertsThroughTheBaseUnit(): void
    {
        self::assertEqualsWithDelta(1000.0, $this->converter->convert(1.0, 'km', 'm'), 1e-9);
    }

    public function testConvertsBetweenNonBaseUnits(): void
    {
        self::assertEqualsWithDelta(3.218688, $this->converter->convert(2.0, 'mi', 'km'), 1e-9);
    }

    public function testSameUnitKeepsTheValue(): void
    {
        self::assertEqualsWithDelta(5.0, $this->converter->convert(5.0, 'm', 'm'), 1e-9);
    }

    public function testUnitsAreGroupedByCategory(): void
    {
        self::assertSame(
            ['longueur' => ['m', 'km', 'mi'], 'masse' => ['g', 'kg']],
            $this->converter->units(),
        );
    }

    public function testSupportsTwoUnitsOfTheSameCategory(): void
    {
        self::assertTrue($this->converter->supports('km', 'm'));
        self::assertFalse($this->converter->supports('km', 'kg'));
        self::assertFalse($this->converter->supports('km', 'inconnu'));
    }

    public function testRefusesUnitsOfDifferentCategories(): void
    {
        $this->expectException(UnsupportedConversionException::class);

        $this->converter->convert(1.0, 'km', 'kg');
    }
}
