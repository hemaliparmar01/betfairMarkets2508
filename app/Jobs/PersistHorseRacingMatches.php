<?php

namespace App\Jobs;

use App\Http\Controllers\Users\HorseRacing\HorseRacingSyncController;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class PersistHorseRacingMatches implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    public function __construct()
    {
        $this->onConnection('sports-realtime')->onQueue('sports-sync-prematch')->delay(now()->addSeconds(2));
    }

    public function uniqueId(): string
    {
        return 'horseracing-persistence';
    }

    public function uniqueVia(): Repository
    {
        return Cache::store('redis');
    }

    /** @param array<string, array<string, mixed>> $matches */
    public static function markPending(array $matches): void
    {
        foreach ($matches as $key => $match) {
            Redis::zadd('HorseRacing:persistence:pending', (int) $match['_odds_version'], $key);
        }
        self::dispatch();
    }

    public function handle(HorseRacingSyncController $controller): void
    {
        $lock = Cache::store('redis')->lock('HorseRacing:persistence:lock', 75);
        if (! $lock->get()) {
            $this->release(2);

            return;
        }
        try {
            $keys = Redis::zrange('HorseRacing:persistence:pending', 0, 24);
            if ($keys === []) {
                return;
            }
            $matches = getInRedis('HorseRacing:matches', readOnly: true, matchKeys: $keys) ?? [];
            $matches = array_intersect_key($matches, array_fill_keys($keys, true));
            if (count($matches) !== count($keys)) {
                throw new \RuntimeException('Horse Racing persistence is waiting for missing cached records.');
            }
            $controller->storeMatchesInDatabase($matches);
            foreach ($matches as $key => $match) {
                Redis::eval('local score = redis.call("ZSCORE", KEYS[1], ARGV[1]); if score and tonumber(score) <= tonumber(ARGV[2]) then return redis.call("ZREM", KEYS[1], ARGV[1]) end; return 0',
                    1, 'HorseRacing:persistence:pending', $key, (int) ($match['_odds_version'] ?? 0));
            }
            if (Redis::zcard('HorseRacing:persistence:pending') > 0) {
                self::dispatch();
            }
        } finally {
            $lock->release();
            sportsMatchRecordCache('HorseRacing:matches', activeOnly: false)?->releaseReadSnapshot();
        }
    }

    public function backoff(): array
    {
        return [2, 5, 10, 30];
    }
}
