<?php

namespace App\Services;

class MarketActivityLiquidity
{
    /** @return array{minimum_size: float, minimum_size_ratio: float} */
    public static function limits(): array
    {
        return [
            'minimum_size' => (float) config('services.market_activity_liquidity.minimum_size', 25),
            'minimum_size_ratio' => (float) config('services.market_activity_liquidity.minimum_size_ratio', 5),
        ];
    }

    /** @param array<string, mixed> $event */
    public static function visible(array $event, ?float $now = null): bool
    {
        $now ??= microtime(true);
        if (($event['stale'] ?? true) || ($event['removed'] ?? false) || ($event['streamRemoved'] ?? false)
            || ($event['active'] ?? true) === false || ($event['liquidityValid'] ?? true) === false
            || ! is_numeric($event['freshUntil'] ?? null) || (float) $event['freshUntil'] <= $now) {
            return false;
        }
        $runners = $event['runners'] ?? [];
        if ($runners === []) {
            return false;
        }
        $favorite = null;
        foreach ($runners as $runner) {
            foreach (['back', 'lay', 'backSize', 'laySize'] as $field) {
                if (! is_numeric($runner[$field] ?? null) || ! is_finite((float) $runner[$field])) {
                    return false;
                }
            }
            if ($runner['back'] <= 1 || $runner['lay'] <= 1 || $runner['lay'] < $runner['back']
                || $runner['backSize'] <= 0 || $runner['laySize'] <= 0) {
                return false;
            }
            if ($favorite === null || $runner['back'] < $favorite['back']) {
                $favorite = $runner;
            }
        }
        $limits = self::limits();
        $minimum = min($favorite['backSize'], $favorite['laySize']);

        return $minimum >= $limits['minimum_size']
            && max($favorite['backSize'], $favorite['laySize']) / $minimum > $limits['minimum_size_ratio'];
    }
}
