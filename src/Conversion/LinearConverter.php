<?php

namespace App\Conversion;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LinearConverter implements ConverterInterface
{
    /**
     * @param array<string, array<string, int|float>> $factors facteur vers l'unité de base, par catégorie puis par unité
     */
    public function __construct(
        #[Autowire(param: 'app.conversion.units')]
        private array $factors,
    ) {
    }

    public function units(): array
    {
        return array_map(
            static fn (array $units): array => array_map('strval', array_keys($units)),
            $this->factors,
        );
    }

    public function supports(string $from, string $to): bool
    {
        return null !== $this->categoryOf($from, $to);
    }

    public function convert(float $value, string $from, string $to): float
    {
        $category = $this->categoryOf($from, $to)
            ?? throw new UnsupportedConversionException(sprintf('Conversion impossible entre « %s » et « %s ».', $from, $to));

        return $value * $this->factors[$category][$from] / $this->factors[$category][$to];
    }

    private function categoryOf(string $from, string $to): ?string
    {
        foreach ($this->factors as $category => $units) {
            if (isset($units[$from], $units[$to])) {
                return (string) $category;
            }
        }

        return null;
    }
}
