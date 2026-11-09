<?php

namespace Tests\Feature;

use App\Http\Controllers\Users\Tennis\TennisSyncController;
use App\Services\TennisMatchCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class TennisMatchCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $socket = getenv('BFMARKETS_TEST_REDIS_SOCKET');
        if (! $socket) {
            $this->markTestSkipped('Requires a disposable Redis Unix socket.');
        }
        $this->assertStringStartsWith('/private/tmp/bfmarkets-sports-tests-', $socket);
        $prefix = 'tennis-cache-test-'.bin2hex(random_bytes(8)).':';
        foreach (['default', 'cache'] as $connection) {
            config(['database.redis.'.$connection => ['url' => null, 'host' => $socket, 'port' => 0, 'database' => 0, 'password' => null, 'persistent' => false]]);
        }
        config(['services.tennis_match_cache' => true, 'database.redis.options.prefix' => $prefix,
            'cache.prefix' => $prefix, 'cache.stores.redis.driver' => 'redis']);
        Redis::purge();
        Redis::purge('cache');
        Cache::purge('redis');
        Bus::fake();
        Event::fake();
        Http::preventStrayRequests();
    }

    private function fixture(int $id): array
    {
        return ['unique_key' => 'match-'.$id, 'sportsApiPro_match_id' => $id,
            'team1' => 'Player One '.$id, 'team2' => 'Player Two '.$id, 'timestamp' => time() - 600,
            'tournament' => 'ATP Paris', 'tournament_country' => 'France',
            'result_status' => 'Prematch', 'minutes_status' => 'Prematch',
            'home_score' => 0, 'away_score' => 0, 'markets' => []];
    }

    private function migrate(): TennisMatchCache
    {
        Redis::setex('Tennis:matches', 3600, json_encode([
            'match-123' => $this->fixture(123),
            'match-124' => $this->fixture(124),
            'unlisted' => [...$this->fixture(125), 'tournament' => 'Unknown Local Cup'],
        ]));
        $cache = app(TennisMatchCache::class);
        $this->assertSame(2, $cache->migrate()['matches']);

        return $cache;
    }

    public function test_tournament_matching_checks_tour_and_blocks_unlisted_competitions(): void
    {
        $this->assertSame(['ATP Masters 1000', 'Paris Masters'], TennisSyncController::tennisCompetition([
            'tournament' => ['name' => 'Paris, France', 'category' => ['name' => 'ATP']],
        ]));
        $this->assertNull(TennisSyncController::tennisCompetition([
            'tournament' => ['name' => 'ATP Paris', 'category' => ['name' => 'WTA']],
        ]));
        $this->assertNull(TennisSyncController::tennisCompetition(['tournament' => 'Unknown Local Cup']));
        $this->assertSame(['Grand Slam', 'Wimbledon'], TennisSyncController::tennisCompetition([
            'tournament' => ['name' => 'Wimbledon', 'category' => ['name' => 'WTA']],
        ]));
    }

    public function test_each_match_uses_one_record_across_prematch_live_and_points_updates(): void
    {
        $cache = $this->migrate();
        $lock = Cache::store('redis')->lock('Tennis:matches:write-lock', 30);
        $this->assertTrue($lock->get());
        try {
            $scores = [];
            $updated = withRedisLock('Tennis:matches', fn (): bool => app(TennisSyncController::class)->mergeSportsWebSocketData(
                'match:123', [
                    'id' => 123, 'status' => ['code' => 8, 'type' => 'inprogress', 'description' => '1st set'],
                    'homeScore' => ['current' => 0, 'period1' => 2, 'point' => '30'],
                    'awayScore' => ['current' => 0, 'period1' => 1, 'point' => '15'],
                    'changes' => ['changeTimestamp' => time()],
                ], $scores,
            ));
            $this->assertTrue($updated);
        } finally {
            $lock->release();
        }
        $match = getInRedis('Tennis:matches', readOnly: true, matchIds: [123])['match-123'];
        $this->assertSame('Live', $match['result_status']);
        $this->assertSame('30', (string) $match['home_score_data']['point']);
        $this->assertSame(2, $match['home_score_data']['period1']);
        $this->assertSame('Live', $cache->index()['matches']['match-123']['result_status']);
        $this->assertSame('Prematch', getInRedis('Tennis:matches', readOnly: true, matchIds: [124])['match-124']['result_status']);
        Bus::assertDispatched(CallQueuedClosure::class, fn (CallQueuedClosure $job): bool => $job->queue === 'realtime-tennis');
        $this->artisan('tennis:cache-migrate --status --match=123')->assertSuccessful();
    }

    public function test_stale_background_data_cannot_regress_live_sets_or_points(): void
    {
        $this->migrate();
        $before = getInRedis('Tennis:matches', readOnly: true, matchIds: [123]);
        $live = $before;
        $live['match-123'] = array_replace($live['match-123'], [
            'result_status' => 'Live', 'minutes_status' => '1st set',
            'home_score_data' => ['period1' => 3, 'point' => '40'],
            'tennis_score_source_timestamp' => 2000,
        ]);
        storeInRedis('Tennis:matches', $live, 30, false, $before);
        $stale = $before;
        $stale['match-123']['tournament_logo'] = '/logo.png';
        $stale['match-123']['tennis_score_source_timestamp'] = 1000;
        $stale['match-123']['home_score_data'] = ['period1' => 1, 'point' => '0'];
        storeInRedis('Tennis:matches', $stale, 30, false, $before);
        $match = getInRedis('Tennis:matches', readOnly: true, matchIds: [123])['match-123'];
        $this->assertSame('Live', $match['result_status']);
        $this->assertSame('40', $match['home_score_data']['point']);
        $this->assertSame(3, $match['home_score_data']['period1']);
        $this->assertSame('/logo.png', $match['tournament_logo']);
    }
}
