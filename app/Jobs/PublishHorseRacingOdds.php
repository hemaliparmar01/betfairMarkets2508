<?php

namespace App\Jobs;

use App\Services\HorseRacingOddsStream;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

class PublishHorseRacingOdds implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 30;

    public int $timeout = 10;

    public int $uniqueFor = 30;

    public function __construct()
    {
        $this->onConnection('sports-realtime')->onQueue('realtime-horseracing');
    }

    public function uniqueId(): string
    {
        return 'horseracing-odds-broadcast';
    }

    public function uniqueVia(): Repository
    {
        return Cache::store('redis');
    }

    /** @param array<string, array<string, mixed>> $matches */
    public static function markPending(array $matches): void
    {
        Redis::pipeline(function ($pipeline) use ($matches): void {
            foreach ($matches as $key => $match) {
                $pipeline->eval('local score = redis.call("ZSCORE", KEYS[1], ARGV[1]); if not score or tonumber(score) < tonumber(ARGV[2]) then redis.call("ZADD", KEYS[1], ARGV[2], ARGV[1]) end; return 1',
                    ['HorseRacing:broadcast:pending', $key, (int) $match['_odds_version']], 1);
            }
        });
        self::dispatch();
    }

    public function handle(HorseRacingOddsStream $stream): void
    {
        $lock = Cache::store('redis')->lock('HorseRacing:broadcast:lock', 15);
        if (! $lock->get()) {
            $this->release(1);

            return;
        }
        $startedAt = microtime(true);
        try {
            $keys = Redis::zrange('HorseRacing:broadcast:pending', 0, 4);
            if ($keys === []) {
                return;
            }
            $matches = getInRedis('HorseRacing:matches', readOnly: true, matchKeys: $keys) ?? [];
            $matches = array_intersect_key($matches, array_fill_keys($keys, true));
            foreach ($keys as $key) {
                if (! isset($matches[$key])) {
                    $cleanedUntil = Redis::zscore('HorseRacing:matches:cleaned', $key);
                    if (is_numeric($cleanedUntil) && (float) $cleanedUntil >= time()) {
                        Redis::zrem('HorseRacing:broadcast:pending', $key);

                        continue;
                    }
                    throw new RuntimeException('Horse Racing broadcast is waiting for missing cached records.');
                }
                $match = $matches[$key];
                $stream->publish([$key => $match]);
                Redis::eval('local score = redis.call("ZSCORE", KEYS[1], ARGV[1]); if score and tonumber(score) <= tonumber(ARGV[2]) then return redis.call("ZREM", KEYS[1], ARGV[1]) end; return 0',
                    1, 'HorseRacing:broadcast:pending', $key, (int) $match['_odds_version']);
            }
            Cache::store('redis')->put('HorseRacing:broadcast:health', ['started_at' => $startedAt, 'completed_at' => microtime(true), 'broadcast_ms' => round((microtime(true) - $startedAt) * 1000, 1), 'memory_mb' => round(memory_get_usage(true) / 1048576, 1), 'races' => count($matches)], 86400);
            if (Redis::zcard('HorseRacing:broadcast:pending') > 0) {
                self::dispatch();
            }
        } finally {
            $lock->release();
            sportsMatchRecordCache('HorseRacing:matches', activeOnly: false)?->releaseReadSnapshot();
        }
    }

    public function backoff(): array
    {
        return [1, 2, 5, 10];
    }
}
