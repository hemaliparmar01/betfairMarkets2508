<?php

namespace Tests\Feature;

use Amp\Websocket\Client\WebsocketConnection;
use App\Console\Commands\ListenSportsApiPro;
use App\Services\FootballMatchCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class FootballMatchCacheTest extends TestCase
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
        $prefix = 'match-cache-test-'.bin2hex(random_bytes(8)).':';
        foreach (['default', 'cache'] as $connection) {
            config(['database.redis.'.$connection => ['url' => null, 'host' => $socket, 'port' => 0, 'database' => 0, 'password' => null, 'persistent' => false]]);
        }
        config(['services.football_match_cache' => true, 'database.redis.options.prefix' => $prefix,
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
            'team1' => 'Home '.$id, 'team2' => 'Away '.$id, 'timestamp' => time() - 600,
            'tournament' => 'Serie A', 'tournament_country' => 'Italy',
            'result_status' => 'Prematch', 'minutes_status' => 'Prematch',
            'home_score' => 0, 'away_score' => 0, 'markets' => []];
    }

    private function migrate(): FootballMatchCache
    {
        Redis::setex('Football:matches', 3600, json_encode(['match-123' => $this->fixture(123), 'match-124' => $this->fixture(124)]));
        $cache = app(FootballMatchCache::class);
        $this->assertSame(2, $cache->migrate()['matches']);

        return $cache;
    }

    public function test_migration_keeps_each_match_and_index_consistent_and_is_repeatable(): void
    {
        $cache = $this->migrate();
        $this->assertTrue($cache->active());
        $this->assertCount(2, $cache->index()['matches']);
        $this->assertSame(['match-123'], array_keys(getInRedis('Football:matches', readOnly: true, matchIds: [123])));
        $this->assertGreaterThan(0, Redis::ttl($cache->recordKey('match-123')));
        $this->assertSame(2, $cache->migrate()['matches']);
        $this->artisan('football:cache-migrate --status --match=123')->assertSuccessful();
    }

    public function test_live_score_updates_ignore_the_legacy_global_lock_and_persist_the_same_timeline(): void
    {
        $cache = $this->migrate();
        $legacy = Redis::get('Football:matches');
        $lock = Cache::store('redis')->lock('Football:matches:write-lock', 30);
        $this->assertTrue($lock->get());
        try {
            $listener = app(ListenSportsApiPro::class);
            $method = new \ReflectionMethod($listener, 'processSportsFrame');
            $config = (new \ReflectionClass($listener))->getConstant('SPORTS')['football'];
            $socket = \Mockery::mock(WebsocketConnection::class);
            $subscriptions = [];
            $data = [
                'id' => 123, 'status.code' => 6, 'status.type' => 'inprogress',
                'homeScore.current' => 1, 'changes.changeTimestamp' => time(),
                'time.currentPeriodStartTimestamp' => time() - 600,
            ];
            $method->invokeArgs($listener, ['football', $config, ['type' => 'update', 'channel' => 'match:123', 'data' => $data], $socket, &$subscriptions]);
        } finally {
            $lock->release();
        }

        $updated = getInRedis('Football:matches', readOnly: true, matchIds: [123])['match-123'];
        $this->assertSame('Live', $updated['result_status']);
        $this->assertSame(1, $updated['home_score']);
        $this->assertSame('Live', $cache->index()['matches']['match-123']['result_status']);
        $this->assertSame('Prematch', getInRedis('Football:matches', readOnly: true, matchIds: [124])['match-124']['result_status']);
        $this->assertSame($legacy, Redis::get('Football:matches'));
        $timeline = json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true);
        $this->assertSame($updated['timeline'], $timeline);
    }

    public function test_a_stale_statistics_writer_keeps_new_live_score_and_timeline(): void
    {
        $this->migrate();
        $before = getInRedis('Football:matches', readOnly: true, matchIds: [123]);
        $live = $before;
        $live['match-123']['result_status'] = 'Live';
        $live['match-123']['minutes_status'] = '1st half';
        $live['match-123']['home_score'] = 1;
        $live['match-123']['provider_lifecycle']['sequence'] = 20;
        storeInRedis('Football:matches', $live, 10, false, $before);
        $statistics = $before;
        $statistics['match-123']['prematch_statistics']['overview'] = ['venue' => 'Test venue'];
        storeInRedis('Football:matches', $statistics, 10, false, $before);
        storeInRedis('Football:matches', $before, 10, false, $before);

        $updated = getInRedis('Football:matches', readOnly: true, matchIds: [123])['match-123'];
        $this->assertSame('Live', $updated['result_status']);
        $this->assertSame(1, $updated['home_score']);
        $this->assertSame('Test venue', $updated['prematch_statistics']['overview']['venue']);
        $this->assertSame(20, $updated['provider_lifecycle']['sequence']);
    }

    public function test_concurrent_statistics_sections_merge_without_dropping_each_other(): void
    {
        $this->migrate();
        $before = getInRedis('Football:matches', readOnly: true, matchIds: [123]);
        $overview = $before;
        $overview['match-123']['prematch_statistics']['overview'] = ['venue' => 'Venue'];
        $form = $before;
        $form['match-123']['prematch_statistics']['pregame-form'] = ['rating' => 5];
        storeInRedis('Football:matches', $overview, 10, false, $before);
        storeInRedis('Football:matches', $form, 10, false, $before);
        $updated = getInRedis('Football:matches', readOnly: true, matchIds: [123])['match-123'];
        $this->assertSame('Venue', $updated['prematch_statistics']['overview']['venue']);
        $this->assertSame(5, $updated['prematch_statistics']['pregame-form']['rating']);
    }

    public function test_cleanup_removes_tombstoned_records_without_removing_other_matches(): void
    {
        $cache = $this->migrate();
        Redis::zadd('Football:matches:cleaned', time() + 600, 'match-123');
        $cache->removeCleaned();
        $this->assertNull(Redis::get($cache->recordKey('match-123')));
        $this->assertArrayNotHasKey('match-123', $cache->index()['matches']);
        $this->assertCount(1, getInRedis('Football:matches', readOnly: true));
        storeInRedis('Football:matches', ['match-123' => $this->fixture(123)], 10, false);
        $this->assertSame([], getInRedis('Football:matches', readOnly: true, matchIds: [123]));
    }

    public function test_expired_records_leave_the_index_and_do_not_return_legacy_prematch_data(): void
    {
        $cache = $this->migrate();
        Redis::del($cache->recordKey('match-123'));
        $this->assertSame([], getInRedis('Football:matches', readOnly: true, matchIds: [123]));
        $this->assertArrayNotHasKey('match-123', $cache->index()['matches']);
        $this->assertSame('Prematch', getInRedis('Football:matches', readOnly: true, matchIds: [124])['match-124']['result_status']);
    }
}
