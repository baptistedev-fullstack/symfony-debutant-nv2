<?php

namespace App\Tests\Conversion;

use App\Conversion\TemperatureConverter;
use App\Conversion\UnsupportedConversionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemperatureConverterTest extends TestCase
{
    /**
     * @return iterable<string, array{float, string, string, float}>
     */
    public static function conversions(): iterable
    {
        yield 'eau gèle' => [0.0, 'C', 'F', 32.0];
        yield 'eau bout' => [100.0, 'C', 'F', 212.0];
        yield 'fahrenheit vers celsius' => [32.0, 'F', 'C', 0.0];
        yield 'celsius vers kelvin' => [0.0, 'C', 'K', 273.15];
        yield 'point commun' => [-40.0, 'C', 'F', -40.0];
        yield 'zéro kelvin' => [0.0, 'K', 'C', -273.15];
    }

    #[DataProvider('conversions')]
    public function testConverts(float $value, string $from, string $to, float $expected): void
    {
        self::assertEqualsWithDelta($expected, (new TemperatureConverter())->convert($value, $from, $to), 1e-9);
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function belowAbsoluteZero(): iterable
    {
        yield 'celsius' => [-300.0, 'C'];
        yield 'kelvin' => [-1.0, 'K'];
        yield 'fahrenheit' => [-500.0, 'F'];
    }

    #[DataProvider('belowAbsoluteZero')]
    public function testRefusesTemperaturesBelowAbsoluteZero(float $value, string $unit): void
    {
        $this->expectException(UnsupportedConversionException::class);

        (new TemperatureConverter())->convert($value, $unit, 'K');
    }

    public function testAcceptsAbsoluteZero(): void
    {
        self::assertEqualsWithDelta(0.0, (new TemperatureConverter())->convert(-273.15, 'C', 'K'), 1e-9);
    }
}
