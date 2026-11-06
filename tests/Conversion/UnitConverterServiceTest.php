<?php

namespace App\Tests\Conversion;

use App\Conversion\UnitConverter;
use App\Conversion\UnsupportedConversionException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UnitConverterServiceTest extends KernelTestCase
{
    public function testContainerInjectsTheConvertersAndThePrecision(): void
    {
        $converter = self::getContainer()->get(UnitConverter::class);

        self::assertSame(1.6093, $converter->convert(1.0, 'mi', 'km'));
        self::assertSame(212.0, $converter->convert(100.0, 'C', 'F'));
    }

    public function testEveryConfiguredCategoryIsExposed(): void
    {
        $categories = array_keys(self::getContainer()->get(UnitConverter::class)->units());

        self::assertContains('longueur', $categories);
        self::assertContains('masse', $categories);
        self::assertContains('température', $categories);
    }

    public function testHugeValueIsRefused(): void
    {
        $this->expectException(UnsupportedConversionException::class);

        self::getContainer()->get(UnitConverter::class)->convert(1e308, 'km', 'mm');
    }
}
