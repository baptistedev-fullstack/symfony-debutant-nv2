<?php

namespace App\Conversion;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.converter')]
interface ConverterInterface
{
    /**
     * @return array<string, list<string>> unités par catégorie
     */
    public function units(): array;

    public function supports(string $from, string $to): bool;

    /**
     * @throws UnsupportedConversionException
     */
    public function convert(float $value, string $from, string $to): float;
}
