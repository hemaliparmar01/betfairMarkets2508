<?php

namespace App\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use OverflowException;
use RuntimeException;

/** One compressed pending book per event, with all unprocessed quote observations retained. */
class BetfairPendingOdds
{
    private const MAX_DECODED_BYTES = 67108864;

    public function connection(): Connection
    {
        return Redis::connection(config('queue.connections.sports-realtime.connection', 'default'));
    }

    /** @param array<string, mixed> $event */
    public function put(string $sport, string $feed, string $id, array $event, int $sequence, float $receivedAt): void
    {
        $event = $this->withObservations($event, $receivedAt);
        $redis = $this->connection();
        $member = json_encode([$feed, $id], JSON_THROW_ON_ERROR);
        $keys = $this->keys($sport);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $old = $redis->hget($keys[0], $member);
            $previous = $old ? $this->decode($old) : null;
            if ($previous !== null && $previous['sequence'] >= $sequence) {
                return;
            }
            $record = ['feed' => $feed, 'id' => $id, 'sequence' => $sequence,
                'received_at' => $previous['received_at'] ?? $receivedAt, 'latest_received_at' => $receivedAt,
                'event' => $previous === null ? $event : $this->merge($previous['event'], $event)];
            $json = json_encode($record, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
            if (strlen($json) > self::MAX_DECODED_BYTES) {
                throw new OverflowException('Betfair pending event reached its safe processing size; ingestion is backpressured.');
            }
            $compressed = gzcompress($json, 1);
            if ($compressed === false) {
                throw new RuntimeException('Unable to compress pending Betfair odds.');
            }
            $result = (int) $redis->eval(<<<'LUA'
local current = redis.call('HGET', KEYS[1], ARGV[1])
if (current or '') ~= ARGV[2] then return 0 end
local bytes = tonumber(redis.call('GET', KEYS[4]) or '0') - string.len(current or '') + string.len(ARGV[3])
if bytes > tonumber(ARGV[6]) or (not current and redis.call('HLEN', KEYS[1]) >= tonumber(ARGV[7])) then return -1 end
redis.call('HSET', KEYS[1], ARGV[1], ARGV[3])
redis.call('HSET', KEYS[3], ARGV[1], ARGV[4])
if not redis.call('ZSCORE', KEYS[2], ARGV[1]) then redis.call('ZADD', KEYS[2], ARGV[5], ARGV[1]) end
redis.call('SET', KEYS[4], bytes)
return 1
LUA, 4, ...[...$keys, $member, $old ?: '', $compressed, $sequence, $receivedAt,
                max(1048576, (int) config('services.betfair_pending_max_bytes', 134217728)),
                max(1, (int) config('services.betfair_pending_max_events', 4096))]);
            if ($result === 1) {
                return;
            }
            if ($result === -1) {
                throw new OverflowException('Betfair pending buffer is full; ingestion is backpressured instead of growing Redis queues.');
            }
        }
        throw new RuntimeException('Pending Betfair odds changed concurrently; retry required.');
    }

    /** @return array<string, array{feed: string, id: string, sequence: int, received_at: float, latest_received_at: float, event: array<string, mixed>}> */
    public function batch(string $sport, int $limit = 5): array
    {
        $redis = $this->connection();
        $keys = $this->keys($sport);
        $records = [];
        foreach ($redis->zrange($keys[1], 0, $limit - 1) as $member) {
            $value = $redis->hget($keys[0], $member);
            if (! $value) {
                throw new RuntimeException('Pending Betfair event is missing; refusing to acknowledge it.');
            }
            $records[$member] = $this->decode($value);
        }

        return $records;
    }

    public function acknowledge(string $sport, string $member, int $sequence): void
    {
        $this->connection()->eval(<<<'LUA'
local version = redis.call('HGET', KEYS[3], ARGV[1])
if tonumber(version or '-1') ~= tonumber(ARGV[2]) then
    if version then redis.call('ZADD', KEYS[2], ARGV[3], ARGV[1]) end
    return 0
end
local payload = redis.call('HGET', KEYS[1], ARGV[1])
redis.call('HDEL', KEYS[1], ARGV[1])
redis.call('HDEL', KEYS[3], ARGV[1])
redis.call('ZREM', KEYS[2], ARGV[1])
redis.call('DECRBY', KEYS[4], string.len(payload or ''))
return 1
LUA, 4, ...[...$this->keys($sport), $member, $sequence, microtime(true)]);
    }

    /** @return array{pending_events: int, compressed_bytes: int, oldest_ready_at: float|null} */
    public function status(string $sport): array
    {
        $redis = $this->connection();
        $keys = $this->keys($sport);
        $oldest = $redis->zrange($keys[1], 0, 0, true);

        return ['pending_events' => (int) $redis->zcard($keys[1]),
            'compressed_bytes' => (int) $redis->get($keys[3]),
            'oldest_ready_at' => $oldest === [] ? null : (float) reset($oldest)];
    }

    /** Discard only this sport's unprocessed Betfair buffer during a paused fresh cutover. */
    public function discard(string $sport): void
    {
        $this->connection()->del(...$this->keys($sport));
    }

    /** @return list<string> */
    private function keys(string $sport): array
    {
        if (! in_array($sport, MarketActivityStream::SPORTS, true)) {
            throw new RuntimeException('Unsupported Betfair pending sport.');
        }
        $prefix = 'Betfair:pending:{'.$sport.'}:';

        return [$prefix.'events', $prefix.'order', $prefix.'versions', $prefix.'bytes'];
    }

    /** @return array{feed: string, id: string, sequence: int, received_at: float, latest_received_at: float, event: array<string, mixed>} */
    private function decode(string $payload): array
    {
        $json = gzuncompress($payload, self::MAX_DECODED_BYTES);
        if ($json === false) {
            throw new RuntimeException('Corrupt pending Betfair odds; recovery required.');
        }

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function withObservations(array $event, float $receivedAt): array
    {
        if (! array_key_exists('_odds_updates', $event)) {
            $event['_odds_updates'] = [];
            foreach ($event['markets'] ?? [] as $row) {
                $handicap = rtrim(rtrim(number_format((float) ($row['handicap'] ?? 0), 10, '.', ''), '0'), '.');
                $key = ($row['marketId'] ?? '').' '.($row['runnerId'] ?? '').' '.($handicap === '-0' || $handicap === '' ? '0' : $handicap);
                $point = array_intersect_key($row, array_flip(['backOdds', 'backSize', 'layOdds', 'laySize', 'market_status', 'liquidity_session', 'liquidity_feed', 'liquidity_book']));
                $point['observed_at'] = $event['_received_at'] ?? $receivedAt;
                $event['_odds_updates'][$key] = [$point];
            }
        }
        if (isset($event['_next_event'])) {
            $event['_next_event'] = $this->withObservations($event['_next_event'], $receivedAt);
        }

        return $event;
    }

    /** @param array<string, mixed> $previous
     * @param  array<string, mixed>  $next
     * @return array<string, mixed>
     */
    private function merge(array $previous, array $next): array
    {
        if (isset($previous['_next_event'])) {
            $previous['_next_event'] = $this->merge($previous['_next_event'], $next);

            return $previous;
        }
        if (($this->statusBoundary($previous) !== $this->statusBoundary($next))
            || $this->runnerKeys($previous) !== $this->runnerKeys($next)) {
            $previous['_next_event'] = $next;

            return $previous;
        }
        $updates = $previous['_odds_updates'] ?? [];
        foreach ($next['_odds_updates'] ?? [] as $key => $points) {
            $known = [];
            foreach ($updates[$key] ?? [] as $point) {
                $known[json_encode($point, JSON_THROW_ON_ERROR)] = true;
            }
            foreach ($points as $point) {
                $identity = json_encode($point, JSON_THROW_ON_ERROR);
                if (! isset($known[$identity])) {
                    $updates[$key][] = $point;
                    $known[$identity] = true;
                }
            }
        }
        $next['_odds_updates'] = $updates;
        $next['_received_at'] = $previous['_received_at'] ?? $next['_received_at'] ?? null;

        return $next;
    }

    /** @param array<string, mixed> $event */
    private function statusBoundary(array $event): string
    {
        if ($event['removed'] ?? false) {
            return 'REMOVED';
        }
        $status = $event['win_market_status'] ?? $event['status'] ?? '';

        return in_array($status, ['SUSPENDED', 'CLOSED'], true) ? $status : 'OPEN';
    }

    /** @param array<string, mixed> $event
     * @return list<string>
     */
    private function runnerKeys(array $event): array
    {
        $keys = array_map(static fn (array $row): string => json_encode([$row['marketId'] ?? '', $row['runnerId'] ?? '', (float) ($row['handicap'] ?? 0)], JSON_THROW_ON_ERROR), $event['markets'] ?? []);
        sort($keys, SORT_STRING);

        return $keys;
    }
}
