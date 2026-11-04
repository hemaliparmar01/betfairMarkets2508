<?php

use App\Http\Controllers\Users\Football\FootballController;
use App\Http\Controllers\Users\Football\FootballSyncController;
use App\Services\MatchBackup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

if (! function_exists('hasMeaningfulOpeningOdds')) {
    /** @param array<string, mixed> $runner */
    function hasMeaningfulOpeningOdds(array $runner): bool
    {
        return ($runner['monitoring_baseline_version'] ?? null) === 1
            && in_array($runner['opening_odds_source'] ?? null, ['available_liquidity', 'first_observed'], true)
            && is_numeric($runner['initialBackOdds'] ?? null)
            && is_finite((float) $runner['initialBackOdds'])
            && (float) $runner['initialBackOdds'] > 1
            && (int) ($runner['initialBackOddsTimestamp'] ?? 0) > 0;
    }
}

if (! function_exists('prepareOddsMonitoring')) {
    /**
     * Legacy price history has no liquidity evidence and cannot establish an opening.
     *
     * @param  array<string, mixed>  $runner
     * @return array<string, mixed>
     */
    function prepareOddsMonitoring(array $runner): array
    {
        if (hasMeaningfulOpeningOdds($runner)) {
            return $runner;
        }

        return array_replace($runner, [
            'initialBackOdds' => null, 'initialBackOddsTimestamp' => null,
            'opening_odds_source' => null, 'opening_odds_evidence' => null,
            'odds_direction' => 'same', 'backOddsHistory' => [],
            'odds_history_version' => 1, 'odds_history_incomplete' => false,
            'odds_history_processed_at' => 0,
            'movementHistory' => [], 'recentOddsHistory' => [],
            'prematch_drop_baseline_odds' => null, 'prematch_drop_triggered' => false,
            'prematch_drop_detected_at' => null, 'market_shock_detected_at' => null,
            'market_shock_snapshots' => [], 'pressure_type' => 'neutral', 'pressure_amount' => 0,
        ]);
    }
}

if (! function_exists('meaningfulOpeningOdds')) {
    /**
     * Available sizes are quote evidence, not traded volume. A funded competitive
     * Back/Lay book permits genuine low prices too. Without size data, use only
     * the first observed valid quote, never an invented historical opening.
     *
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $previous
     * @return array{initialBackOdds: ?float, initialBackOddsTimestamp: ?int, opening_odds_source: ?string, opening_odds_evidence: ?array<string, float>}
     */
    function meaningfulOpeningOdds(array $quote, array $previous, float $now, bool $canStart = true): array
    {
        if (hasMeaningfulOpeningOdds($previous)) {
            return array_intersect_key($previous, array_flip(['initialBackOdds', 'initialBackOddsTimestamp', 'opening_odds_source', 'opening_odds_evidence']));
        }
        $opening = ['initialBackOdds' => null, 'initialBackOddsTimestamp' => null, 'opening_odds_source' => null, 'opening_odds_evidence' => null];
        $price = $quote['backOdds'] ?? null;
        if (! $canStart || ! is_numeric($price) || ! is_finite((float) $price) || (float) $price <= 1) {
            return $opening;
        }
        $hasLiquidity = array_key_exists('backSize', $quote) || array_key_exists('laySize', $quote);
        if ($hasLiquidity) {
            $lay = $quote['layOdds'] ?? null;
            $backSize = $quote['backSize'] ?? null;
            $laySize = $quote['laySize'] ?? null;
            if (! is_numeric($lay) || ! is_finite((float) $lay) || (float) $lay < (float) $price
                || ! is_numeric($backSize) || ! is_finite((float) $backSize) || (float) $backSize <= 0
                || ! is_numeric($laySize) || ! is_finite((float) $laySize) || (float) $laySize <= 0
                || ((float) $lay - (float) $price) / (float) $price > 0.20 + 1e-9) {
                return $opening;
            }
            $opening['opening_odds_evidence'] = ['back_size' => (float) $backSize, 'lay_odds' => (float) $lay, 'lay_size' => (float) $laySize];
        }
        $opening['initialBackOdds'] = (float) $price;
        $opening['initialBackOddsTimestamp'] = (int) $now;
        $opening['opening_odds_source'] = $hasLiquidity ? 'available_liquidity' : 'first_observed';

        return $opening;
    }
}

if (! function_exists('updateOddsMonitoring')) {
    /**
     * Keep the opening and the chart anchor together for this market/selection.
     *
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $previous
     * @return array<string, mixed>
     */
    function updateOddsMonitoring(array $quote, array $previous, float $now, bool $canStart = true): array
    {
        foreach (['marketId', 'marketType', 'runnerId', 'handicap'] as $identity) {
            if (isset($quote[$identity], $previous[$identity]) && (string) $quote[$identity] !== (string) $previous[$identity]) {
                $previous = [];
                break;
            }
        }
        $updates = $quote['_odds_updates'] ?? [];
        unset($quote['_odds_updates']);
        $beforeBatch = $previous;
        if ($updates !== []) {
            $processedAt = (float) ($previous['odds_history_processed_at'] ?? 0);
            $updates = array_filter($updates, static fn (array $update): bool => (float) ($update['observed_at'] ?? $now) > $processedAt);
            if ($updates === []) {
                return $previous;
            }
            $lastUpdate = array_key_last($updates);
            foreach ($updates as $index => $update) {
                $observedAt = (float) ($update['observed_at'] ?? $now);
                unset($update['observed_at']);
                if ($index === $lastUpdate) {
                    $now = $observedAt;
                } else {
                    $previous = updateOddsMonitoring(array_replace($quote, $update), $previous, $observedAt, $canStart);
                }
            }
        }
        if ($now < (float) ($previous['liquidity_updated_at'] ?? 0)) {
            return $previous;
        }
        $hasPreviousOpening = hasMeaningfulOpeningOdds($previous);
        $previous = prepareOddsMonitoring($previous);
        $opening = meaningfulOpeningOdds($quote, $previous, $now, $canStart);
        $runner = array_replace(prepareOddsMonitoring(array_replace($previous, $quote)), $opening);
        $price = $runner['backOdds'] ?? null;
        $current = is_numeric($price) && is_finite((float) $price) && (float) $price > 1 ? (float) $price : 0.0;
        $runner['monitoring_baseline_version'] = 1;
        $runner['monitoring_started_at'] = $previous['monitoring_started_at'] ?? (int) $now;
        $previousPrice = (float) ($previous['currentBackOdds'] ?? 0);
        $previousHistory = $previous['backOddsHistory'] ?? [];
        $runner['previousBackOdds'] = ! $hasPreviousOpening ? $current
            : ($previousPrice > 1 ? $previousPrice : (float) ($previousHistory[array_key_last($previousHistory)]['odds'] ?? $current));
        $runner['currentBackOdds'] = $current;
        $initial = (float) ($opening['initialBackOdds'] ?? 0);
        $runner['odds_direction'] = $initial > 1 && $current > 1 ? ($current < $initial ? 'down' : ($current > $initial ? 'up' : 'same')) : 'same';
        $history = $initial > 1 ? ($previous['backOddsHistory'] ?? []) : [];
        $legacyHistory = ($previous['odds_history_version'] ?? null) !== 1;
        if ($legacyHistory && $history !== []) {
            $history = array_values(array_filter($history, static fn (array $point): bool => is_numeric($point['odds'] ?? null)
                && is_finite((float) $point['odds']) && (float) $point['odds'] > 1
                && (int) ($point['timestamp'] ?? 0) >= $opening['initialBackOddsTimestamp']));
        }
        if ($initial > 1 && ($history === [] || (float) $history[0]['odds'] !== $initial || (int) $history[0]['timestamp'] !== $opening['initialBackOddsTimestamp'])) {
            array_unshift($history, ['timestamp' => $opening['initialBackOddsTimestamp'], 'odds' => $initial]);
        }
        if ($initial > 1 && $current > 1 && (float) $history[array_key_last($history)]['odds'] !== $current) {
            $history[] = ['timestamp' => (int) $now, 'odds' => $current];
        }
        $runner['backOddsHistory'] = $history;
        $runner['odds_history_version'] = 1;
        $runner['odds_history_processed_at'] = max($now, (float) ($previous['odds_history_processed_at'] ?? 0));
        $runner['odds_history_incomplete'] = ($previous['odds_history_incomplete'] ?? false)
            || ($legacyHistory && count($previous['backOddsHistory'] ?? []) === 12);
        if (count($updates) > 1) {
            $previousBatchHistory = $beforeBatch['backOddsHistory'] ?? [];
            $previousBatchPrice = (float) ($beforeBatch['currentBackOdds'] ?? 0);
            $runner['previousBackOdds'] = hasMeaningfulOpeningOdds($beforeBatch)
                ? ($previousBatchPrice > 1 ? $previousBatchPrice : (float) ($previousBatchHistory[array_key_last($previousBatchHistory)]['odds'] ?? $current))
                : ($initial > 1 ? $initial : $current);
        }

        if (array_key_exists('backSize', $quote) || array_key_exists('laySize', $quote)) {
            if (! array_key_exists('liquidity_session', $quote)) {
                unset($runner['liquidity_session'], $runner['liquidity_feed']);
            }
            $backSize = $runner['backSize'] ?? null;
            $laySize = $runner['laySize'] ?? null;
            $validBook = is_numeric($backSize) && is_finite((float) $backSize) && (float) $backSize >= 0
                && is_numeric($laySize) && is_finite((float) $laySize) && (float) $laySize >= 0
                && ((float) $backSize === 0.0 || $current > 1)
                && ((float) $laySize === 0.0 || (is_numeric($runner['layOdds'] ?? null) && is_finite((float) $runner['layOdds']) && (float) $runner['layOdds'] > 1));
            $runner['liquidity_valid'] = $validBook;
            $runner['liquidity_updated_at'] = $now;
            $liquidityHistory = $previous['liquidity_history'] ?? [];
            if (! $validBook || ($runner['liquidity_book'] ?? null) !== ($previous['liquidity_book'] ?? null)
                || ($runner['market_status'] ?? 'OPEN') !== ($previous['market_status'] ?? 'OPEN')
                || in_array($runner['market_status'] ?? 'OPEN', ['SUSPENDED', 'CLOSED'], true)
                || ((empty($runner['liquidity_session']) || ($runner['liquidity_session'] ?? null) !== ($previous['liquidity_session'] ?? null))
                    && $now - (float) ($previous['liquidity_updated_at'] ?? 0) > 75)) {
                $liquidityHistory = [];
            }
            if ($validBook) {
                $anchor = null;
                $recent = [];
                foreach ($liquidityHistory as $point) {
                    if ($point['timestamp'] < $now - 900) {
                        $anchor = $point;
                    } else {
                        $recent[] = $point;
                    }
                }
                $liquidityHistory = $anchor === null ? $recent : [$anchor, ...$recent];
                $point = ['timestamp' => $now, 'back' => (float) $laySize, 'lay' => (float) $backSize];
                $last = array_key_last($liquidityHistory);
                if ($last !== null && ($liquidityHistory[$last]['timestamp'] === $now
                    || ($last > 0 && (int) ($liquidityHistory[$last]['timestamp'] / 10) === (int) ($now / 10)))) {
                    $liquidityHistory[$last] = $point;
                } elseif ($last === null || $liquidityHistory[$last]['timestamp'] < $now) {
                    $liquidityHistory[] = $point;
                }
            }
            $runner['liquidity_history'] = array_slice($liquidityHistory, -93);
        }

        return $runner;
    }
}

if (! function_exists('oddsMovementHistory')) {
    /** @return list<array{timestamp: int, odds: float}> */
    function oddsMovementHistory(array $runner): array
    {
        return hasMeaningfulOpeningOdds($runner) && is_array($runner['backOddsHistory'] ?? null)
            ? $runner['backOddsHistory'] : [];
    }
}

if (! function_exists('oddsMovementSequence')) {
    /** @param list<array{timestamp?: int, odds?: float}> $history */
    function oddsMovementSequence(array $history): string
    {
        $prices = [];
        $previous = null;
        foreach ($history as $point) {
            $price = $point['odds'] ?? null;
            if (! is_numeric($price) || ! is_finite((float) $price) || (float) $price <= 1 || (float) $price === $previous) {
                continue;
            }
            $previous = (float) $price;
            $formatted = rtrim(number_format($previous, 6, '.', ''), '0');
            $prices[] = str_pad($formatted, strpos($formatted, '.') + 3, '0');
        }

        return implode(' → ', $prices);
    }
}

if (! function_exists('prepareMatchOddsMonitoring')) {
    /**
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>
     */
    function prepareMatchOddsMonitoring(array $match, bool $withMarketPressure = false): array
    {
        $now = microtime(true);
        $feedHealth = [];
        foreach ($match['markets'] ?? [] as $key => $runners) {
            $match['markets'][$key] = array_map('prepareOddsMonitoring', $runners);
            if (! $withMarketPressure) {
                if (! array_any($match['markets'][$key], 'hasMeaningfulOpeningOdds')) {
                    unset($match['market_indicators'][$key]);
                }

                continue;
            }
            $back = 0.0;
            $lay = 0.0;
            $lastUpdate = null;
            $freshUntil = null;
            $favorite = null;
            $valid = $runners !== [];
            $stale = false;
            $historical = [60 => ['back' => 0.0, 'lay' => 0.0, 'valid' => true], 300 => ['back' => 0.0, 'lay' => 0.0, 'valid' => true], 900 => ['back' => 0.0, 'lay' => 0.0, 'valid' => true]];
            foreach ($runners as $runner) {
                $price = (float) ($runner['currentBackOdds'] ?? $runner['backOdds'] ?? 0);
                if ($price > 1 && ($favorite === null || $price < (float) ($favorite['currentBackOdds'] ?? $favorite['backOdds'] ?? 0))) {
                    $favorite = $runner;
                }
                $backSize = $runner['backSize'] ?? null;
                $laySize = $runner['laySize'] ?? null;
                $valid = $valid && is_numeric($backSize) && is_finite((float) $backSize) && (float) $backSize >= 0
                    && is_numeric($laySize) && is_finite((float) $laySize) && (float) $laySize >= 0
                    && ($runner['liquidity_valid'] ?? true);
                $back += max(0, (float) $laySize);
                $lay += max(0, (float) $backSize);
                $updatedAt = (float) ($runner['liquidity_updated_at'] ?? 0);
                $expiresAt = $updatedAt + 75;
                if (! empty($runner['liquidity_session'])) {
                    $feed = $runner['liquidity_feed'] ?? (($match['result_status'] ?? '') === 'Live' ? 'live' : 'prematch');
                    $health = $feedHealth[$feed] ??= racingFeedHealth($feed);
                    $stale = $stale || $updatedAt <= 0 || $health['state'] !== 'live' || (float) $runner['liquidity_session'] !== (float) ($health['session'] ?? 0);
                    $expiresAt = (float) ($health['checked_at'] ?? 0) + 75;
                } else {
                    $stale = $stale || $updatedAt <= 0 || $now - $updatedAt > 75;
                }
                $lastUpdate = $lastUpdate === null ? $updatedAt : max($lastUpdate, $updatedAt);
                $freshUntil = $freshUntil === null ? $expiresAt : min($freshUntil, $expiresAt);
                $references = [];
                foreach ($runner['liquidity_history'] ?? [] as $point) {
                    foreach ($historical as $window => $unused) {
                        if ($point['timestamp'] <= $now - $window) {
                            $references[$window] = $point;
                        }
                    }
                }
                foreach ($historical as $window => &$totals) {
                    $reference = $references[$window] ?? null;
                    if ($reference === null || (empty($runner['liquidity_session']) && $now - $window - $reference['timestamp'] > 75)) {
                        $totals['valid'] = false;
                    } else {
                        $totals['back'] += $reference['back'];
                        $totals['lay'] += $reference['lay'];
                    }
                }
                unset($totals);
            }
            $total = $back + $lay;
            $backPercent = $valid && $total > 0 ? $back / $total * 100 : null;
            $deltas = [];
            foreach ($historical as $window => $totals) {
                $previousTotal = $totals['back'] + $totals['lay'];
                $deltas[$window] = ! $stale && $backPercent !== null && $totals['valid'] && $previousTotal > 0
                    ? $backPercent - $totals['back'] / $previousTotal * 100 : null;
            }
            $momentum = $deltas[60];
            $active = ! $stale && ! in_array($match['market_status'] ?? 'OPEN', ['SUSPENDED', 'CLOSED'], true)
                && ($match['result_status'] ?? '') !== 'Finished'
                && ! array_any($runners, static fn (array $runner): bool => in_array($runner['market_status'] ?? 'OPEN', ['SUSPENDED', 'CLOSED'], true));
            $racing = in_array($runners[array_key_first($runners)]['marketType'] ?? '', ['WIN', 'PLACE', 'EACH_WAY', 'MATCH_BET', 'MONEY_BACK_2ND', 'EXACTA', 'QUINELLA'], true);
            $initial = (float) ($favorite['initialBackOdds'] ?? 0);
            $price = (float) ($favorite['currentBackOdds'] ?? $favorite['backOdds'] ?? 0);
            $movement = $initial > 1 && $price > 1 ? abs(($initial - $price) / $initial * 100) : null;
            $match['market_indicators'][$key] = array_replace($match['market_indicators'][$key] ?? [], [
                'back_total' => $back, 'lay_total' => $lay, 'liquidity' => $total,
                'back_percent' => $backPercent, 'lay_percent' => $backPercent === null ? null : 100 - $backPercent,
                'balance' => ! $active || $backPercent === null ? 'unknown' : ($racing ? ($backPercent > 58 ? 'back' : ($backPercent < 42 ? 'lay' : 'balanced')) : ($back > $lay * 1.05 ? 'back' : ($lay > $back * 1.05 ? 'lay' : 'balanced'))),
                'momentum' => ! $active || $momentum === null ? 'unknown' : ($momentum > 10 ? 'strong_back' : ($momentum > 3 ? 'back' : ($momentum < -10 ? 'strong_lay' : ($momentum < -3 ? 'lay' : 'neutral')))),
                'delta' => $active ? $deltas[300] : null, 'delta_windows' => $active ? $deltas : [60 => null, 300 => null, 900 => null],
                'updated_at' => $lastUpdate ?: null, 'fresh_until' => $freshUntil, 'stale' => $stale, 'active' => $active,
                'volatility' => $match['market_indicators'][$key]['volatility'] ?? ($movement === null ? 'unknown' : ($movement >= 10 ? 'high' : ($movement >= 3 ? 'medium' : 'low'))),
            ]);
        }

        return $match;
    }
}

if (! function_exists('racingFeedHealth')) {
    /** @return array{state: string, last_frame_at: ?int, checked_at: ?int, session: ?float} */
    function racingFeedHealth(string $sport): array
    {
        $feed = in_array($sport, ['live', 'prematch', 'greyhounds', 'horse_prematch'], true) ? $sport : 'horse_prematch';
        $connection = Cache::store('redis')->get('Betfair:racing:connection', []);
        $health = Cache::store('redis')->get('Betfair:racing:feed:'.$feed, []);
        $state = ($connection['state'] ?? '') === 'connected' && time() - ($connection['at'] ?? 0) <= 75
            ? ($health['state'] ?? 'waiting') : 'disconnected';

        return ['state' => $state, 'last_frame_at' => $health['at'] ?? null, 'checked_at' => $connection['at'] ?? null, 'session' => $connection['session'] ?? null];
    }
}

if (! function_exists('GetBetfairKey')) {
    function GetBetfairKey()
    {
        return env('Betfair_API_KEY');
    }
}

if (! function_exists('GetSportApiProKey')) {
    function GetSportApiProKey()
    {
        return env('SPORTS_API_PRO_KEY');
    }
}

if (! function_exists('recordSportsApiProRestRequest')) {
    function recordSportsApiProRestRequest(string $endpoint, bool $lowPriority = false): int
    {
        $date = gmdate('Y-m-d');
        $requestCount = (int) Redis::eval(<<<'LUA'
local total = tonumber(redis.call('GET', KEYS[1]) or '0')
local remaining = tonumber(redis.call('GET', KEYS[2]) or '-1')
local limit = math.min(tonumber(redis.call('GET', KEYS[3]) or ARGV[2]), tonumber(ARGV[2]))
if ARGV[1] == '1' and (total >= limit - tonumber(ARGV[3])
    or (remaining >= 0 and remaining <= tonumber(ARGV[3]))
    or tonumber(redis.call('GET', KEYS[4]) or '0') >= tonumber(ARGV[4])
    or tonumber(redis.call('GET', KEYS[5]) or '0') > tonumber(ARGV[6])) then
    return 0
end
total = redis.call('INCR', KEYS[1])
redis.call('EXPIREAT', KEYS[1], ARGV[5])
if remaining > 0 then redis.call('DECR', KEYS[2]) end
if ARGV[1] == '1' then
    redis.call('INCR', KEYS[4])
    redis.call('EXPIREAT', KEYS[4], ARGV[5])
end
return total
LUA, 5, 'SportsAPIPro:rest-requests:'.$date, 'SportsAPIPro:rest-remaining:'.$date,
            'SportsAPIPro:rest-limit:'.$date, 'SportsAPIPro:football-v6-requests:'.$date,
            'SportsAPIPro:rest-backoff:'.$date, $lowPriority ? '1' : '0',
            max(1, (int) config('services.sports_api_pro.daily_rest_limit', 75000)),
            max(0, (int) config('services.sports_api_pro.rest_reserve', 7500)),
            max(0, (int) config('services.sports_api_pro.football_v6_daily_budget', 30000)),
            strtotime('tomorrow 00:05 UTC'), time());

        if ($requestCount === 0) {
            if (Cache::store('redis')->add('SportsAPIPro:v6-budget-notice:'.$date, true, 3600)) {
                Log::notice('Football V6 polling paused for API quota reserve or backoff; cached statistics retained');
            }

            return 0;
        }

        if (in_array($requestCount, [60000, 67500, 71250], true)) {
            Log::warning('SportsAPI Pro daily REST request usage is approaching the plan limit', [
                'requests' => $requestCount,
                'daily_limit' => 75000,
                'endpoint' => $endpoint,
            ]);
        }

        return $requestCount;
    }
}

if (! function_exists('recordSportsApiProRestResponse')) {
    /** @param array<string, string> $headers */
    function recordSportsApiProRestResponse(array $headers, int $requestNumber, int $status): void
    {
        $date = gmdate('Y-m-d');
        $expiresAt = strtotime('tomorrow 00:05 UTC');
        if (is_numeric($headers['x-ratelimit-limit'] ?? null)) {
            Redis::setex('SportsAPIPro:rest-limit:'.$date, $expiresAt - time(), (int) $headers['x-ratelimit-limit']);
        }
        if (is_numeric($headers['x-ratelimit-remaining'] ?? null)) {
            Redis::eval(<<<'LUA'
local total = tonumber(redis.call('GET', KEYS[1]) or ARGV[2])
local remaining = math.max(0, tonumber(ARGV[1]) - math.max(0, total - tonumber(ARGV[2])))
local previous = tonumber(redis.call('GET', KEYS[2]) or '-1')
if previous >= 0 then remaining = math.min(previous, remaining) end
redis.call('SET', KEYS[2], remaining)
redis.call('EXPIREAT', KEYS[2], ARGV[3])
LUA, 2, 'SportsAPIPro:rest-requests:'.$date, 'SportsAPIPro:rest-remaining:'.$date,
                (int) $headers['x-ratelimit-remaining'], $requestNumber, $expiresAt);
        }
        if ($status === 429) {
            $retryAt = is_numeric($headers['retry-after'] ?? null) ? time() + (int) $headers['retry-after']
                : (strtotime($headers['retry-after'] ?? '') ?: time() + 300);
            if (isset($headers['x-ratelimit-remaining']) && (int) $headers['x-ratelimit-remaining'] === 0) {
                $retryAt = strtotime($headers['x-ratelimit-reset'] ?? '') ?: strtotime('tomorrow 00:00 UTC');
            }
            Redis::setex('SportsAPIPro:rest-backoff:'.$date, $expiresAt - time(), max(time() + 60, $retryAt));
        }
    }
}

if (! function_exists('apiResponseLogContext')) {
    /**
     * @param  array<string, mixed>  $response
     * @param  list<string>  $secrets
     * @return array<string, mixed>
     */
    function apiResponseLogContext(array $response, array $secrets = []): array
    {
        if (isset($response['url'])) {
            $response['url'] = preg_replace_callback('/([?&](?:x[-_]?api[-_]?key|x[-_]?portal[-_]?api[-_]?key|api[-_]?key|key|token|access_token|auth_key|auth_signature|signature|password|secret)=)([^&#]*)/i', static function (array $match) use (&$secrets): string {
                $secrets[] = urldecode($match[2]);

                return $match[1].'[REDACTED]';
            }, $response['url']);
        }
        $secrets = array_values(array_filter(array_unique($secrets), static fn (string $secret): bool => $secret !== ''));
        foreach ($secrets as $secret) {
            $encoded = json_encode($secret);
            $variants = [$secret, rawurlencode($secret), urlencode($secret)];
            if (is_string($encoded)) {
                $variants[] = substr($encoded, 1, -1);
            }
            foreach ($response as $field => $value) {
                if (is_string($value)) {
                    $response[$field] = str_replace($variants, '[REDACTED]', $value);
                }
            }
        }

        return $response;
    }
}

if (! function_exists('ApiCall')) {
    /**
     * @param  string|array<string, string>|list<string>  $key
     * @param  array{query?: array<string, mixed>, connect_timeout?: int, timeout?: int, raw_response?: bool, follow_redirects?: bool, low_priority?: bool}  $options
     * @param  array<string, mixed>|null  $diagnostics
     */
    function ApiCall(string $url, string|array $key, array $options = [], ?array &$diagnostics = null): mixed
    {
        $captureDiagnostics = func_num_args() >= 4;
        $diagnostics = null;
        $query = http_build_query($options['query'] ?? [], '', '&', PHP_QUERY_RFC3986);
        if ($query !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?').$query;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! app()->runningInConsole() && preg_match('/(^|\.)(sportsapipro\.com|betfair-odds\.com)$/', $host)) {
            $diagnostics = apiResponseLogContext(['url' => $url, 'http_status' => null, 'skipped' => 'browser_request']);

            return null;
        }

        $isSportsApiPro = parse_url($url, PHP_URL_HOST) === 'api.sportsapipro.com';
        $requestNumber = 0;
        if ($isSportsApiPro) {
            $requestNumber = recordSportsApiProRestRequest((string) parse_url($url, PHP_URL_PATH), $options['low_priority'] ?? false);
            if ($requestNumber === 0) {
                $diagnostics = apiResponseLogContext(['url' => $url, 'http_status' => null, 'skipped' => 'quota_reserve']);

                return null;
            }
        }

        $headers = [];
        $secrets = [];
        foreach ((array) $key as $name => $value) {
            $header = is_string($name) ? $name.': '.$value : $value;
            $headers[] = $header;
            if (preg_match('/^[^:]*?(?:authorization|key|token|secret)[^:]*:\s*(.+)$/i', $header, $matches)) {
                $secrets[] = $matches[1];
                $secrets[] = preg_replace('/^Bearer\s+/i', '', $matches[1]);
            }
        }

        $responseHeaders = [];
        $ch = curl_init($url);
        try {
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $options['connect_timeout'] ?? 0,
                CURLOPT_TIMEOUT => $options['timeout'] ?? 0,
                CURLOPT_FOLLOWLOCATION => $options['follow_redirects'] ?? false,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_HEADERFUNCTION => static function (CurlHandle $handle, string $line) use (&$responseHeaders): int {
                    if (str_starts_with($line, 'HTTP/')) {
                        $responseHeaders = [];
                    } elseif (str_contains($line, ':')) {
                        [$name, $value] = explode(':', $line, 2);
                        $responseHeaders[strtolower(trim($name))] = trim($value);
                    }

                    return strlen($line);
                },
            ]);
        } catch (Throwable $exception) {
            curl_close($ch);
            throw $exception;
        }
        $complete = static function (string|false $body, ?int $multiError = null) use ($ch, $url, $options, $isSportsApiPro, $requestNumber, &$responseHeaders, $captureDiagnostics, &$diagnostics, $secrets): mixed {
            try {
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                $curlErrorCode = $multiError ?? curl_errno($ch);
                $curlError = curl_error($ch);
                if ($curlError === '' && $curlErrorCode !== 0) {
                    $curlError = curl_strerror($curlErrorCode);
                }
                if ($isSportsApiPro) {
                    recordSportsApiProRestResponse($responseHeaders, $requestNumber, $status);
                }
                $rawResponse = $options['raw_response'] ?? false;
                $data = null;
                $jsonError = null;
                if ($body !== false && (! $rawResponse || str_contains(strtolower($contentType), 'json'))) {
                    $data = json_decode($body, true);
                    $jsonError = json_last_error() === JSON_ERROR_NONE ? null : json_last_error_msg();
                }
                $failed = $body === false || $status < 200 || $status >= 300 || $jsonError !== null
                    || (is_array($data) && ($data['success'] ?? true) === false);
                if ($captureDiagnostics || $failed) {
                    $diagnostics = apiResponseLogContext([
                        'url' => $url,
                        'http_status' => $status,
                        'content_type' => $contentType,
                        'curl_errno' => $curlErrorCode,
                        'curl_error' => $curlError !== '' ? $curlError : null,
                        'json_error' => $jsonError,
                        'response_body' => $body === false ? null : $body,
                    ], $secrets);
                    if ($failed) {
                        Log::warning('API request failed', $diagnostics);
                    }
                }

                if ($rawResponse) {
                    return [
                        'body' => $body === false ? '' : $body,
                        'status' => $status,
                        'content_type' => $contentType,
                        'successful' => $body !== false && $status >= 200 && $status < 300,
                    ];
                }

                return $data;
            } finally {
                curl_close($ch);
            }
        };
        if ($options['_pool'] ?? false) {
            return ['handle' => $ch, 'complete' => $complete];
        }

        return $complete(curl_exec($ch));
    }
}

if (! function_exists('ApiCallPool')) {
    /**
     * @param  array<string, array{url: string, key: string|array, options?: array}>  $requests
     * @return array<string, array{response: mixed, diagnostics: ?array}>
     */
    function ApiCallPool(array $requests, int $concurrency = 2): array
    {
        $multi = curl_multi_init();
        $pending = array_keys($requests);
        $active = [];
        $results = [];
        $diagnostics = [];
        $offset = 0;
        $concurrency = max(1, min(8, $concurrency));
        try {
            do {
                while (count($active) < $concurrency && isset($pending[$offset])) {
                    $key = $pending[$offset++];
                    $request = $requests[$key];
                    try {
                        $registration = ApiCall($request['url'], $request['key'], array_replace($request['options'] ?? [], ['_pool' => true]), $diagnostics[$key]);
                    } catch (Throwable $exception) {
                        $results[$key] = ['response' => null, 'diagnostics' => $diagnostics[$key] ?? null, 'exception' => $exception];

                        continue;
                    }
                    if (! is_array($registration) || ! isset($registration['handle'], $registration['complete'])) {
                        $results[$key] = ['response' => $registration, 'diagnostics' => $diagnostics[$key]];

                        continue;
                    }
                    $active[spl_object_id($registration['handle'])] = $registration + ['key' => $key];
                    curl_multi_add_handle($multi, $registration['handle']);
                }
                do {
                    $status = curl_multi_exec($multi, $running);
                } while ($status === CURLM_CALL_MULTI_PERFORM);
                if ($status !== CURLM_OK) {
                    throw new RuntimeException('Concurrent API transport failed: '.curl_multi_strerror($status));
                }
                while ($info = curl_multi_info_read($multi)) {
                    $id = spl_object_id($info['handle']);
                    $registration = $active[$id];
                    $key = $registration['key'];
                    $body = $info['result'] === CURLE_OK ? curl_multi_getcontent($info['handle']) : false;
                    curl_multi_remove_handle($multi, $info['handle']);
                    unset($active[$id]);
                    $response = $registration['complete']($body, $info['result']);
                    $results[$key] = ['response' => $response, 'diagnostics' => $diagnostics[$key]];
                }
                if ($active !== [] && curl_multi_select($multi, 0.1) === -1) {
                    usleep(1000);
                }
            } while ($active !== [] || isset($pending[$offset]));
        } finally {
            foreach ($active as $registration) {
                curl_multi_remove_handle($multi, $registration['handle']);
                curl_close($registration['handle']);
            }
            curl_multi_close($multi);
        }

        return array_replace(array_fill_keys(array_keys($requests), null), $results);
    }
}

if (! function_exists('queueSportsSynchronization')) {
    function queueSportsSynchronization(string $controller, string $method): void
    {
        $key = 'Sports:scheduled:'.hash('sha256', $controller.'::'.$method);
        $pending = Cache::store('redis')->lock($key, 3600);
        if (! $pending->get()) {
            return;
        }
        $owner = $pending->owner();
        try {
            dispatch(static function () use ($controller, $method, $key, $owner): void {
                try {
                    app($controller)->{$method}();
                } finally {
                    Cache::store('redis')->restoreLock($key, $owner)->release();
                }
            })->name($controller.'::'.$method)->onConnection('sports-realtime')
                ->onQueue(str_contains($method, 'Live') ? 'sports-sync-live' : 'sports-sync-prematch')
                ->catch(static function (Throwable $exception) use ($key, $owner): void {
                    Cache::store('redis')->restoreLock($key, $owner)->release();
                    Log::warning('Scheduled sports synchronization failed', ['exception' => $exception::class]);
                });
        } catch (Throwable $exception) {
            $pending->release();
            throw $exception;
        }
    }
}

if (! function_exists('getSportsApiProImage')) {
    function getSportsApiProImage(string $sport, string $type, mixed $entityId): ?string
    {
        $sports = [
            'football' => ['football', 'Football'],
            'cricket' => ['cricket', 'Cricket'],
            'icehockey' => ['hockey', 'IceHockey'],
            'americanfootball' => ['american-football', 'AmericanFootball'],
            'baseball' => ['baseball', 'Baseball'],
            'basketball' => ['basketball', 'Basketball'],
            'tennis' => ['tennis', 'Tennis'],
        ];
        if (! isset($sports[$sport]) || ! in_array($type, ['teams', 'tournaments'], true) || ! is_numeric($entityId) || (int) $entityId <= 0) {
            return null;
        }

        $entityId = (int) $entityId;
        $directory = "images/{$sport}/{$type}";
        $images = glob(public_path("{$directory}/{$entityId}.*"));
        if ($images && File::isFile($images[0])) {
            return '/'.$directory.'/'.basename($images[0]);
        }

        $lockName = "SportsAPIPro:image-pending:{$sport}:{$type}:{$entityId}";
        $missingKey = "SportsAPIPro:image-unavailable:{$sport}:{$type}:{$entityId}";
        $pendingLock = null;
        try {
            if (Cache::store('redis')->has($missingKey)) {
                return null;
            }
            $pendingLock = Cache::store('redis')->lock($lockName, 3600);
            if (! $pendingLock->get()) {
                return null;
            }
            $owner = $pendingLock->owner();
            [$apiSport, $prefix] = $sports[$sport];
            dispatch(static function () use ($sport, $type, $entityId, $directory, $lockName, $missingKey, $owner, $apiSport, $prefix): void {
                $lock = Cache::store('redis')->restoreLock($lockName, $owner);
                $apiDiagnostics = null;
                try {
                    if (! $lock->isOwnedByCurrentProcess()) {
                        return;
                    }
                    $images = glob(public_path("{$directory}/{$entityId}.*"));
                    if (! $images || ! File::isFile($images[0])) {
                        $response = ApiCall("https://api.sportsapipro.com/v2/{$apiSport}/images/{$type}/{$entityId}", [
                            'x-api-key' => GetSportApiProKey(),
                            'Accept' => 'image/png,image/jpeg,image/webp,image/gif',
                        ], ['connect_timeout' => 3, 'timeout' => 10, 'raw_response' => true, 'follow_redirects' => true], $apiDiagnostics);
                        $contentType = strtolower(trim(explode(';', $response['content_type'])[0]));
                        $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'][$contentType] ?? null;
                        if (! $response['successful'] || $extension === null || $response['body'] === '' || strlen($response['body']) > 5_000_000) {
                            Log::warning('SportsAPI Pro image response unavailable', ['sport' => $sport, 'type' => $type, 'entity_id' => $entityId, 'api' => $apiDiagnostics]);
                            Cache::store('redis')->put($missingKey, true, in_array($response['status'], [404, 410], true) ? 21600 : 300);

                            return;
                        }
                        File::ensureDirectoryExists(public_path($directory));
                        File::replace(public_path("{$directory}/{$entityId}.{$extension}"), $response['body']);
                        $images = [public_path("{$directory}/{$entityId}.{$extension}")];
                    }
                    $imagePath = '/'.$directory.'/'.basename($images[0]);
                    $cacheKey = $prefix.':matches';
                    $updated = withRedisLock($cacheKey, static function () use ($cacheKey, $type, $entityId, $imagePath, $sport): bool {
                        $matches = getInRedis($cacheKey) ?? [];
                        $updated = false;
                        $fields = $type === 'teams'
                            ? ['home_team_logo' => 'home_team_id', 'away_team_logo' => 'away_team_id']
                            : ['tournament_logo' => in_array($sport, ['football', 'cricket'], true) ? 'tournament_id' : 'tournament_image_id'];
                        foreach ($matches as &$match) {
                            foreach ($fields as $field => $idField) {
                                if ((int) ($match[$idField] ?? 0) === $entityId && ($match[$field] ?? null) !== $imagePath) {
                                    $match[$field] = $imagePath;
                                    $updated = true;
                                }
                            }
                        }
                        unset($match);
                        if ($updated) {
                            storeInRedis($cacheKey, $matches);
                        }

                        return $updated;
                    }, 2);
                    if ($updated) {
                        $eventClass = 'App\\Events\\'.$prefix.'SportsScoreUpdated';
                        Event::dispatch(new $eventClass);
                    }
                } catch (Throwable $e) {
                    Cache::store('redis')->put($missingKey, true, 300);
                    Log::warning('SportsAPI Pro background image failed', ['sport' => $sport, 'type' => $type, 'entity_id' => $entityId, 'api' => $apiDiagnostics, 'exception' => $e::class, 'message' => $e->getMessage()]);
                } finally {
                    $lock->release();
                }
            })->onQueue('sports-images')->catch(static function (Throwable $e) use ($lockName, $owner, $missingKey): void {
                Cache::store('redis')->restoreLock($lockName, $owner)->release();
                Cache::store('redis')->put($missingKey, true, 300);
            });
        } catch (Throwable $e) {
            $pendingLock?->release();
            Log::warning('SportsAPI Pro image could not be queued', ['sport' => $sport, 'type' => $type, 'entity_id' => $entityId, 'exception' => $e::class]);
        }

        return null;
    }
}

if (! function_exists('storeInRedis')) {
    /**
     * Reuse decoded rows only while their JSON still matches the current cache.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $existingData
     */
    function storeInRedis(string $cacheKey, array $data, int $ttlMinutes = 10, bool $mergeLifecycle = true, ?array $existingData = null, bool $replace = false): void
    {
        if ($replace) {
            $mergedData = $data;
        } else {
            $cachedData = Redis::get($cacheKey);
            $encodedExisting = $existingData === null ? null : json_encode($existingData);
            $reuseExisting = $encodedExisting !== null && is_string($cachedData)
                && strncmp($cachedData, $encodedExisting, strlen($encodedExisting)) === 0
                && trim(substr($cachedData, strlen($encodedExisting))) === '';
            unset($encodedExisting);
            if (! $reuseExisting) {
                $existingData = $cachedData ? json_decode($cachedData, true) : [];
            }
            unset($cachedData);
            $mergedData = array_replace($existingData, $data);
            if ($cacheKey === 'Football:matches') {
                $mergedData = app(FootballSyncController::class)->mergeDuplicateMatches($mergedData);
            }
            $sport = MatchBackup::sportForCache($cacheKey);
            if ($sport !== null && $mergeLifecycle) {
                $cleaned = array_fill_keys(Redis::zrangebyscore($cacheKey.':cleaned', time(), '+inf'), true);
                foreach ($mergedData as $matchKey => &$match) {
                    if (isset($cleaned[$matchKey])) {
                        unset($mergedData[$matchKey]);

                        continue;
                    }
                    $previous = $existingData[$matchKey] ?? [];
                    if (($previous['result_status'] ?? '') === 'Finished' && ($match['result_status'] ?? '') !== 'Finished') {
                        $match = $previous;
                    }
                    $match = MatchBackup::merge($match, $previous, $sport);
                }
                unset($match);
            }
            if ($sport !== null && $sport !== 'football') {
                $tennisCountries = null;
                foreach ($mergedData as $matchKey => &$match) {
                    $field = in_array($sport, ['greyhounds', 'horseracing'], true) ? 'country' : 'tournament_country';
                    if ($sport === 'tennis' && (! filled($match[$field] ?? null) || in_array($match[$field], ['ATP', 'WTA', 'ITF', 'Challenger'], true)) && class_exists(ResourceBundle::class)) {
                        if ($tennisCountries === null) {
                            $regions = ResourceBundle::create('en', 'ICUDATA-region')?->get('Countries');
                            $tennisCountries = $regions === null ? [] : array_change_key_case(array_flip(iterator_to_array($regions)), CASE_LOWER);
                        }
                        if ($tennisCountries !== []) {
                            foreach (array_slice(explode(',', (string) ($match['tournament'] ?? '')), 1) as $part) {
                                $name = trim($part);
                                $name = ['USA' => 'United States', 'UK' => 'United Kingdom'][$name] ?? $name;
                                $code = $tennisCountries[strtolower($name)] ?? null;
                                if ($code !== null) {
                                    $match[$field] = Locale::getDisplayRegion('und_'.$code, 'en');
                                    break;
                                }
                            }
                        }
                        if (in_array($match[$field] ?? null, ['ATP', 'WTA', 'ITF', 'Challenger'], true)) {
                            unset($match[$field]);
                        }
                    }
                    $country = array_find([$match[$field] ?? null, $match['country'] ?? null, $match['country_code'] ?? null, $match['countryCode'] ?? null, $existingData[$matchKey][$field] ?? null, $existingData[$matchKey]['country'] ?? null], static fn (mixed $value): bool => (is_array($value) || (is_string($value) && trim($value) !== '')) && ($sport !== 'tennis' || ! in_array($value, ['ATP', 'WTA', 'ITF', 'Challenger'], true)));
                    if (is_array($country)) {
                        $country = $country['name'] ?? $country['code'] ?? $country['alpha2'] ?? null;
                    }
                    if (is_string($country) && trim($country) !== '') {
                        $country = trim($country);
                        if (preg_match('/^[a-z]{2}$/i', $country) && class_exists(Locale::class)) {
                            $country = Locale::getDisplayRegion('und_'.strtoupper($country), 'en');
                        }
                        $match[$field] = $country;
                    }
                }
                unset($match);
            }
            if ($cacheKey === 'Football:matches') {
                $timelineController = app(FootballSyncController::class);
                $savedTimelines = collect();
                try {
                    $matchIds = array_values(array_filter(array_column($mergedData, 'sportsApiPro_match_id'), static fn (mixed $id): bool => is_scalar($id) && preg_match('/^[1-9][0-9]*$/', (string) $id)));
                    if ($matchIds !== []) {
                        $savedTimelines = DB::table('football_timeline')->whereIn('match_id', $matchIds)->get(['match_id', 'timeline'])->keyBy('match_id');
                    }
                } catch (Throwable $exception) {
                    Log::warning('Football timeline cache fallback unavailable', [
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ]);
                }
                foreach ($mergedData as $matchKey => &$match) {
                    $saved = $savedTimelines->get((string) ($match['sportsApiPro_match_id'] ?? ''));
                    $timelineController->storeTimeline($match, frameType: 'normalize', previous: $existingData[$matchKey] ?? [], storedTimeline: json_decode($saved->timeline ?? '[]', true) ?? []);
                }
                unset($match);
            }
            unset($existingData, $data, $previous, $cleaned, $savedTimelines, $matchIds, $saved);
        }
        if ($cacheKey === 'Football:matches' && config('services.football_fixture_index')) {
            $index = ['format' => 3, 'matches' => [], 'positions' => [], 'ids' => [], 'orders' => []];
            $fields = array_flip(['unique_key', 'sportsApiPro_match_id', 'betfair_match_id', 'timestamp', 'result_status', 'minutes_status', 'current_period_start_timestamp', 'added_time', 'tournament', 'tournament_country', 'tournament_id', 'tournament_logo', 'country_id', 'internal_backup', 'provider_lifecycle', 'pinnacle_drop_triggered', 'pinnacle_market_shock_detected_at']);
            $visibleFields = array_flip(['unique_key', 'team1', 'team2', 'home_team_id', 'away_team_id', 'home_team_logo', 'away_team_logo', 'betfair_match_id', 'sportsApiPro_match_id', 'timestamp', 'result_status', 'minutes_status', 'current_period_start_timestamp', 'added_time', 'home_score', 'away_score', 'timeline', 'live_stats', 'live_stats_meta', 'statistics_periods', 'prematch_statistics', 'statistics_requests', 'predictions']);
            $controller = app(FootballController::class);
            $index['priority_version'] = hash('xxh3', serialize((new ReflectionClass($controller))->getConstant('LEAGUE_PRIORITY')));
            $previousIndex = getInRedis($cacheKey, fixtureIndex: true) ?? [];
            $isList = array_is_list($mergedData);
            $serialized = $isList ? '[' : '{';
            $sortFields = [];
            $number = 0;
            $canonical = true;
            foreach ($mergedData as $matchKey => $match) {
                $serialized .= ($number++ > 0 ? ',' : '').($isList ? '' : json_encode((string) $matchKey).':');
                $json = json_encode($match, JSON_THROW_ON_ERROR);
                $index['positions'][$matchKey] = [strlen($serialized), strlen($json)];
                $serialized .= $json;
                $rowVersion = hash('xxh3', $json);
                if (($previousIndex['matches'][$matchKey]['_fixture_source_version']['odds'] ?? null) === $rowVersion) {
                    $entry = $previousIndex['matches'][$matchKey];
                } else {
                    $entry = array_intersect_key($match, $fields);
                    $entry['_fixture_cache_key'] = $matchKey;
                    $entry['_fixture_priority'] = $controller->footballLeaguePriority((string) ($match['tournament'] ?? ''), $match['tournament_country'] ?? null);
                    $entry['_fixture_source_version'] = ['odds' => $rowVersion, 'timeline' => hash('xxh3', json_encode(array_intersect_key($match, $visibleFields)))];
                    $entry['home_score'] = $match['home_score'] ?? null;
                    $entry['away_score'] = $match['away_score'] ?? null;
                    $entry['bet365_prematch'] = array_intersect_key($match['bet365_prematch'] ?? [], array_flip(['favorite_side', 'favorite_odds']));
                    $entry['markets'] = [];
                    if (($match['result_status'] ?? '') === 'Prematch') {
                        $entry['pinnacle_drop_triggered'] = ($controller->footballFilterPredicate('prematch_drops'))($match);
                        $entry['pinnacle_market_shock_detected_at'] = (int) ($match['pinnacle_market_shock_detected_at'] ?? 0);
                        foreach ($match['markets'] ?? [] as $runners) {
                            foreach ($runners as $runner) {
                                if ((int) ($runner['market_shock_detected_at'] ?? 0) > $entry['pinnacle_market_shock_detected_at'] && hasMeaningfulOpeningOdds($runner)) {
                                    $entry['pinnacle_market_shock_detected_at'] = (int) $runner['market_shock_detected_at'];
                                }
                            }
                        }
                    }
                }
                $index['matches'][$matchKey] = $entry;
                $id = (string) ($match['sportsApiPro_match_id'] ?? '');
                if (ctype_digit($id) && (int) $id > 0) {
                    $canonical = $canonical && ! isset($index['ids'][$id]);
                    $index['ids'][$id] = $matchKey;
                }
                $sortFields[$matchKey] = [$entry['_fixture_priority'], $entry['timestamp'] ?? 0, $entry['tournament_id'] ?? null, $entry['tournament_country'] ?? null, $entry['tournament'] ?? ''];
            }
            $serialized .= $isList ? ']' : '}';
            $index['length'] = strlen($serialized);
            $index['content_version'] = hash('xxh3', $serialized);
            if (($previousIndex['content_version'] ?? null) === $index['content_version']) {
                $extended = Redis::eval('if redis.call("GETRANGE", KEYS[1], -66, -1) == ARGV[1] then redis.call("EXPIRE", KEYS[1], ARGV[2]); redis.call("EXPIRE", KEYS[2], ARGV[2]); return 1 end; return 0', 2, $cacheKey, $cacheKey.':fixtures', $previousIndex['marker'], $ttlMinutes * 60);
                if ($extended) {
                    return;
                }
            }
            $index['sort_version'] = hash('xxh3', serialize($sortFields));
            if (($previousIndex['sort_version'] ?? null) === $index['sort_version'] && is_array($previousIndex['orders'] ?? null) && count($previousIndex['orders']) === 3) {
                $index['orders'] = $previousIndex['orders'];
            } else {
                foreach (['competition', 'default', 'time'] as $mode) {
                    $ordered = $index['matches'];
                    uasort($ordered, static function (array $first, array $second) use ($mode): int {
                        $time = (int) ($first['timestamp'] ?? 0) <=> (int) ($second['timestamp'] ?? 0);
                        $priority = $first['_fixture_priority'] <=> $second['_fixture_priority'];
                        $competition = $mode === 'competition' && $priority === 0
                            ? strcmp((string) ($first['tournament_id'] ?? ($first['tournament_country'] ?? '').'|'.($first['tournament'] ?? '')), (string) ($second['tournament_id'] ?? ($second['tournament_country'] ?? '').'|'.($second['tournament'] ?? ''))) : 0;

                        return $mode === 'time' ? ($time ?: $priority) : ($priority ?: $competition ?: $time);
                    });
                    $index['orders'][$mode] = array_keys($ordered);
                }
            }
            // JSON whitespace binds the byte index to its payload without changing decoded data.
            $index['marker'] = "\n".strtr(str_pad(decbin(hexdec(substr(hash('sha256', json_encode($index, JSON_THROW_ON_ERROR)), 0, 15))), 64, '0', STR_PAD_LEFT), '01', " \t")."\n";
            $serialized .= $index['marker'];
            Redis::eval('redis.call("SET", KEYS[1], ARGV[1], "EX", ARGV[3]); if ARGV[2] ~= "" then redis.call("SET", KEYS[2], ARGV[2], "EX", ARGV[3]) else redis.call("DEL", KEYS[2]) end; return 1', 2, $cacheKey, $cacheKey.':fixtures', $serialized, $canonical ? json_encode($index, JSON_THROW_ON_ERROR) : '', $ttlMinutes * 60);
        } else {
            if ($replace) {
                Redis::setex($cacheKey, $ttlMinutes * 60, json_encode($mergedData));
            } else {
                Redis::set($cacheKey, json_encode($mergedData));
                Redis::expire($cacheKey, $ttlMinutes * 60);
            }
        }
    }
}

if (! function_exists('getInRedis')) {
    /**
     * Read selected Football rows from the current JSON snapshot when available.
     *
     * @param  array<int, int|string>|null  $matchIds
     * @param  array<string, mixed>|null  $snapshot
     */
    function getInRedis(string $cacheKey, bool $restoreVisibleFootballTimelines = false, bool $readOnly = false, ?array $matchIds = null, bool $fixtureIndex = false, ?array $snapshot = null): mixed
    {
        if ($cacheKey === 'Football:matches' && ($fixtureIndex || $matchIds !== null)) {
            $index = $snapshot;
            if ($index === null && config('services.football_fixture_index')) {
                $index = json_decode(Redis::get($cacheKey.':fixtures') ?? '[]', true);
            }
            $metadata = is_array($index) ? $index : [];
            unset($metadata['marker']);
            $marker = "\n".strtr(str_pad(decbin(hexdec(substr(hash('sha256', json_encode($metadata)), 0, 15))), 64, '0', STR_PAD_LEFT), '01', " \t")."\n";
            $valid = is_array($index) && ($index['format'] ?? null) === 3
                && is_array($index['matches'] ?? null) && is_array($index['orders'] ?? null)
                && ($index['priority_version'] ?? null) === hash('xxh3', serialize((new ReflectionClass(FootballController::class))->getConstant('LEAGUE_PRIORITY')))
                && is_string($index['marker'] ?? null) && strlen($index['marker']) === 66
                && $index['marker'] === $marker
                && Redis::getrange($cacheKey, -66, -1) === $index['marker'];
            if ($valid) {
                foreach (['competition', 'default', 'time'] as $mode) {
                    $order = $index['orders'][$mode] ?? [];
                    if (! is_array($order) || count($order) !== count($index['matches']) || array_diff_key($index['matches'], array_flip($order)) !== []) {
                        $valid = false;
                        break;
                    }
                }
            }
            if ($fixtureIndex) {
                return $valid ? $index : null;
            }
            if (array_any($matchIds, static fn (mixed $id): bool => ! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id <= 0)) {
                $valid = false;
            }
            if ($valid) {
                $arguments = [$index['marker'], (int) $index['length'] + 66];
                $keys = [];
                foreach ($matchIds as $id) {
                    $key = $index['ids'][(string) $id] ?? null;
                    if ($key !== null && ! isset($keys[$key])) {
                        [$offset, $length] = $index['positions'][$key] ?? [-1, 0];
                        if (! is_int($offset) || ! is_int($length) || $offset < 0 || $length < 2 || $offset + $length > $index['length']) {
                            $valid = false;
                            break;
                        }
                        $keys[$key] = true;
                        $arguments[] = $offset;
                        $arguments[] = $offset + $length - 1;
                    }
                }
                $rows = $valid ? Redis::eval('if redis.call("GETRANGE", KEYS[1], -66, -1) ~= ARGV[1] or redis.call("STRLEN", KEYS[1]) ~= tonumber(ARGV[2]) then return false end; local rows = {}; for i = 3, #ARGV, 2 do rows[#rows + 1] = redis.call("GETRANGE", KEYS[1], tonumber(ARGV[i]), tonumber(ARGV[i + 1])) end; return rows', 1, $cacheKey, ...$arguments) : false;
                if (is_array($rows) && count($rows) === count($keys)) {
                    $matches = [];
                    foreach (array_keys($keys) as $position => $key) {
                        $row = json_decode($rows[$position], true);
                        if (! is_array($row) || ! isset($index['ids'][(string) ($row['sportsApiPro_match_id'] ?? '')])) {
                            $valid = false;
                            break;
                        }
                        $matches[$key] = $row;
                    }
                    if ($valid) {
                        if ($readOnly) {
                            return $matches;
                        }
                        $selectedMatches = $matches;
                    }
                }
            }
            if ($snapshot !== null && ! isset($selectedMatches)) {
                return null;
            }
        }
        $cachedData = isset($selectedMatches) ? null : Redis::get($cacheKey);

        $matches = $selectedMatches ?? ($cachedData === null ? null : json_decode($cachedData, true));
        if ($cacheKey === 'Football:matches' && is_array($matches) && $matches !== []) {
            try {
                $controller = app(FootballSyncController::class);
                if ($readOnly) {
                    unset($cachedData);

                    $matches = $controller->mergeDuplicateMatches($matches);
                    if ($matchIds !== null) {
                        $ids = array_fill_keys(array_map('strval', $matchIds), true);
                        $matches = array_filter($matches, static fn (array $match): bool => isset($ids[(string) ($match['sportsApiPro_match_id'] ?? '')]));
                    }

                    return $matches;
                }
                $originalMatches = $matches;
                $matches = $controller->mergeDuplicateMatches($matches);
                $changed = $matches !== $originalMatches;
                unset($originalMatches);
                $timelineIds = [];
                $matchKeys = [];
                foreach ($matches as $matchKey => $match) {
                    if ($restoreVisibleFootballTimelines && (($match['result_status'] ?? '') === 'Finished' || MatchBackup::isHidden($match, 'football'))) {
                        continue;
                    }
                    $matchId = $match['sportsApiPro_match_id'] ?? null;
                    if (is_scalar($matchId) && preg_match('/^[1-9][0-9]*$/', (string) $matchId)) {
                        $timelineIds[] = $matchId;
                        $matchKeys[] = $matchKey;
                    }
                }
                $savedTimelines = $timelineIds === [] ? collect() : DB::table('football_timeline')->whereIn('match_id', $timelineIds)->get(['match_id', 'timeline'])->keyBy('match_id');
                foreach ($matchKeys as $matchKey) {
                    $match = &$matches[$matchKey];
                    $saved = $savedTimelines->get((string) ($match['sportsApiPro_match_id'] ?? ''));
                    $timeline = json_decode($saved->timeline ?? '[]', true) ?? [];
                    if ($timeline !== []) {
                        $controller ??= app(FootballSyncController::class);
                        $changed = $controller->storeTimeline($match, frameType: 'restore', storedTimeline: $timeline) || $changed;
                    }
                }
                unset($match);
                unset($savedTimelines, $timelineIds, $matchKeys, $timeline, $saved);
                if ($changed && ! isset($selectedMatches)) {
                    Redis::eval('if redis.call("GET", KEYS[1]) == ARGV[1] then return redis.call("SET", KEYS[1], ARGV[2], "KEEPTTL") end return 0', 1, $cacheKey, $cachedData, json_encode($matches));
                }
            } catch (Throwable $exception) {
                Log::warning('Football timeline read fallback unavailable', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]);
            }
        }

        if ($matchIds !== null && is_array($matches)) {
            $ids = array_fill_keys(array_map('strval', $matchIds), true);
            $matches = array_filter($matches, static fn (array $match): bool => isset($ids[(string) ($match['sportsApiPro_match_id'] ?? '')]));
        }

        return $matches;
    }
}

if (! function_exists('getVisibleSportsMatches')) {
    function getVisibleSportsMatches(string $cacheKey): array
    {
        $sport = MatchBackup::sportForCache($cacheKey);
        $matches = getInRedis($cacheKey) ?? [];

        return $sport === null ? $matches : array_filter($matches, static fn (array $match): bool => ! MatchBackup::isHidden($match, $sport));
    }
}

if (! function_exists('withRedisLock')) {
    function withRedisLock(string $cacheKey, callable $callback, int $waitSeconds = 5): mixed
    {
        return Cache::store('redis')
            ->lock("{$cacheKey}:write-lock", 30)
            ->block($waitSeconds, $callback);
    }
}

if (! function_exists('getCurrentUserTimezone')) {
    function getCurrentUserTimezone()
    {
        return auth('customers')->user()?->timezone ?? fetch_local_timezone();

        return 'Asia/Kolkata';
    }
}

if (! function_exists('fetch_local_timezone')) {
    function fetch_local_timezone()
    {
        try {
            return session('local_timezone', config('app.timezone', 'UTC'));
        } catch (Throwable $th) {
            return 'UTC';
        }
    }
}

if (! function_exists('checkAuthAndSubscription')) {
    function checkAuthAndSubscription()
    {
        if (! auth()->guard('customers')->check()) {
            return ['is_authenticated' => false, 'has_active_subscription' => false, 'display_odds' => false];
        }

        $customer = auth()->guard('customers')->user();
        if ($customer->hasActiveSubscription()) {
            return ['is_authenticated' => true, 'has_active_subscription' => true, 'display_odds' => true];
        }

        if ($customer->hasActiveTrial()) {
            return ['is_authenticated' => true, 'has_active_subscription' => true, 'display_odds' => true];
        }

        return ['is_authenticated' => true, 'has_active_subscription' => false, 'display_odds' => false];
    }
}
