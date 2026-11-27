<?php

namespace Tests\Feature;

use App\Console\Commands\ListenBetfairOdds;
use App\Jobs\DrainBetfairOdds;
use App\Jobs\ProcessBetfairOdds;
use App\Jobs\PublishHorseRacingOdds;
use App\Services\BetfairPendingOdds;
use Illuminate\Bus\UniqueLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use OverflowException;
use Tests\TestCase;

class BetfairPendingOddsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $socket = getenv('BFMARKETS_TEST_REDIS_SOCKET');
        if (! $socket) {
            $this->markTestSkipped('Requires a disposable Redis Unix socket.');
        }
        $this->assertStringStartsWith('/private/tmp/bfmarkets-sports-tests-', $socket);
        $prefix = 'pending-'.bin2hex(random_bytes(8)).':';
        foreach (['default', 'cache'] as $connection) {
            config(['database.redis.'.$connection => ['url' => null, 'host' => $socket, 'port' => 0, 'database' => 0, 'password' => null, 'persistent' => false]]);
        }
        config(['database.redis.options.prefix' => $prefix, 'cache.prefix' => $prefix,
            'cache.stores.redis.driver' => 'redis', 'services.horseracing_match_cache' => false,
            'services.horseracing_ingestion_queue' => 'ingest-horseracing']);
        Redis::purge();
        Redis::purge('cache');
        Cache::purge('redis');
        Http::preventStrayRequests();
        Event::fake();
        Bus::fake();
    }

    private function event(float $odds = 2.4): array
    {
        return ['sport' => 'horse_racing', 'team1' => 'Ascot', 'team2' => 'Race 1', 'tournament' => 'Ascot', 'country' => 'GB', 'openTimestamp' => gmdate('c', time() + 120),
            'markets' => [['marketId' => '456', 'marketType' => 'WIN', 'runnerId' => '11', 'runner' => 'First Horse', 'handicap' => 0, 'backOdds' => $odds, 'backSize' => 100, 'layOdds' => 3.5, 'laySize' => 200]]];
    }

    public function test_burst_keeps_one_small_queue_job_and_all_quote_history_before_processing_latest_odds(): void
    {
        Bus::fake()->except([DrainBetfairOdds::class]);
        $pending = app(BetfairPendingOdds::class);
        $at = microtime(true);
        for ($sequence = 1; $sequence <= 100; $sequence++) {
            $pending->put('horseracing', 'horse_prematch', '456', $this->event(3 - $sequence / 100), $sequence, $at + $sequence / 100);
            DrainBetfairOdds::dispatch('horseracing');
        }
        $record = array_values($pending->batch('horseracing'))[0];
        $this->assertCount(100, $record['event']['_odds_updates']['456 11 0']);
        $queue = Queue::connection('sports-realtime');
        $this->assertSame(1, $queue->size('ingest-horseracing'));
        $this->assertSame(1, $pending->status('horseracing')['pending_events']);
        $raw = $pending->connection()->lindex('queues:ingest-horseracing', 0);
        $this->assertLessThan(2000, strlen($raw));
        $this->assertStringNotContainsString('First Horse', $raw);
        $job = $queue->pop('ingest-horseracing');
        $job->fire();
        $job->delete();
        $race = getInRedis('HorseRacing:matches', readOnly: true)['horseracing|456'];
        $runner = array_values($race['markets']['456_WIN'])[0];
        $this->assertSame(2, $runner['currentBackOdds']);
        $this->assertCount(100, $runner['backOddsHistory']);
        $this->assertSame(0, $pending->status('horseracing')['pending_events']);
        $this->assertSame(0, $pending->status('horseracing')['compressed_bytes']);
        $this->assertDatabaseCount('horse_racing_matches', 0);
    }

    public function test_acknowledging_an_older_version_keeps_concurrent_update_available(): void
    {
        $pending = app(BetfairPendingOdds::class);
        $pending->put('horseracing', 'horse_prematch', '456', $this->event(), 1, 1000.0);
        $listener = $this->mock(ListenBetfairOdds::class);
        $listener->shouldReceive('processQueuedChanges')->once()->andReturnUsing(function () use ($pending): array {
            $pending->put('horseracing', 'horse_prematch', '456', $this->event(2.34), 2, 1001.0);

            return [];
        });
        (new DrainBetfairOdds('horseracing'))->handle($pending, $listener);
        $this->assertSame(1, $pending->status('horseracing')['pending_events']);
        $record = array_values($pending->batch('horseracing'))[0];
        $this->assertSame(2, $record['sequence']);
        $this->assertSame(2.34, $record['event']['markets'][0]['backOdds']);
        Bus::assertDispatched(DrainBetfairOdds::class);
    }

    public function test_busy_cache_keeps_event_and_does_not_advance_processed_cursor(): void
    {
        $pending = app(BetfairPendingOdds::class);
        $pending->put('horseracing', 'horse_prematch', '456', $this->event(), 1, microtime(true));
        $lock = Cache::store('redis')->lock('HorseRacing:matches:write-lock', 30);
        $lock->get();
        try {
            (new DrainBetfairOdds('horseracing'))->handle($pending, app(ListenBetfairOdds::class));
            $this->assertSame(1, $pending->status('horseracing')['pending_events']);
            $this->assertNull(Cache::store('redis')->get('Betfair:processed:horseracing:horse_prematch:456'));
        } finally {
            $lock->release();
        }
        (new DrainBetfairOdds('horseracing'))->handle($pending, app(ListenBetfairOdds::class));
        $this->assertSame(0, $pending->status('horseracing')['pending_events']);
    }

    public function test_suspension_reopening_and_closure_survive_coalescing(): void
    {
        $pending = app(BetfairPendingOdds::class);
        foreach ([$this->event(), ['win_market_status' => 'SUSPENDED'], $this->event(2.34), ['removed' => true]] as $index => $event) {
            $pending->put('horseracing', 'horse_prematch', '456', $event, $index + 1, microtime(true) + $index);
        }
        (new DrainBetfairOdds('horseracing'))->handle($pending, app(ListenBetfairOdds::class));
        $race = getInRedis('HorseRacing:matches', readOnly: true)['horseracing|456'];
        $this->assertSame('Finished', $race['result_status']);
        $this->assertSame(2.34, array_values($race['markets']['456_WIN'])[0]['currentBackOdds']);
        $this->assertSame(0, $pending->status('horseracing')['pending_events']);
    }

    public function test_capacity_rejects_new_events_without_deleting_pending_history(): void
    {
        config(['services.betfair_pending_max_events' => 1]);
        $pending = app(BetfairPendingOdds::class);
        $pending->put('football', 'prematch', '1', $this->event(), 1, 1000);
        try {
            $pending->put('football', 'prematch', '2', $this->event(), 2, 1001);
            $this->fail('Capacity must not be exceeded.');
        } catch (OverflowException) {
            $this->assertSame(1, $pending->status('football')['pending_events']);
        }
        $pending->put('football', 'prematch', '1', $this->event(2.34), 3, 1002);
        $record = array_values($pending->batch('football'))[0];
        $this->assertCount(2, $record['event']['_odds_updates']['456 11 0']);
        $pending->put('horseracing', 'horse_prematch', '2', $this->event(), 4, 1003);
        $this->assertSame(1, $pending->status('horseracing')['pending_events']);
    }

    public function test_byte_budget_refuses_growth_and_keeps_the_original_queue_payload_recoverable(): void
    {
        config(['services.betfair_pending_max_bytes' => 1048576]);
        $pending = app(BetfairPendingOdds::class);
        $pending->put('football', 'prematch', '1', $this->event(), 1, 1000);
        $before = $pending->status('football')['compressed_bytes'];
        $event = $this->event(2.34);
        $event['large_metadata'] = implode('', array_map(static fn (int $id): string => hash('sha512', (string) $id), range(1, 20000)));
        $queue = Queue::connection('sports-realtime');
        $queue->push(new ProcessBetfairOdds('football', 'prematch', ['2' => $event], 2, 1001), '', 'realtime-football');
        $this->artisan('betfair:pending', ['--sport' => 'football', '--compact-legacy' => true, '--apply' => true, '--workers-paused' => true])->assertFailed();
        $this->assertSame(1, $queue->size('realtime-football'));
        $this->assertSame($before, $pending->status('football')['compressed_bytes']);
        $this->assertSame(1, $pending->status('football')['pending_events']);
    }

    public function test_a_constantly_changing_event_cannot_starve_another_pending_match(): void
    {
        $pending = app(BetfairPendingOdds::class);
        $pending->put('football', 'prematch', '1', $this->event(), 1, 1000);
        $pending->put('football', 'prematch', '2', $this->event(), 2, 1001);
        $record = $pending->batch('football', 1);
        $member = array_key_first($record);
        $pending->put('football', 'prematch', '1', $this->event(2.34), 3, 1002);
        $pending->acknowledge('football', $member, 1);
        $next = array_values($pending->batch('football', 1))[0];
        $this->assertSame('2', $next['id']);
        $this->assertSame(2, $pending->status('football')['pending_events']);
    }

    public function test_large_football_books_are_shared_in_the_buffer_instead_of_repeated_in_queue_jobs(): void
    {
        Bus::fake()->except([DrainBetfairOdds::class]);
        $pending = app(BetfairPendingOdds::class);
        $event = $this->event();
        $event['markets'] = array_map(static fn (int $id): array => ['marketId' => (string) $id, 'marketType' => 'MATCH_ODDS', 'runnerId' => $id, 'runner' => 'Selection '.$id, 'backOdds' => 2.4, 'layOdds' => 2.5, 'backSize' => 100, 'laySize' => 200], range(1, 500));
        for ($sequence = 1; $sequence <= 30; $sequence++) {
            $event['markets'][0]['backOdds'] = 2.4 - $sequence / 100;
            $pending->put('football', 'prematch', '1', $event, $sequence, 1000 + $sequence);
            DrainBetfairOdds::dispatch('football');
        }
        $this->assertSame(1, Queue::connection('sports-realtime')->size('realtime-football'));
        $this->assertLessThan(2000, strlen($pending->connection()->lindex('queues:realtime-football', 0)));
        $this->assertLessThan(250000, $pending->status('football')['compressed_bytes']);
        $record = array_values($pending->batch('football', 1))[0];
        $this->assertCount(500, $record['event']['markets']);
        $this->assertCount(30, $record['event']['_odds_updates']['1 1 0']);
    }

    public function test_sport_and_feed_identity_do_not_mix_live_and_prematch_books(): void
    {
        $pending = app(BetfairPendingOdds::class);
        $pending->put('football', 'prematch', '1', $this->event(), 1, 1000);
        $pending->put('football', 'live', '1', $this->event(2.34), 2, 1001);
        $pending->put('horseracing', 'horse_prematch', '1', $this->event(3.5), 3, 1002);
        $this->assertSame(2, $pending->status('football')['pending_events']);
        $this->assertSame(1, $pending->status('horseracing')['pending_events']);
    }

    public function test_fresh_cutover_is_read_only_until_explicit_apply_and_paused_workers_are_given(): void
    {
        $queue = Queue::connection('sports-realtime');
        $queue->push(new DrainBetfairOdds('football'), '', 'realtime-football');
        $pending = app(BetfairPendingOdds::class);
        $pending->put('football', 'prematch', '1', $this->event(), 1, 1000);
        $this->artisan('betfair:pending', ['--fresh-start' => true])->assertSuccessful();
        $this->assertSame(1, $queue->size('realtime-football'));
        $this->assertSame(1, $pending->status('football')['pending_events']);
        $this->artisan('betfair:pending', ['--fresh-start' => true, '--apply' => true])->assertFailed();
        $this->assertSame(1, $queue->size('realtime-football'));
        $this->assertSame(1, $pending->status('football')['pending_events']);
    }

    public function test_fresh_cutover_discards_ready_reserved_delayed_sports_work_but_keeps_payments_alerts_and_fixtures(): void
    {
        $queue = Queue::connection('sports-realtime');
        $pending = app(BetfairPendingOdds::class);
        $queue->push(new DrainBetfairOdds('football'), '', 'realtime-football');
        $this->assertNotNull($queue->pop('realtime-football'));
        $queue->push(new DrainBetfairOdds('football'), '', 'realtime-football');
        $queue->later(60, new DrainBetfairOdds('football'), '', 'realtime-football');
        $queue->push(new DrainBetfairOdds('horseracing'), '', 'ingest-horseracing');
        $queue->push(new PublishHorseRacingOdds, '', 'realtime-horseracing');
        foreach (['payments', 'alerts', 'alerts-telegram', 'default', 'sports-sync-prematch'] as $name) {
            $queue->pushRaw(json_encode(['job' => 'ProtectedWork', 'data' => ['queue' => $name]], JSON_THROW_ON_ERROR), $name);
        }
        Redis::set('Football:matches', 'existing-fixtures');
        Redis::set('Betfair:processing:sequence', 100);
        Redis::zadd('HorseRacing:broadcast:pending', 1, 'horseracing|456');
        Redis::zadd('HorseRacing:persistence:pending', 1, 'horseracing|456');
        $pending->put('football', 'prematch', '1', $this->event(), 1, 1000);
        $pending->put('horseracing', 'horse_prematch', '456', $this->event(), 2, 1001);
        $locks = new UniqueLock(Cache::store('redis'));
        $this->assertTrue($locks->acquire(new DrainBetfairOdds('football')));
        $this->artisan('betfair:pending', ['--fresh-start' => true, '--apply' => true, '--workers-paused' => true])->assertSuccessful();
        foreach (['realtime-football', 'realtime-horseracing', 'ingest-horseracing'] as $name) {
            $this->assertSame(0, $queue->size($name));
        }
        foreach (['payments', 'alerts', 'alerts-telegram', 'default', 'sports-sync-prematch'] as $name) {
            $this->assertSame(1, $queue->size($name));
        }
        $this->assertSame('existing-fixtures', Redis::get('Football:matches'));
        $this->assertSame('100', Redis::get('Betfair:processing:sequence'));
        $this->assertSame(0, $pending->status('football')['pending_events']);
        $this->assertSame(0, $pending->status('horseracing')['compressed_bytes']);
        $this->assertSame(0, Redis::zcard('HorseRacing:broadcast:pending'));
        $this->assertSame(0, Redis::zcard('HorseRacing:persistence:pending'));
        Bus::fake()->except([DrainBetfairOdds::class]);
        $pending->put('football', 'prematch', '1', $this->event(2.34), 101, microtime(true));
        DrainBetfairOdds::dispatch('football');
        $this->assertSame(1, $queue->size('realtime-football'));
        $this->assertSame(2.34, array_values($pending->batch('football', 1))[0]['event']['markets'][0]['backOdds']);
    }

    public function test_mixed_queue_conversion_reaches_legacy_jobs_behind_other_jobs_without_reordering_them(): void
    {
        $queue = Queue::connection('sports-realtime');
        $redis = app(BetfairPendingOdds::class)->connection();
        $key = 'queues:realtime-football';
        $other = static fn (int $id): string => json_encode(['displayName' => 'OtherJob', 'job' => 'OtherHandler', 'data' => ['commandName' => 'OtherJob', 'id' => $id]], JSON_THROW_ON_ERROR);
        $queue->pushRaw($other(1), 'realtime-football');
        $queue->push(new ProcessBetfairOdds('football', 'prematch', ['1' => $this->event(3.0)], 1, 1001), '', 'realtime-football');
        $queue->pushRaw($other(2), 'realtime-football');
        $queue->push(new ProcessBetfairOdds('football', 'prematch', ['1' => $this->event(2.9)], 2, 1002), '', 'realtime-football');
        $queue->push(new DrainBetfairOdds('football'), '', 'realtime-football');
        $queue->push(new ProcessBetfairOdds('football', 'prematch', ['1' => $this->event(2.8)], 3, 1003), '', 'realtime-football');
        $queue->pushRaw($other(3), 'realtime-football');
        $expected = array_values(array_filter($redis->lrange($key, 0, -1), static fn (string $payload): bool => (json_decode($payload, true)['data']['commandName'] ?? '') !== ProcessBetfairOdds::class));
        $options = ['--sport' => 'football', '--compact-legacy' => true, '--apply' => true, '--workers-paused' => true];
        $this->artisan('betfair:pending', $options + ['--limit' => 1])->assertSuccessful();
        $this->assertSame(6, $queue->size('realtime-football'));
        $this->artisan('betfair:pending', $options)->assertSuccessful();
        $this->assertSame($expected, $redis->lrange($key, 0, -1));
        $record = array_values(app(BetfairPendingOdds::class)->batch('football', 1))[0];
        $this->assertSame(3, $record['sequence']);
        $this->assertSame(2.8, $record['event']['markets'][0]['backOdds']);
        $this->assertCount(3, $record['event']['_odds_updates']['456 11 0']);
    }

    public function test_legacy_conversion_is_read_only_by_default_and_retains_other_job_types(): void
    {
        $queue = Queue::connection('sports-realtime');
        foreach ([1, 2] as $sequence) {
            $queue->push(new ProcessBetfairOdds('football', 'prematch', ['1' => $this->event(3 - $sequence / 10)], $sequence, 1000 + $sequence), '', 'realtime-football');
        }
        $queue->push(new DrainBetfairOdds('football'), '', 'realtime-football');
        $this->artisan('betfair:pending', ['--sport' => 'football', '--compact-legacy' => true])->assertSuccessful();
        $this->assertSame(3, $queue->size('realtime-football'));
        $this->artisan('betfair:pending', ['--sport' => 'football', '--compact-legacy' => true, '--apply' => true])->assertFailed();
        $this->assertSame(3, $queue->size('realtime-football'));
        $this->artisan('betfair:pending', ['--sport' => 'football', '--compact-legacy' => true, '--apply' => true, '--workers-paused' => true])->assertSuccessful();
        $this->assertSame(1, $queue->size('realtime-football'));
        $pending = app(BetfairPendingOdds::class);
        $record = array_values($pending->batch('football'))[0];
        $this->assertSame(2.8, $record['event']['markets'][0]['backOdds']);
        $this->assertCount(2, $record['event']['_odds_updates']['456 11 0']);
    }
}
