<?php

namespace App\Conversion;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class UnitConverter
{
    /**
     * @param iterable<ConverterInterface> $converters
     */
    public function __construct(
        #[AutowireIterator('app.converter')]
        private iterable $converters,
        #[Autowire(env: 'int:APP_CONVERSION_PRECISION')]
        private int $precision,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function units(): array
    {
        $units = [];
        foreach ($this->converters as $converter) {
            $units = array_merge_recursive($units, $converter->units());
        }

        return $units;
    }

    /**
     * @throws UnsupportedConversionException
     */
    public function convert(float $value, string $from, string $to): float
    {
        foreach ($this->converters as $converter) {
            if (!$converter->supports($from, $to)) {
                continue;
            }

            $result = $converter->convert($value, $from, $to);
            if (!is_finite($result)) {
                throw new UnsupportedConversionException('Le résultat est trop grand pour être affiché.');
            }

            // + 0.0 transforme -0.0 en 0.0 : sans lui, un petit résultat négatif arrondi s'afficherait « -0 ».
            return round($result, $this->precision) + 0.0;
        }

        throw new UnsupportedConversionException(sprintf('Conversion impossible entre « %s » et « %s ».', $from, $to));
    }
}
