<?php

namespace App\Conversion;

final class TemperatureConverter implements ConverterInterface
{
    private const UNITS = ['C', 'F', 'K'];
    private const TOLERANCE = 1e-9;

    public function units(): array
    {
        return ['température' => self::UNITS];
    }

    public function supports(string $from, string $to): bool
    {
        return \in_array($from, self::UNITS, true) && \in_array($to, self::UNITS, true);
    }

    public function convert(float $value, string $from, string $to): float
    {
        if (!$this->supports($from, $to)) {
            throw new UnsupportedConversionException(sprintf('Conversion impossible entre « %s » et « %s ».', $from, $to));
        }

        $kelvin = $this->toKelvin($value, $from);

        if ($kelvin < -self::TOLERANCE) {
            throw new UnsupportedConversionException('Une température ne peut pas être inférieure au zéro absolu.');
        }

        return $this->fromKelvin($kelvin, $to);
    }

    private function toKelvin(float $value, string $unit): float
    {
        return match ($unit) {
            'C' => $value + 273.15,
            'F' => ($value - 32) * 5 / 9 + 273.15,
            'K' => $value,
        };
    }

    private function fromKelvin(float $kelvin, string $unit): float
    {
        return match ($unit) {
            'C' => $kelvin - 273.15,
            'F' => ($kelvin - 273.15) * 9 / 5 + 32,
            'K' => $kelvin,
        };
    }
}
