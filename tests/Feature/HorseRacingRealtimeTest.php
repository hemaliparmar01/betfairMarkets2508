<?php

namespace Tests\Feature;

use App\Console\Commands\ListenBetfairOdds;
use App\Events\HorseRacingOddsUpdated;
use App\Http\Controllers\Users\HorseRacing\HorseRacingSyncController;
use App\Jobs\DrainBetfairOdds;
use App\Jobs\PersistHorseRacingMatches;
use App\Jobs\ProcessBetfairOdds;
use App\Jobs\PublishHorseRacingOdds;
use App\Models\HorseRacingMatch;
use App\Services\BetfairPendingOdds;
use App\Services\CustomerAuthService;
use App\Services\HorseRacingOddsStream;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use ReflectionMethod;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class HorseRacingRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
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
        $prefix = 'horse-realtime-'.bin2hex(random_bytes(8)).':';
        foreach (['default', 'cache'] as $connection) {
            config(['database.redis.'.$connection => ['url' => null, 'host' => $socket, 'port' => 0, 'database' => 0, 'password' => null, 'persistent' => false]]);
        }
        config(['database.redis.options.prefix' => $prefix, 'cache.prefix' => $prefix, 'cache.stores.redis.driver' => 'redis', 'services.horseracing_match_cache' => false, 'services.horseracing_ingestion_queue' => 'ingest-horseracing']);
        Redis::purge();
        Redis::purge('cache');
        Cache::purge('redis');
        Http::preventStrayRequests();
        Bus::fake();
        Event::fake();
    }

    private function event(float $odds = 2.4): array
    {
        return ['sport' => 'horse_racing', 'team1' => 'Ascot', 'team2' => 'Race 1', 'tournament' => 'Ascot', 'country' => 'GB', 'openTimestamp' => gmdate('c', time() + 120),
            'markets' => [['marketId' => '456', 'marketType' => 'WIN', 'runnerId' => '11', 'runner' => 'First Horse', 'sortPriority' => 1, 'handicap' => 0, 'backOdds' => $odds, 'backSize' => 100, 'layOdds' => 2.42, 'laySize' => 200]]];
    }

    private function process(int $sequence, array $event): void
    {
        (new ProcessBetfairOdds('horseracing', 'horse_prematch', ['456' => $event], $sequence, microtime(true)))->handle(app(ListenBetfairOdds::class));
    }

    private function race(): array
    {
        return getInRedis('HorseRacing:matches', readOnly: true)['horseracing|456'];
    }

    public function test_socket_dispatches_to_each_existing_sport_worker_without_merging_or_dropping_history(): void
    {
        $pending = ['horse_prematch' => ['456' => $this->event() + ['_next_event' => ['win_market_status' => 'SUSPENDED']]],
            'prematch' => ['10' => ['sport' => 'soccer'], '20' => ['sport' => 'tennis'], '30' => ['sport' => 'ice hockey']]];
        (new ReflectionMethod(ListenBetfairOdds::class, 'dispatchPendingChanges'))->invokeArgs(app(ListenBetfairOdds::class), [&$pending]);
        Bus::assertDispatchedTimes(DrainBetfairOdds::class, 4);
        foreach (['football', 'tennis', 'icehockey', 'horseracing'] as $sport) {
            Bus::assertDispatched(DrainBetfairOdds::class, fn ($job): bool => $job->sport === $sport && $job->queue === ($sport === 'horseracing' ? 'ingest-horseracing' : 'realtime-'.$sport) && $job->connection === 'sports-realtime');
        }
        $horse = array_values(app(BetfairPendingOdds::class)->batch('horseracing'))[0]['event'];
        $this->assertSame('SUSPENDED', $horse['_next_event']['win_market_status']);
        $this->assertSame($this->event()['markets'], $horse['markets']);
        $this->assertSame(['horse_prematch' => [], 'prematch' => []], $pending);
        $this->assertDatabaseCount('horse_racing_matches', 0);
    }

    public function test_snapshot_batches_are_bounded_and_failed_queue_dispatch_retains_pending_updates(): void
    {
        $pending = ['horse_prematch' => array_fill_keys(range(1, 80), $this->event())];
        $method = new ReflectionMethod(ListenBetfairOdds::class, 'dispatchPendingChanges');
        $method->invokeArgs(app(ListenBetfairOdds::class), [&$pending]);
        Bus::assertDispatchedTimes(DrainBetfairOdds::class, 1);
        $this->assertSame(80, app(BetfairPendingOdds::class)->status('horseracing')['pending_events']);
        $this->assertCount(5, app(BetfairPendingOdds::class)->batch('horseracing'));
        Bus::assertDispatched(DrainBetfairOdds::class, fn ($job): bool => strlen(serialize($job)) < 2000);
        $pending = ['prematch' => ['999' => ['sport' => 'soccer']]];
        $original = $pending;
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue unavailable.'));
        try {
            $method->invokeArgs(app(ListenBetfairOdds::class), [&$pending]);
            $this->fail('Enqueue failure must leave the event available for retry.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Queue unavailable.', $error->getMessage());
        }
        $this->assertSame($original, $pending);
    }

    public function test_new_odds_are_cached_and_broadcast_before_database_persistence(): void
    {
        $this->process(1, $this->event());
        $this->process(2, $this->event(2.34));
        $this->assertSame(2.34, array_values($this->race()['markets']['456_WIN'])[0]['currentBackOdds']);
        $this->assertDatabaseCount('horse_racing_matches', 0);
        Bus::assertDispatched(PersistHorseRacingMatches::class);
        Event::assertNotDispatched(HorseRacingOddsUpdated::class);
        (new PublishHorseRacingOdds)->handle(app(HorseRacingOddsStream::class));
        Event::assertDispatched(HorseRacingOddsUpdated::class, fn ($event): bool => $event->race['runners'][0]['currentBackOdds'] === 2.34
            && $event->broadcastOn()[0]->name === 'private-sports.horseracing.odds.456');
        (new PersistHorseRacingMatches)->handle(app(HorseRacingSyncController::class));
        $stored = HorseRacingMatch::query()->firstOrFail();
        $this->assertSame(2.34, $stored->markets->first()->payload['currentBackOdds']);
        $this->assertSame(0, Redis::zcard('HorseRacing:persistence:pending'));
    }

    public function test_real_redis_queue_round_trip_processes_the_race_without_database_writes(): void
    {
        Bus::fake()->except([ProcessBetfairOdds::class]);
        $queue = Queue::connection('sports-realtime');
        $queue->push(new ProcessBetfairOdds('horseracing', 'horse_prematch', ['456' => $this->event(2.34)], 1, microtime(true)), '', 'ingest-horseracing');
        $this->assertNull($queue->pop('realtime-horseracing'));
        $job = $queue->pop('ingest-horseracing');
        $this->assertNotNull($job);
        $job->fire();
        $job->delete();
        $this->assertSame(2.34, array_values($this->race()['markets']['456_WIN'])[0]['currentBackOdds']);
        $this->assertDatabaseCount('horse_racing_matches', 0);
    }

    public function test_active_individual_cache_keeps_the_live_update_free_of_database_queries(): void
    {
        $this->process(1, $this->event());
        config(['services.horseracing_match_cache' => true]);
        sportsMatchRecordCache('HorseRacing:matches', activeOnly: false)->migrate();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->process(2, $this->event(2.34));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame([], $queries);
        $this->assertSame(2.34, array_values($this->race()['markets']['456_WIN'])[0]['currentBackOdds']);
    }

    public function test_burst_ticks_keep_one_broadcast_job_and_send_only_the_latest_book(): void
    {
        Bus::fake()->except([PublishHorseRacingOdds::class]);
        for ($sequence = 1; $sequence <= 20; $sequence++) {
            $this->process($sequence, $this->event(2.4 - $sequence / 100));
        }
        $this->assertCount(20, array_values($this->race()['markets']['456_WIN'])[0]['backOddsHistory']);
        $queue = Queue::connection('sports-realtime');
        $this->assertSame(1, $queue->size('realtime-horseracing'));
        $this->assertSame(1, Redis::zcard('HorseRacing:broadcast:pending'));
        Event::assertNotDispatched(HorseRacingOddsUpdated::class);
        $job = $queue->pop('realtime-horseracing');
        $job->fire();
        $job->delete();
        Event::assertDispatchedTimes(HorseRacingOddsUpdated::class, 1);
        Event::assertDispatched(HorseRacingOddsUpdated::class, fn ($event): bool => abs($event->race['runners'][0]['currentBackOdds'] - 2.2) < 0.0001);
        $this->assertSame(0, Redis::zcard('HorseRacing:broadcast:pending'));
    }

    public function test_broadcast_failure_retains_dirty_races_and_retry_reads_the_latest_quote(): void
    {
        $this->process(1, $this->event());
        $stream = \Mockery::mock(HorseRacingOddsStream::class);
        $stream->shouldReceive('publish')->once()->andThrow(new \RuntimeException('Broadcast unavailable.'));
        try {
            (new PublishHorseRacingOdds)->handle($stream);
            $this->fail('Broadcast failure must be retried.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Broadcast unavailable.', $error->getMessage());
        }
        $this->assertSame(1, Redis::zcard('HorseRacing:broadcast:pending'));
        $this->process(2, $this->event(2.34));
        (new PublishHorseRacingOdds)->handle(app(HorseRacingOddsStream::class));
        Event::assertDispatched(HorseRacingOddsUpdated::class, fn ($event): bool => $event->race['runners'][0]['currentBackOdds'] === 2.34);
    }

    public function test_new_tick_during_broadcast_is_not_removed_by_acknowledging_an_older_version(): void
    {
        $this->process(1, $this->event());
        $stream = \Mockery::mock(HorseRacingOddsStream::class);
        $stream->shouldReceive('publish')->once()->andReturnUsing(function (): void {
            $this->process(2, $this->event(2.34));
        });
        (new PublishHorseRacingOdds)->handle($stream);
        $this->assertSame(1, Redis::zcard('HorseRacing:broadcast:pending'));
        (new PublishHorseRacingOdds)->handle(app(HorseRacingOddsStream::class));
        $this->assertSame(0, Redis::zcard('HorseRacing:broadcast:pending'));
        Event::assertDispatched(HorseRacingOddsUpdated::class, fn ($event): bool => $event->race['runners'][0]['currentBackOdds'] === 2.34);
    }

    public function test_broadcast_batches_read_only_five_requested_races_without_scanning_the_catalog(): void
    {
        $events = [];
        for ($id = 456; $id < 462; $id++) {
            $event = $this->event();
            $event['markets'][0]['marketId'] = (string) $id;
            $events[(string) $id] = $event;
        }
        (new ProcessBetfairOdds('horseracing', 'horse_prematch', $events, 1, microtime(true)))->handle(app(ListenBetfairOdds::class));
        config(['services.horseracing_match_cache' => true]);
        sportsMatchRecordCache('HorseRacing:matches', activeOnly: false)->migrate();
        $commands = [];
        Redis::enableEvents();
        $this->app['events']->listen(CommandExecuted::class, function ($event) use (&$commands): void {
            $commands[] = strtolower($event->command);
        });
        try {
            (new PublishHorseRacingOdds)->handle(app(HorseRacingOddsStream::class));
        } finally {
            Redis::disableEvents();
        }
        $this->assertNotContains('hgetall', $commands);
        Event::assertDispatchedTimes(HorseRacingOddsUpdated::class, 5);
        $this->assertSame(1, Redis::zcard('HorseRacing:broadcast:pending'));
    }

    public function test_queue_configuration_is_production_specific_and_can_be_explicitly_overridden(): void
    {
        $code = 'require "vendor/autoload.php"; new Illuminate\Foundation\Application(getcwd()); $services = require "config/services.php"; echo $services["horseracing_ingestion_queue"];';
        foreach (['production' => 'ingest-horseracing', 'staging' => 'realtime-horseracing', 'local' => 'realtime-horseracing', 'testing' => 'realtime-horseracing'] as $environment => $queue) {
            $process = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ENV' => $environment, 'HORSE_RACING_INGESTION_QUEUE' => false]);
            $process->mustRun();
            $this->assertSame($queue, $process->getOutput());
        }
        $process = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ENV' => 'production', 'HORSE_RACING_INGESTION_QUEUE' => 'realtime-horseracing']);
        $process->mustRun();
        $this->assertSame('realtime-horseracing', $process->getOutput());
    }

    public function test_staging_and_local_can_keep_the_existing_queue_without_a_new_service(): void
    {
        config(['services.horseracing_ingestion_queue' => 'realtime-horseracing']);
        $job = new ProcessBetfairOdds('horseracing', 'horse_prematch', [], 1, microtime(true));
        $this->assertSame('realtime-horseracing', $job->queue);
        $this->assertSame('realtime-football', (new ProcessBetfairOdds('football', 'prematch', [], 1, microtime(true)))->queue);
    }

    public function test_retried_older_batch_cannot_overwrite_newer_odds_but_other_races_are_not_skipped(): void
    {
        $this->process(20, $this->event(2.34));
        $job = new ProcessBetfairOdds('horseracing', 'horse_prematch', ['456' => $this->event(2.4), '457' => $this->event(3.5)], 10, microtime(true));
        $job->handle(app(ListenBetfairOdds::class));
        $this->assertSame(2.34, array_values($this->race()['markets']['456_WIN'])[0]['currentBackOdds']);
        $this->assertArrayHasKey('horseracing|457', getInRedis('HorseRacing:matches', readOnly: true));
    }

    public function test_pending_quotes_and_suspension_then_reopening_then_closure_are_applied_in_order(): void
    {
        $event = $this->event();
        $event['_next_event'] = ['win_market_status' => 'SUSPENDED'];
        $this->process(1, $event);
        $this->assertSame('SUSPENDED', $this->race()['market_status']);
        $this->process(2, $this->event(2.34));
        $this->assertSame('OPEN', $this->race()['market_status']);
        $this->process(3, ['removed' => true]);
        $this->assertSame('Finished', $this->race()['result_status']);
        $this->process(2, $this->event(2.4));
        $this->assertSame('Finished', $this->race()['result_status']);
    }

    public function test_database_writer_keeps_newer_dirty_revision_received_while_persisting(): void
    {
        $this->process(1, $this->event());
        $controller = \Mockery::mock(HorseRacingSyncController::class)->makePartial();
        $controller->shouldReceive('storeMatchesInDatabase')->once()->andReturnUsing(function (): void {
            $this->process(2, $this->event(2.34));
        });
        (new PersistHorseRacingMatches)->handle($controller);
        $this->assertSame(1, Redis::zcard('HorseRacing:persistence:pending'));
        (new PersistHorseRacingMatches)->handle(app(HorseRacingSyncController::class));
        $this->assertSame(0, Redis::zcard('HorseRacing:persistence:pending'));
        $this->assertSame(2.34, HorseRacingMatch::query()->firstOrFail()->markets->first()->payload['currentBackOdds']);
    }

    public function test_large_books_are_fragmented_without_broadcasting_history(): void
    {
        $this->process(1, $this->event());
        $race = $this->race();
        $runner = array_values($race['markets']['456_WIN'])[0];
        $race['markets']['456_WIN'] = array_map(fn ($id) => array_replace($runner, ['runnerId' => (string) $id, 'runner' => str_repeat('Horse ', 30), 'backOddsHistory' => array_fill(0, 1000, ['odds' => 2.4])]), range(1, 80));
        $packets = app(HorseRacingOddsStream::class)->packets($race);
        $this->assertCount(20, $packets);
        foreach ($packets as $packet) {
            $this->assertArrayNotHasKey('backOddsHistory', $packet['runners'][0]);
            $this->assertLessThan(8500, strlen(json_encode(json_encode($packet))));
        }
    }

    public function test_unicode_runner_names_stay_below_the_broadcast_request_limit(): void
    {
        $this->process(1, $this->event());
        $race = $this->race();
        $runner = array_values($race['markets']['456_WIN'])[0];
        $race['markets']['456_WIN'] = array_fill(0, 12, array_replace($runner, ['runner' => str_repeat('ა', 255)]));
        foreach (app(HorseRacingOddsStream::class)->packets($race) as $packet) {
            $this->assertLessThan(8500, strlen(json_encode(json_encode($packet))));
        }
    }

    public function test_late_terminal_retry_closes_the_race_without_replaying_old_prices(): void
    {
        $this->process(20, $this->event(2.34));
        $old = $this->event(2.4);
        $old['_next_event'] = ['removed' => true];
        $this->process(10, $old);
        $this->assertSame('Finished', $this->race()['result_status']);
        $this->assertSame(2.34, array_values($this->race()['markets']['456_WIN'])[0]['currentBackOdds']);
        $this->assertSame(20, (int) Cache::store('redis')->get('Betfair:processed:horseracing:horse_prematch:456'));
    }

    public function test_busy_cache_does_not_acknowledge_or_drop_a_quote(): void
    {
        $lock = Cache::store('redis')->lock('HorseRacing:matches:write-lock', 30);
        $lock->get();
        try {
            $this->process(1, $this->event());
            $this->fail('A busy cache must request a retry.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('cache write is busy', $error->getMessage());
            $this->assertNull(Cache::store('redis')->get('Betfair:processed:horseracing:horse_prematch:456'));
        } finally {
            $lock->release();
        }
        $this->process(1, $this->event());
        $this->assertSame(2.4, array_values($this->race()['markets']['456_WIN'])[0]['currentBackOdds']);
    }

    public function test_database_failure_keeps_the_live_quote_and_pending_persistence_for_retry(): void
    {
        $this->process(1, $this->event(2.34));
        $controller = \Mockery::mock(HorseRacingSyncController::class)->makePartial();
        $controller->shouldReceive('storeMatchesInDatabase')->once()->andThrow(new \RuntimeException('Database temporarily unavailable.'));
        try {
            (new PersistHorseRacingMatches)->handle($controller);
            $this->fail('Persistence failure must request a retry.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Database temporarily unavailable.', $error->getMessage());
        }
        $this->assertSame(2.34, array_values($this->race()['markets']['456_WIN'])[0]['currentBackOdds']);
        $this->assertSame(1, Redis::zcard('HorseRacing:persistence:pending'));
        Bus::assertDispatched(PersistHorseRacingMatches::class, fn ($job): bool => $job->connection === 'sports-realtime' && $job->queue === 'sports-sync-prematch');
    }

    public function test_private_odds_require_an_active_verified_customer(): void
    {
        $this->withoutMiddleware(CustomerAuthService::class);
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        require base_path('routes/channels.php');
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-sports.horseracing.odds.456', 'socket_id' => '123.456'])->assertOk();
        $customer->update(['trial_ends_at' => now()->subDay()]);
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-sports.horseracing.odds.456', 'socket_id' => '123.456'])->assertForbidden();
    }
}
