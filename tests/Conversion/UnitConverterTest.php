<?php

namespace App\Tests\Conversion;

use App\Conversion\ConverterInterface;
use App\Conversion\UnitConverter;
use App\Conversion\UnsupportedConversionException;
use PHPUnit\Framework\TestCase;

final class UnitConverterTest extends TestCase
{
    public function testUsesTheConverterThatSupportsTheUnits(): void
    {
        $first = $this->converter(supports: false);
        $second = $this->converter(supports: true, result: 42.0);

        self::assertSame(42.0, (new UnitConverter([$first, $second], 4))->convert(1.0, 'a', 'b'));
    }

    public function testRoundsToTheConfiguredPrecision(): void
    {
        $stub = $this->converter(supports: true, result: 1.609344);

        self::assertSame(1.6093, (new UnitConverter([$stub], 4))->convert(1.0, 'mi', 'km'));
        self::assertSame(1.61, (new UnitConverter([$stub], 2))->convert(1.0, 'mi', 'km'));
    }

    public function testNeverReturnsNegativeZero(): void
    {
        $stub = $this->converter(supports: true, result: -0.00001);

        self::assertSame('0', (string) (new UnitConverter([$stub], 2))->convert(1.0, 'a', 'b'));
    }

    public function testRefusesWhenNoConverterSupportsTheUnits(): void
    {
        $this->expectException(UnsupportedConversionException::class);

        (new UnitConverter([$this->converter(supports: false)], 4))->convert(1.0, 'a', 'b');
    }

    public function testRefusesAnInfiniteResult(): void
    {
        $this->expectException(UnsupportedConversionException::class);

        (new UnitConverter([$this->converter(supports: true, result: INF)], 4))->convert(1.0, 'a', 'b');
    }

    public function testMergesTheUnitsOfEveryConverter(): void
    {
        $first = $this->converter(units: ['longueur' => ['m']]);
        $second = $this->converter(units: ['masse' => ['kg']]);

        self::assertSame(
            ['longueur' => ['m'], 'masse' => ['kg']],
            (new UnitConverter([$first, $second], 4))->units(),
        );
    }

    /**
     * @param array<string, list<string>> $units
     */
    private function converter(bool $supports = false, float $result = 0.0, array $units = []): ConverterInterface
    {
        $stub = $this->createStub(ConverterInterface::class);
        $stub->method('supports')->willReturn($supports);
        $stub->method('convert')->willReturn($result);
        $stub->method('units')->willReturn($units);

        return $stub;
    }
}
