<?php

namespace App\Services;

class PinnacleEdge
{
    public static function calculate(mixed $betfairPrice, mixed $fairPrice): ?float
    {
        if (! self::validPrice($betfairPrice) || ! self::validPrice($fairPrice)) {
            return null;
        }

        $edge = ((float) $betfairPrice / (float) $fairPrice - 1) * 100;

        return is_finite($edge) ? $edge : null;
    }

    /** @param array<string, mixed> $drop */
    public static function fairPrice(array $drop): ?float
    {
        if (self::validPrice($drop['nvp'] ?? null)) {
            return (float) $drop['nvp'];
        }
        $outcome = strtolower(trim((string) ($drop['outcome'] ?? '')));
        foreach ($drop['full_market'] ?? [] as $selection) {
            if ($outcome !== '' && strtolower(trim((string) ($selection['outcome'] ?? ''))) === $outcome && self::validPrice($selection['nvp'] ?? null)) {
                return (float) $selection['nvp'];
            }
        }

        return null;
    }

    public static function format(?float $edge): string
    {
        if ($edge === null || ! is_finite($edge)) {
            return 'N/A';
        }
        $rounded = round($edge, 1);
        if ($rounded == 0.0) {
            $rounded = 0.0;
        }

        return ($rounded > 0 ? '+' : '').number_format($rounded, 1, '.', '').'%';
    }

    private static function validPrice(mixed $price): bool
    {
        return is_numeric($price) && is_finite((float) $price) && (float) $price > 1;
    }
}
