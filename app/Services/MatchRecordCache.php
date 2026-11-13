<?php

namespace App\Services;

use App\Http\Controllers\Users\Football\FootballController;
use App\Http\Controllers\Users\Football\FootballSyncController;
use Fiber;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Redis;
use WeakMap;

abstract class MatchRecordCache
{
    protected const PREFIX = 'Football';

    protected const SPORT = 'football';

    protected const CATALOG = 'Football:matches:catalog';

    protected const IDS = 'Football:matches:ids';

    protected const READY = 'Football:matches:individual';

    /** @var array<string, array<string, mixed>> */
    private array $mainReads = [];

    /** @var WeakMap<Fiber, array<string, array<string, mixed>>> */
    private WeakMap $fiberReads;

    public function __construct()
    {
        $this->fiberReads = new WeakMap;
    }

    public function active(): bool
    {
        return (bool) config('services.'.static::SPORT.'_match_cache')
            && Redis::get(static::READY) === '1';
    }

    public function recordKey(string $key): string
    {
        return static::PREFIX.':match:'.hash('sha256', $key);
    }

    /** @return array<string, array<string, mixed>> */
    private function catalog(): array
    {
        $rows = Redis::hgetall(static::CATALOG);
        $catalog = [];
        $expired = [];
        foreach ($rows as $key => $json) {
            $entry = json_decode($json, true);
            if (! is_array($entry) || ($entry['_expires_at'] ?? 0) <= time()) {
                $expired[] = [$key, $json];

                continue;
            }
            $catalog[$key] = $entry;
        }
        foreach ($expired as [$key, $json]) {
            $entry = json_decode($json, true) ?? [];
            Redis::eval('if redis.call("HGET", KEYS[1], ARGV[1]) == ARGV[2] then redis.call("HDEL", KEYS[1], ARGV[1]); if redis.call("HGET", KEYS[2], ARGV[3]) == ARGV[1] then redis.call("HDEL", KEYS[2], ARGV[3]) end end; return 1',
                2, static::CATALOG, static::IDS, $key, $json, (string) ($entry['sportsApiPro_match_id'] ?? ''));
        }

        return $catalog;
    }

    /** @return array<string, mixed> */
    public function index(): array
    {
        $matches = $this->catalog();
        $ids = [];
        $football = static::SPORT === 'football' ? app(FootballController::class) : null;
        foreach ($matches as $key => &$match) {
            if ($football !== null) {
                $match['_fixture_priority'] = $football->footballLeaguePriority(
                    (string) ($match['tournament'] ?? ''), $match['tournament_country'] ?? null,
                );
            }
            if (! empty($match['sportsApiPro_match_id'])) {
                $ids[(string) $match['sportsApiPro_match_id']] = $key;
            }
        }
        unset($match);
        $orders = [];
        foreach (['competition', 'default', 'time'] as $mode) {
            $ordered = $matches;
            uasort($ordered, static function (array $first, array $second) use ($mode, $football): int {
                $priority = $first['_fixture_priority'] <=> $second['_fixture_priority'];
                $time = (int) ($first['timestamp'] ?? 0) <=> (int) ($second['timestamp'] ?? 0);
                $competition = $mode === 'competition' && $priority === 0
                    ? strcmp((string) ($first['tournament_id'] ?? $first['tournament'] ?? ''), (string) ($second['tournament_id'] ?? $second['tournament'] ?? ''))
                    : 0;

                $liveComparison = $football !== null
                    ? (($second['result_status'] ?? null) === 'Live') <=> (($first['result_status'] ?? null) === 'Live')
                    : 0;

                return $liveComparison ?: ($mode === 'time' ? ($time ?: $priority) : ($priority ?: $competition ?: $time));
            });
            $orders[$mode] = array_keys($ordered);
        }

        return ['format' => 4, 'market_activity' => 1, 'statistics_schedule' => 2,
            'matches' => $matches, 'ids' => $ids, 'orders' => $orders];
    }

    /** @return array<string, array<string, mixed>> */
    public function read(?array $matchIds = null, ?array $matchKeys = null, ?array $snapshot = null): array
    {
        if (isset($snapshot['matches'])) {
            $catalog = $snapshot['matches'];
        } elseif ($matchKeys !== null || $matchIds !== null) {
            $keys = $matchKeys;
            if ($keys === null) {
                $keys = $matchIds === [] ? [] : array_values(array_filter(Redis::hmget(static::IDS, array_map('strval', $matchIds))));
            }
            $entries = $keys === [] ? [] : Redis::hmget(static::CATALOG, $keys);
            $catalog = [];
            foreach ($keys as $position => $key) {
                $entry = json_decode($entries[$position] ?? '', true);
                if (is_array($entry) && ($entry['_expires_at'] ?? 0) > time()) {
                    $catalog[$key] = $entry;
                }
            }
        } else {
            $catalog = $this->catalog();
        }
        if ($matchKeys !== null) {
            $catalog = array_intersect_key($catalog, array_fill_keys($matchKeys, true));
        } elseif ($matchIds !== null) {
            $ids = array_fill_keys(array_map('strval', $matchIds), true);
            $catalog = array_filter($catalog, static fn (array $entry): bool => isset($ids[(string) ($entry['sportsApiPro_match_id'] ?? '')]));
        }
        $result = [];
        foreach (array_chunk(array_keys($catalog), 100) as $keys) {
            $values = Redis::mget(array_map(fn (string $key): string => $this->recordKey($key), $keys));
            foreach ($keys as $position => $key) {
                $match = json_decode($values[$position] ?? '', true);
                if (is_array($match) && $this->allowed($match)) {
                    $result[$key] = $match;
                } elseif (! is_array($match)) {
                    Redis::eval('if redis.call("EXISTS", KEYS[1]) == 0 then redis.call("HDEL", KEYS[2], ARGV[1]); if redis.call("HGET", KEYS[3], ARGV[2]) == ARGV[1] then redis.call("HDEL", KEYS[3], ARGV[2]) end end; return 1',
                        3, $this->recordKey((string) $key), static::CATALOG, static::IDS, (string) $key, (string) ($catalog[$key]['sportsApiPro_match_id'] ?? ''));
                }
            }
        }
        $fiber = Fiber::getCurrent();
        if ($fiber === null) {
            $this->mainReads = $result;
        } else {
            $this->fiberReads[$fiber] = $result;
        }

        return $result;
    }

    /**
     * Apply only fields changed by this writer, preserving concurrent updates.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function mergeChanges(array $before, array $incoming, array $current): array
    {
        foreach ($before as $field => $value) {
            if (! array_key_exists($field, $incoming) && ($current[$field] ?? null) === $value) {
                unset($current[$field]);
            }
        }
        foreach ($incoming as $field => $value) {
            if (array_key_exists($field, $before) && $value === $before[$field]) {
                continue;
            }
            $previous = $before[$field] ?? [];
            $latest = $current[$field] ?? [];
            if (is_array($value) && ($value === [] || ! array_is_list($value))
                && is_array($previous) && ($previous === [] || ! array_is_list($previous))
                && is_array($latest) && ($latest === [] || ! array_is_list($latest))) {
                $current[$field] = $this->mergeChanges($previous, $value, $latest);
            } else {
                $current[$field] = $value;
            }
        }

        return $current;
    }

    /** @return array<string, mixed> */
    protected function entry(string $key, array $match, string $encoded, int $expires): array
    {
        $fields = ['unique_key', 'sportsApiPro_match_id', 'betfair_match_id', 'timestamp', 'result_status', 'minutes_status',
            'current_period_start_timestamp', 'added_time', 'tournament', 'tournament_country', 'tournament_id',
            'tournament_logo', 'country_id', 'internal_backup', 'provider_lifecycle', 'pinnacle_drop_triggered',
            'pinnacle_market_shock_detected_at', 'home_score', 'away_score'];
        $entry = array_intersect_key($match, array_flip($fields));
        $entry['_fixture_cache_key'] = $key;
        $entry['_expires_at'] = $expires;
        $entry['_payload_bytes'] = strlen($encoded);
        $entry['_cache_updated_at'] = microtime(true);
        $entry['_fixture_priority'] = app(FootballController::class)->footballLeaguePriority(
            (string) ($match['tournament'] ?? ''), $match['tournament_country'] ?? null,
        );
        $version = hash('xxh3', $encoded);
        $entry['_fixture_source_version'] = ['odds' => $version, 'timeline' => $version];
        $entry['markets'] = [];
        $entry['bet365_prematch'] = array_intersect_key($match['bet365_prematch'] ?? [], array_flip(['favorite_side', 'favorite_odds']));
        $entry['_market_activity'] = prepareMatchOddsMonitoring($match, marketActivitySummary: true);
        if (! empty($match['statistics_requests'])) {
            $entry['_statistics_due_at'] = PHP_FLOAT_MAX;
            foreach (FootballSyncController::prematchStatisticsSections($match['result_status'] ?? null) as $section) {
                $attempt = $match['statistics_requests'][$section] ?? [];
                $entry['_statistics_due_at'] = min($entry['_statistics_due_at'], ($attempt['at'] ?? 0) + (($attempt['available'] ?? false) ? 1800 : 120));
            }
        }
        if (! empty($match['v6_stats']['next_poll_at'])) {
            $entry['_v6_next_poll_at'] = (int) $match['v6_stats']['next_poll_at'];
        }

        return $entry;
    }

    public function store(array $matches, int $ttlMinutes, bool $mergeLifecycle, ?array $existing = null): void
    {
        $fiber = Fiber::getCurrent();
        $reads = $fiber === null ? $this->mainReads : ($this->fiberReads[$fiber] ?? []);
        $ttl = max(60, $ttlMinutes * 60);
        $unchanged = array_filter($matches, fn (array $match, string|int $key): bool => $match === ($existing[$key] ?? $reads[$key] ?? null)
            && $this->allowed($match), ARRAY_FILTER_USE_BOTH);
        foreach (array_chunk(array_keys($unchanged), 100) as $keys) {
            $results = Redis::pipeline(function ($pipeline) use ($keys, $ttl): void {
                foreach ($keys as $key) {
                    $pipeline->eval(<<<'LUA'
local entry = redis.call('HGET', KEYS[2], ARGV[1])
if not entry or redis.call('EXISTS', KEYS[1]) == 0 then return 0 end
local metadata = cjson.decode(entry)
metadata['_expires_at'] = tonumber(ARGV[2]) + tonumber(ARGV[3])
redis.call('EXPIRE', KEYS[1], ARGV[3])
redis.call('HSET', KEYS[2], ARGV[1], cjson.encode(metadata))
return 1
LUA, [$this->recordKey((string) $key), static::CATALOG, (string) $key, time(), $ttl], 2);
                }
            });
            foreach ($keys as $position => $key) {
                if ($results[$position]) {
                    unset($matches[$key]);
                }
            }
        }
        foreach ($matches as $key => $incoming) {
            $key = (string) $key;
            if (! $this->allowed($incoming)) {
                continue;
            }
            $before = $existing[$key] ?? $reads[$key] ?? [];
            $recordKey = $this->recordKey($key);
            $stored = false;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $raw = Redis::get($recordKey);
                $current = json_decode($raw ?? '[]', true) ?? [];
                $match = $current === [] ? $incoming : $this->mergeChanges($before, $incoming, $current);
                if (($current['result_status'] ?? '') === 'Finished' && ($match['result_status'] ?? '') !== 'Finished') {
                    $match = $current;
                }
                $incomingSequence = (int) ($incoming['provider_lifecycle']['sequence'] ?? 0);
                if (($incomingSequence > 0 && (int) ($current['provider_lifecycle']['sequence'] ?? 0) > $incomingSequence)
                    || ((int) ($incoming['tennis_score_source_timestamp'] ?? 0) > 0
                        && (int) ($current['tennis_score_source_timestamp'] ?? 0) > (int) $incoming['tennis_score_source_timestamp'])
                    || (($current['result_status'] ?? '') === 'Live' && ($match['result_status'] ?? '') === 'Prematch')) {
                    foreach (['result_status', 'minutes_status', 'home_score', 'away_score', 'current_period_start_timestamp', 'last_period_end_timestamp', 'added_time', 'timeline', 'provider_lifecycle', 'home_score_data', 'away_score_data', 'current_set', 'serving', 'first_to_serve', 'tennis_score_version', 'tennis_score_source_timestamp', 'tennis_points_source', 'sports_change_timestamp', 'websocket_change_timestamps'] as $field) {
                        if (array_key_exists($field, $current)) {
                            $match[$field] = $current[$field];
                        }
                    }
                }
                if ($mergeLifecycle) {
                    $match = MatchBackup::merge($match, $current, static::SPORT);
                }
                if (($match['timeline'] ?? []) !== ($current['timeline'] ?? [])) {
                    $this->persistTimeline($match, $current);
                }
                $encoded = json_encode($match, JSON_THROW_ON_ERROR);
                $entry = json_encode($this->entry($key, $match, $encoded, time() + $ttl), JSON_THROW_ON_ERROR);
                $stored = (bool) Redis::eval(<<<'LUA'
local current = redis.call('GET', KEYS[1])
if (ARGV[1] == '0' and current) or (ARGV[1] == '1' and current ~= ARGV[2]) then return 0 end
local cleaned = redis.call('ZSCORE', KEYS[3], ARGV[4])
if cleaned and tonumber(cleaned) >= tonumber(ARGV[7]) then return 2 end
redis.call('SET', KEYS[1], ARGV[3], 'EX', ARGV[6])
redis.call('HSET', KEYS[2], ARGV[4], ARGV[5])
if ARGV[8] ~= '' then redis.call('HSET', KEYS[4], ARGV[8], ARGV[4]) end
return 1
LUA, 4, $recordKey, static::CATALOG, static::PREFIX.':matches:cleaned', static::IDS,
                    is_string($raw) ? '1' : '0', is_string($raw) ? $raw : '', $encoded, $key, $entry, $ttl, time(), (string) ($match['sportsApiPro_match_id'] ?? ''));
                if ($stored) {
                    if ($fiber === null) {
                        $this->mainReads[$key] = $match;
                    } else {
                        $this->fiberReads[$fiber] = array_replace($this->fiberReads[$fiber] ?? [], [$key => $match]);
                    }
                    break;
                }
            }
            if (! $stored) {
                throw new LockTimeoutException('Football match changed concurrently; retry required.');
            }
        }
    }

    public function removeCleaned(): void
    {
        foreach (Redis::zrangebyscore(static::PREFIX.':matches:cleaned', time(), '+inf') as $key) {
            $raw = Redis::get($this->recordKey((string) $key));
            $match = json_decode($raw ?? '[]', true) ?? [];
            Redis::eval(<<<'LUA'
local cleaned = redis.call('ZSCORE', KEYS[4], ARGV[1])
if not cleaned or tonumber(cleaned) < tonumber(ARGV[3]) then return 0 end
redis.call('DEL', KEYS[1])
redis.call('HDEL', KEYS[2], ARGV[1])
if redis.call('HGET', KEYS[3], ARGV[2]) == ARGV[1] then redis.call('HDEL', KEYS[3], ARGV[2]) end
return 1
LUA, 4, $this->recordKey((string) $key), static::CATALOG, static::IDS, static::PREFIX.':matches:cleaned',
                (string) $key, (string) ($match['sportsApiPro_match_id'] ?? ''), time());
        }
    }

    abstract protected function allowed(array $match): bool;

    protected function canonicalMatches(array $matches): array
    {
        return app(FootballSyncController::class)->mergeDuplicateMatches($matches);
    }

    protected function persistTimeline(array &$match, array $previous): void
    {
        app(FootballSyncController::class)->storeTimeline($match, frameType: 'normalize', previous: $previous);
    }

    /** @return array<string, int> */
    public function migrate(): array
    {
        if ($this->active()) {
            return ['matches' => count($this->catalog()), 'legacy_bytes' => 0];
        }
        $raw = Redis::get(static::PREFIX.':matches');
        $matches = json_decode($raw ?? '[]', true, flags: JSON_THROW_ON_ERROR);
        $matches = $this->canonicalMatches($matches);
        $matches = array_filter($matches, fn (array $match): bool => $this->allowed($match));
        $this->store($matches, 60, false, []);
        Redis::set(static::READY, '1');

        return ['matches' => count($matches), 'legacy_bytes' => strlen($raw ?? '')];
    }
}
