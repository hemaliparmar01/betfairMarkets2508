<?php

namespace Tests\Feature;

use Amp\CancelledException;
use Amp\TimeoutCancellation;
use Amp\Websocket\Client\WebsocketConnection;
use App\Console\Commands\ListenBetfairOdds;
use App\Console\Commands\ListenSportsApiPro;
use App\Events\FootballSportsScoreUpdated;
use App\Models\BasketballMatch;
use App\Services\MatchBackup;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SportsPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $database = config('database.connections.'.config('database.default'));
        $this->assertTrue(app()->environment('testing'));
        $this->assertTrue((config('database.default') === 'sqlite' && $database['database'] === ':memory:')
            || (config('database.default') === 'mysql' && str_starts_with($database['unix_socket'] ?? '', '/private/tmp/bfmarkets-sports-tests-') && $database['database'] === 'bfmarkets_testing'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $socket = getenv('BFMARKETS_TEST_REDIS_SOCKET');
        if (! $socket) {
            $this->markTestSkipped('Requires a disposable Redis Unix socket in BFMARKETS_TEST_REDIS_SOCKET.');
        }
        $this->assertStringStartsWith('/private/tmp/bfmarkets-sports-tests-', $socket);
        $this->assertFileExists($socket);
        $prefix = 'sports-test-'.bin2hex(random_bytes(8)).':';
        foreach (['default', 'cache'] as $connection) {
            config(['database.redis.'.$connection => ['url' => null, 'host' => $socket, 'port' => 0, 'database' => 0, 'password' => null, 'persistent' => false]]);
        }
        config(['database.redis.options.prefix' => $prefix, 'cache.prefix' => $prefix, 'cache.stores.redis.driver' => 'redis']);
        Redis::purge();
        Redis::purge('cache');
        Cache::purge('redis');
        Http::preventStrayRequests();
        Bus::fake();
        Event::fake();
    }

    /** @return array<string, array{string}> */
    public static function teamSports(): array
    {
        return array_combine(['Football', 'Cricket', 'IceHockey', 'AmericanFootball', 'Baseball', 'Basketball', 'Tennis'], array_map(static fn (string $sport): array => [$sport], ['Football', 'Cricket', 'IceHockey', 'AmericanFootball', 'Baseball', 'Basketball', 'Tennis']));
    }

    /** @return array<string, array{string}> */
    public static function racingSports(): array
    {
        return ['greyhounds' => ['Greyhounds'], 'horseracing' => ['HorseRacing']];
    }

    /** @return array<string, array{string}> */
    public static function allSports(): array
    {
        return self::teamSports() + self::racingSports();
    }

    /** @return array<string, mixed> */
    private function betfair(string $sport, array $changes = []): array
    {
        $name = ['Football' => 'soccer', 'IceHockey' => 'ice hockey', 'AmericanFootball' => 'american football', 'HorseRacing' => 'horse_racing'][$sport] ?? strtolower($sport);

        return array_replace(['sport' => $name, 'team1' => 'Home Alpha', 'team2' => 'Away Beta', 'tournament' => 'Test League', 'openTimestamp' => gmdate('c', time() + 60), 'markets' => [['marketId' => '456', 'marketType' => in_array($sport, ['HorseRacing', 'Greyhounds'], true) ? 'WIN' : 'MATCH_ODDS', 'runnerId' => '11', 'runner' => 'Home Alpha', 'sortPriority' => 1, 'handicap' => 0, 'backOdds' => 2.0, 'backSize' => 100, 'layOdds' => 2.02, 'laySize' => 100]]], $changes);
    }

    /** @return array<string, mixed> */
    private function sportsEvent(array $changes = []): array
    {
        return array_replace(['id' => 123, 'homeTeam' => ['name' => 'Home Alpha'], 'awayTeam' => ['name' => 'Away Beta'], 'startTimestamp' => time() + 60, 'tournament' => ['name' => 'Test League'], 'status' => ['code' => 0, 'type' => 'notstarted', 'description' => 'Not started'], 'homeScore' => ['current' => 0], 'awayScore' => ['current' => 0]], $changes);
    }

    private function sync(string $sport): object
    {
        return app('App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'SyncController');
    }

    private function mergeBetfair(string $sport, array $events, bool $live = false): void
    {
        if ($sport === 'Football') {
            (new ReflectionMethod(ListenBetfairOdds::class, 'updateFootballCache'))->invoke(app(ListenBetfairOdds::class), $events, $live);
        } else {
            $this->sync($sport)->mergeBetfairWebSocketData($events, $live);
        }
    }

    #[DataProvider('teamSports')]
    public function test_provider_payload_parsers_merge_by_utc_and_preserve_one_match_and_market(string $sport): void
    {
        $sync = $this->sync($sport);
        $betfair = new ReflectionMethod($sync, 'readBetFairData');
        $sports = new ReflectionMethod($sync, 'readsportApiData');
        $key = $sport.':matches';
        $fixture = $this->betfair($sport);
        $start = strtotime($fixture['openTimestamp']);
        $betfair->invoke($sync, ['liveMap' => ['456' => $fixture]], $key, false, 10);
        $this->assertCount(1, getInRedis($key));
        $this->assertFalse((bool) array_values(getInRedis($key))[0]['data_sync_complete']);

        $event = $this->sportsEvent(['startTimestamp' => $start]);
        $sports->invoke($sync, ['events' => [$event]], $key, false, 10);
        $sports->invoke($sync, ['events' => [$event, $event]], $key, false, 10);
        $matches = getInRedis($key);
        if ($sport === 'Football') {
            $sync->persistOddsSnapshots($matches);
        }
        $this->assertCount(1, $matches);
        $match = array_values($matches)[0];
        $this->assertSame('123', (string) $match['sportsApiPro_match_id']);
        $this->assertSame('456', (string) $match['betfair_match_id']);
        $this->assertSame($start, $match['timestamp']);
        $this->assertTrue($match['data_sync_complete']);
        $this->assertCount(1, $match['markets']);
        $model = 'App\\Models\\'.$sport.'Match';
        $this->assertSame(1, $model::query()->count());
        $this->assertSame((string) $match['sportsApiPro_match_id'], (string) $model::query()->first()->sports_api_pro_match_id);
        $before = Redis::get($key);
        $sports->invoke($sync, [], $key, false, 10);
        $this->assertSame($before, Redis::get($key));
    }

    #[DataProvider('teamSports')]
    public function test_sports_only_fixture_and_delayed_betfair_data_keep_existing_scores(string $sport): void
    {
        $sync = $this->sync($sport);
        $fixture = $this->sportsEvent();
        (new ReflectionMethod($sync, 'readsportApiData'))->invoke($sync, ['events' => [$fixture]], $sport.':matches', false, 10);
        $matches = getInRedis($sport.':matches');
        $this->assertCount(1, $matches);
        $this->assertFalse(array_values($matches)[0]['data_sync_complete']);
        $betfair = $this->betfair($sport, ['openTimestamp' => gmdate('c', $fixture['startTimestamp'])]);
        (new ReflectionMethod($sync, 'readBetFairData'))->invoke($sync, ['liveMap' => ['456' => $betfair]], $sport.':matches', false, 10);
        $match = array_values(getInRedis($sport.':matches'))[0];
        $this->assertSame('123', (string) $match['sportsApiPro_match_id']);
        $this->assertTrue($match['data_sync_complete']);
        $this->assertSame(0, $match['home_score']);
    }

    #[DataProvider('teamSports')]
    public function test_snapshot_update_duplicate_older_snapshot_and_delayed_finished_frames(string $sport): void
    {
        $sync = $this->sync($sport);
        $fixture = $this->sportsEvent();
        (new ReflectionMethod($sync, 'readsportApiData'))->invoke($sync, ['events' => [$fixture]], $sport.':matches', false, 10);
        if ($sport === 'Football') {
            $sync->persistOddsSnapshots(getInRedis($sport.':matches'));
        }
        $listener = app(ListenSportsApiPro::class);
        $config = (new ReflectionClass($listener))->getConstant('SPORTS')[strtolower($sport)];
        $socket = Mockery::mock(WebsocketConnection::class);
        $socket->shouldReceive('sendText')->andReturnNull();
        $subscriptions = ['123' => ['last_frame_at' => 0, 'last_reconciled_at' => 0, 'reconciling' => false]];
        $method = new ReflectionMethod($listener, 'processSportsFrame');
        $at = time();
        $states = [];
        foreach ([['snapshot', 2, 1, 'inprogress', 1], ['update', 2, 1, 'inprogress', 1], ['snapshot', 1, 0, 'notstarted', 0], ['update', 3, 2, 'finished', 100], ['update', 2, 1, 'inprogress', 1]] as [$type, $sequence, $score, $status, $code]) {
            if ($sport === 'Football' && $status === 'inprogress') {
                $code = 6;
            }
            $event = $this->sportsEvent(['homeScore' => ['current' => $score], 'status' => ['code' => $code, 'type' => $status], 'changes' => ['changeTimestamp' => $at + $sequence]]);
            $frame = ['type' => $type, 'channel' => 'match:123', 'timestamp' => $at + $sequence, 'data' => $event];
            $method->invokeArgs($listener, [strtolower($sport), $config, $frame, $socket, &$subscriptions]);
            $match = array_values(getInRedis($sport.':matches'))[0];
            $states[] = [$match['home_score'], $match['result_status']];
        }
        $this->assertSame([[1, 'Live'], [1, 'Live'], [1, 'Live'], [2, 'Finished'], [2, 'Finished']], $states);
        $this->assertSame('Finished', $match['result_status']);
        if ($sport === 'Football') {
            app(MatchBackup::class)->process('football');
        }
        $model = 'App\\Models\\'.$sport.'Match';
        $this->assertSame('Finished', $model::query()->first()->result_status);
    }

    #[DataProvider('racingSports')]
    public function test_racing_snapshots_odds_updates_and_closed_markets_do_not_resurrect(string $sport): void
    {
        $sync = $this->sync($sport);
        $fixture = $this->betfair($sport);
        $sync->mergeBetfairWebSocketData(['456' => $fixture], false, true);
        $this->assertCount(1, getInRedis($sport.':matches'));
        $fixture['markets'][0]['backOdds'] = 1.8;
        $sync->mergeBetfairWebSocketData(['456' => $fixture]);
        $match = array_values(getInRedis($sport.':matches'))[0];
        $this->assertSame(1.8, array_values(array_values($match['markets'])[0])[0]['currentBackOdds']);
        $sync->mergeBetfairWebSocketData(['456' => ['win_market_status' => 'CLOSED']]);
        $sync->mergeBetfairWebSocketData(['456' => $fixture]);
        $this->assertSame('Finished', array_values(getInRedis($sport.':matches'))[0]['result_status']);
        $model = 'App\\Models\\'.$sport.'Match';
        $this->assertSame('Finished', $model::query()->first()->result_status);
    }

    public function test_odds_buffer_preserves_distinct_updates_without_duplicate_quotes_or_handicap_collisions(): void
    {
        $listener = app(ListenBetfairOdds::class);
        $method = new ReflectionMethod($listener, 'bufferOddsEvent');
        $event = $this->betfair('Football');
        $pending = $method->invoke($listener, [], $event, 100.0);
        $pending = $method->invoke($listener, $pending, $event, 101.0);
        $this->assertCount(1, array_values($pending['_odds_updates'])[0]);
        $event['markets'][0]['backOdds'] = 1.8;
        $pending = $method->invoke($listener, $pending, $event, 102.0);
        $this->assertSame([2.0, 1.8], array_column(array_values($pending['_odds_updates'])[0], 'backOdds'));
        $event['markets'][0]['handicap'] = -1.5;
        $pending = $method->invoke($listener, $pending, $event, 103.0);
        $this->assertCount(2, $pending['_odds_updates']);
    }

    #[DataProvider('teamSports')]
    public function test_matching_normalizes_team_case_uses_utc_and_rejects_different_teams(string $sport): void
    {
        $sync = $this->sync($sport);
        $method = new ReflectionMethod($sync, 'checkIfRecordExistsOnAnotherApi');
        $start = strtotime('2026-10-30T18:00:00+05:30');
        $rows = ['known' => ['team1' => 'Home Alpha', 'team2' => 'Away Beta', 'timestamp' => $start, 'result_status' => 'Prematch']];
        $this->assertSame('known', $method->invoke($sync, 'different-key', 'HOME ALPHA', 'AWAY BETA', strtotime('2026-10-30T12:30:00Z'), $rows));
        $this->assertNull($method->invoke($sync, 'different-key', 'Unrelated Wolves', 'Unknown Eagles', $start, $rows));
        $this->assertNull($method->invoke($sync, 'different-key', 'Home Alpha', 'Away Beta', $start + 86400, $rows));
        $this->assertNull($method->invoke($sync, 'different-key', 'Home Alpha', 'Away Beta', $start, []));
        $normalize = new ReflectionMethod($sync, 'normalizeTeamName');
        $this->assertSame('saint_united_athletic', $normalize->invoke($sync, ' St. Útd Ath. '));
    }

    #[DataProvider('teamSports')]
    public function test_missing_markets_and_invalid_provider_rows_do_not_remove_an_existing_match(string $sport): void
    {
        $sync = $this->sync($sport);
        $method = new ReflectionMethod($sync, 'readBetFairData');
        $key = $sport.':matches';
        $fixture = $this->betfair($sport);
        $method->invoke($sync, ['liveMap' => ['456' => $fixture]], $key, false, 10);
        $this->mergeBetfair($sport, []);
        $fixture['markets'] = [];
        $method->invoke($sync, ['liveMap' => ['456' => $fixture]], $key, false, 10);
        $this->assertCount(1, getInRedis($key));
        $this->assertSame('456', (string) array_values(getInRedis($key))[0]['betfair_match_id']);
        $before = getInRedis($key);
        $method->invoke($sync, [], $key, false, 10);
        $this->assertSame(array_keys($before), array_keys(getInRedis($key)));
        $method->invoke($sync, ['liveMap' => ['invalid' => ['team1' => 'Incomplete']]], $key, false, 10);
        $this->assertSame(array_keys($before), array_keys(getInRedis($key)));
        foreach (['openTimestamp', 'team1', 'team2'] as $field) {
            $incomplete = $fixture;
            unset($incomplete[$field]);
            $method->invoke($sync, ['liveMap' => ['invalid' => $incomplete, '456' => $fixture]], $key, false, 10);
            $this->assertSame(array_keys($before), array_keys(getInRedis($key)), $field);
        }
    }

    #[DataProvider('teamSports')]
    public function test_provider_control_error_and_rate_limit_frames_leave_scores_unchanged(string $sport): void
    {
        $listener = app(ListenSportsApiPro::class);
        $config = (new ReflectionClass($listener))->getConstant('SPORTS')[strtolower($sport)];
        $key = $sport.':matches';
        $value = json_encode(['known' => ['sportsApiPro_match_id' => '123', 'home_score' => 2, 'away_score' => 1]], JSON_THROW_ON_ERROR);
        Redis::set($key, $value);
        $socket = Mockery::mock(WebsocketConnection::class);
        $socket->shouldNotReceive('sendText');
        $subscriptions = [];
        foreach (['error', 'rate_limit', 'subscribed', 'ping'] as $type) {
            (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, [strtolower($sport), $config, ['type' => $type, 'channel' => 'match:123', 'data' => ['code' => 429]], $socket, &$subscriptions]);
            $this->assertSame($value, Redis::get($key));
        }
        Event::assertNotDispatched($config['event']);
    }

    #[DataProvider('teamSports')]
    public function test_disconnect_recovery_is_queued_once_and_releases_its_lock_after_mocked_recovery(string $sport): void
    {
        $listener = app(ListenSportsApiPro::class);
        $config = (new ReflectionClass($listener))->getConstant('SPORTS')[strtolower($sport)];
        $controller = Mockery::mock($config['controller']);
        $controller->shouldReceive('syncSportsApiProLiveData')->once()->andReturn([]);
        $this->app->instance($config['controller'], $controller);
        $method = new ReflectionMethod($listener, 'queueLiveRecovery');
        $method->invoke($listener, strtolower($sport), $config, 'disconnect');
        $method->invoke($listener, strtolower($sport), $config, 'disconnect');
        Bus::assertDispatchedTimes(CallQueuedClosure::class, 1);
        $job = Bus::dispatched(CallQueuedClosure::class)->first();
        $this->assertSame('default', $job->queue);
        $this->assertNotNull(Cache::store('redis')->get($sport.':sports-api-pro-live-recovery'));
        $job->handle($this->app);
        Event::assertDispatched($config['event']);
        $lock = Cache::store('redis')->lock($sport.':sports-api-pro-recovery-pending', 3600);
        $this->assertTrue($lock->get());
        $lock->release();
        $method->invoke($listener, strtolower($sport), $config, 'disconnect');
        Bus::assertDispatchedTimes(CallQueuedClosure::class, 1);
    }

    #[DataProvider('allSports')]
    public function test_real_cache_keys_json_ttl_expiry_and_client_reconnection(string $sport): void
    {
        $key = $sport.':matches';
        $fixture = ['known' => ['unique_key' => 'known', 'sportsApiPro_match_id' => '123', 'timestamp' => time() + 60, 'result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'team1' => 'Home Alpha', 'team2' => 'Away Beta', 'markets' => []]];
        storeInRedis($key, $fixture, 1);
        $value = getInRedis($key);
        $this->assertSame('known', $value['known']['unique_key']);
        $this->assertSame([], $value['known']['markets']);
        $this->assertGreaterThan(0, Redis::ttl($key));
        $this->assertLessThanOrEqual(60, Redis::ttl($key));
        Redis::purge();
        $this->assertSame($value, getInRedis($key));
        Redis::expire($key, 0);
        $this->assertNull(getInRedis($key));
    }

    public function test_malformed_and_missing_cache_data_is_not_converted_into_matches(): void
    {
        $this->assertNull(getInRedis('Basketball:matches'));
        Redis::set('Basketball:matches', '{broken');
        $this->assertNull(getInRedis('Basketball:matches'));
        Redis::set('Basketball:matches', '[]');
        $this->assertSame([], getInRedis('Basketball:matches'));
    }

    private function enableRealQueues(): void
    {
        Bus::swap(new Dispatcher($this->app, fn (?string $connection) => $this->app['queue']->connection($connection)));
        Event::swap(new \Illuminate\Events\Dispatcher($this->app));
    }

    private function work(string $queue, string $connection = 'sports-realtime', int $tries = 3): void
    {
        $this->assertSame(0, Artisan::call('queue:work', ['connection' => $connection, '--queue' => $queue, '--once' => true, '--sleep' => 0, '--tries' => $tries, '--backoff' => 0]));
    }

    #[DataProvider('allSports')]
    public function test_real_realtime_queue_uniqueness_retries_and_reverb_transport_contract(string $sport): void
    {
        $this->enableRealQueues();
        $requests = [];
        $handler = HandlerStack::create(new MockHandler([new Response(200, [], '{}'), new Response(200, [], '{}')]));
        $handler->push(Middleware::history($requests));
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'isolated-key', 'broadcasting.connections.reverb.secret' => 'isolated-secret', 'broadcasting.connections.reverb.app_id' => 'isolated-app', 'broadcasting.connections.reverb.client_options.handler' => $handler]);
        Broadcast::purge('reverb');
        $class = 'App\\Events\\'.$sport.'SportsScoreUpdated';
        $event = new $class;
        $queue = $event->broadcastQueue;
        $this->assertSame('realtime-'.strtolower($sport), $queue);
        $this->assertSame('sports.'.strtolower($sport).'.live', $event->broadcastOn()[0]->name);
        $this->assertSame('score.updated', $event->broadcastAs());
        Broadcast::queue($event);
        Broadcast::queue(new $class);
        $this->assertSame(1, Queue::connection('sports-realtime')->size($queue));
        $job = Queue::connection('sports-realtime')->pop($queue);
        $payload = $job->payload();
        $this->assertSame(6, $payload['maxTries']);
        $this->assertSame(10, $payload['timeout']);
        $job->release(0);
        $this->work($queue);
        $this->assertSame(0, Queue::connection('sports-realtime')->size($queue));
        $this->assertCount(1, $requests);
        $sent = json_decode((string) $requests[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $sent = $sent['batch'][0] ?? $sent;
        $this->assertSame(['sports.'.strtolower($sport).'.live'], $sent['channels'] ?? [$sent['channel']]);
        $this->assertSame('score.updated', $sent['name']);
        $this->assertTrue(json_decode($sent['data'], true, flags: JSON_THROW_ON_ERROR)['refresh']);
        Broadcast::queue(new $class);
        $this->assertSame(1, Queue::connection('sports-realtime')->size($queue));
        $this->work($queue);
        $this->assertCount(2, $requests);
    }

    public function test_default_and_sports_image_jobs_run_on_the_existing_database_queue_names(): void
    {
        $this->enableRealQueues();
        foreach (['default', 'sports-images'] as $queue) {
            $key = 'executed:'.$queue;
            Queue::connection('database')->pushOn($queue, CallQueuedClosure::create(static function () use ($key): void {
                Cache::store('redis')->increment($key);
            }));
            $this->work($queue, 'database');
            $this->assertSame(1, (int) Cache::store('redis')->get($key));
            $this->assertSame(0, Queue::connection('database')->size($queue));
        }
    }

    public function test_job_exception_is_retried_then_recorded_in_failed_jobs_after_its_attempt_limit(): void
    {
        $this->enableRealQueues();
        config(['queue.failed.driver' => 'database-uuids']);
        Queue::connection('sports-realtime')->pushOn('realtime-football', CallQueuedClosure::create(static function (): void {
            Cache::store('redis')->increment('failed-attempts');
            throw new \RuntimeException('Isolated expected queue failure');
        }));
        $this->work('realtime-football', tries: 2);
        $this->assertSame(1, Queue::connection('sports-realtime')->size('realtime-football'));
        $this->work('realtime-football', tries: 2);
        $this->assertSame(2, (int) Cache::store('redis')->get('failed-attempts'));
        $this->assertSame(0, Queue::connection('sports-realtime')->size('realtime-football'));
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertStringContainsString('Isolated expected queue failure', DB::table('failed_jobs')->value('exception'));
    }

    public function test_reserved_job_recovers_after_worker_loss_and_backlog_keeps_fifo_order(): void
    {
        $this->enableRealQueues();
        $queue = Queue::connection('sports-realtime');
        for ($index = 0; $index < 10; $index++) {
            $queue->pushOn('realtime-football', CallQueuedClosure::create(static function () use ($index): void {
                Redis::rpush('processed-order', (string) $index);
            }));
        }
        $reserved = $queue->pop('realtime-football');
        $this->assertSame(1, $reserved->attempts());
        $this->travel(91)->seconds();
        $this->work('realtime-football');
        $this->assertSame(['1'], Redis::lrange('processed-order', 0, -1));
        for ($index = 1; $index < 10; $index++) {
            $this->work('realtime-football');
        }
        $this->assertSame(['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'], Redis::lrange('processed-order', 0, -1));
        $this->assertSame(0, $queue->size('realtime-football'));
        $this->travelBack();
    }

    public function test_odds_monitoring_rejects_invalid_prices_and_keeps_distinct_history_without_duplicate_growth(): void
    {
        $quote = $this->betfair('Football')['markets'][0];
        $first = updateOddsMonitoring($quote, [], 1000.0);
        foreach ([0, -1, 1, 'invalid', INF, NAN, null] as $invalid) {
            $updated = updateOddsMonitoring(array_replace($quote, ['backOdds' => $invalid]), $first, 1001.0);
            $this->assertSame(0.0, $updated['currentBackOdds']);
            $this->assertSame($first['backOddsHistory'], $updated['backOddsHistory']);
            $this->assertSame($first['initialBackOdds'], $updated['initialBackOdds']);
        }
        $updated = $first;
        for ($index = 1; $index <= 1000; $index++) {
            $updated = updateOddsMonitoring(array_replace($quote, ['backOdds' => $index % 2 ? 1.9 : 2.0]), $updated, 1000.0 + $index);
        }
        $this->assertCount(1001, $updated['backOddsHistory']);
        $history = $updated['backOddsHistory'];
        for ($index = 0; $index < 1000; $index++) {
            $updated = updateOddsMonitoring($quote, $updated, 2001.0 + $index);
        }
        $this->assertSame($history, $updated['backOddsHistory']);
    }

    #[DataProvider('teamSports')]
    public function test_betfair_deltas_preserve_opening_odds_and_unchanged_quotes_do_not_duplicate_history(string $sport): void
    {
        $sync = $this->sync($sport);
        $fixture = $this->betfair($sport);
        $key = $sport.':matches';
        (new ReflectionMethod($sync, 'readBetFairData'))->invoke($sync, ['liveMap' => ['456' => $fixture]], $key, false, 10);
        (new ReflectionMethod($sync, 'readsportApiData'))->invoke($sync, ['events' => [$this->sportsEvent(['startTimestamp' => strtotime($fixture['openTimestamp'])])]], $key, false, 10);
        foreach ([1.8, 1.8, 1.9] as $price) {
            $fixture['markets'][0]['backOdds'] = $price;
            $this->mergeBetfair($sport, ['456' => $fixture]);
        }
        $match = array_values(getInRedis($key))[0];
        $runner = array_values(array_values($match['markets'])[0])[0];
        $this->assertSame(2.0, (float) $runner['initialBackOdds']);
        $this->assertSame(1.9, $runner['currentBackOdds']);
        $this->assertSame([2.0, 1.8, 1.9], array_map('floatval', array_column($runner['backOddsHistory'], 'odds')));
    }

    /** @return array<string, string> */
    private function processEnvironment(): array
    {
        return ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sports-realtime', 'QUEUE_FAILED_DRIVER' => 'null', 'BROADCAST_CONNECTION' => 'null', 'REDIS_HOST' => getenv('BFMARKETS_TEST_REDIS_SOCKET'), 'REDIS_PORT' => '0', 'REDIS_URL' => '', 'REDIS_PASSWORD' => '', 'REDIS_USERNAME' => '', 'REDIS_DB' => '0', 'REDIS_CACHE_DB' => '0', 'REDIS_PREFIX' => config('database.redis.options.prefix'), 'CACHE_PREFIX' => config('cache.prefix'), 'HTTP_PROXY' => 'http://127.0.0.1:1', 'HTTPS_PROXY' => 'http://127.0.0.1:1', 'ALL_PROXY' => 'http://127.0.0.1:1', 'http_proxy' => 'http://127.0.0.1:1', 'https_proxy' => 'http://127.0.0.1:1', 'all_proxy' => 'http://127.0.0.1:1', 'NO_PROXY' => '', 'no_proxy' => ''];
    }

    public function test_real_redis_lock_serializes_two_independent_php_writers(): void
    {
        Redis::set('pipeline-counter', '0');
        $code = 'require '.var_export(base_path('vendor/autoload.php'), true).'; $app = require '.var_export(base_path('bootstrap/app.php'), true).'; $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();';
        $code .= <<<'PHP'
            for ($index = 0; $index < 25; $index++) {
                withRedisLock('pipeline-counter', static function (): void {
                    $before = (int) \Illuminate\Support\Facades\Redis::get('pipeline-counter');
                    usleep(1000);
                    \Illuminate\Support\Facades\Redis::set('pipeline-counter', (string) ($before + 1));
                });
            }
            PHP;
        $first = new Process([PHP_BINARY, '-r', $code], base_path(), $this->processEnvironment());
        $second = new Process([PHP_BINARY, '-r', $code], base_path(), $this->processEnvironment());
        $first->start();
        $second->start();
        $this->assertSame(0, $first->wait(), $first->getErrorOutput());
        $this->assertSame(0, $second->wait(), $second->getErrorOutput());
        $this->assertSame(50, (int) Redis::get('pipeline-counter'));
    }

    public function test_isolated_worker_timeout_stops_the_job_before_its_completion_side_effect(): void
    {
        if (! function_exists('pcntl_signal')) {
            $this->markTestSkipped('Worker timeout requires pcntl.');
        }
        $this->enableRealQueues();
        Queue::connection('sports-realtime')->pushOn('realtime-football', CallQueuedClosure::create(static function (): void {
            Cache::store('redis')->increment('timeout-started');
            sleep(3);
            Cache::store('redis')->put('timeout-finished', true, 60);
        }));
        $worker = new Process([PHP_BINARY, 'artisan', 'queue:work', 'sports-realtime', '--queue=realtime-football', '--sleep=0', '--timeout=1', '--tries=1', '--max-jobs=1', '--stop-when-empty'], base_path(), $this->processEnvironment());
        $worker->setTimeout(8);
        try {
            $worker->run();
        } catch (ProcessSignaledException $exception) {
            $this->assertSame(9, $worker->getTermSignal());
        }
        $this->assertSame(1, (int) Cache::store('redis')->get('timeout-started'), $worker->getErrorOutput());
        $this->assertNull(Cache::store('redis')->get('timeout-finished'));
        $this->assertFalse($worker->isSuccessful());
    }

    public function test_actual_reverb_receives_each_sport_once_and_resubscribes_after_disconnect(): void
    {
        $this->enableRealQueues();
        $portSocket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($portSocket, false), ':'), 1);
        fclose($portSocket);
        $environment = array_replace($this->processEnvironment(), ['REVERB_APP_ID' => 'isolated-app', 'REVERB_APP_KEY' => 'isolated-key', 'REVERB_APP_SECRET' => 'isolated-secret', 'REVERB_SCALING_ENABLED' => 'false']);
        $server = new Process([PHP_BINARY, 'artisan', 'reverb:start', '--host=127.0.0.1', '--port='.$port], base_path(), $environment);
        $server->start();
        try {
            $ready = false;
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $probe = @stream_socket_client('tcp://127.0.0.1:'.$port, $errorCode, $errorMessage, 0.05);
                if ($probe !== false) {
                    fclose($probe);
                    $ready = true;
                    break;
                }
                usleep(20000);
            }
            $this->assertTrue($ready, $server->getErrorOutput().$server->getOutput());
            config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'isolated-key', 'broadcasting.connections.reverb.secret' => 'isolated-secret', 'broadcasting.connections.reverb.app_id' => 'isolated-app', 'broadcasting.connections.reverb.options' => ['host' => '127.0.0.1', 'port' => $port, 'scheme' => 'http', 'useTLS' => false], 'broadcasting.connections.reverb.client_options' => ['proxy' => '', 'timeout' => 3]]);
            Broadcast::purge('reverb');
            foreach ([1, 2] as $connection) {
                $socket = \Amp\Websocket\Client\connect('ws://127.0.0.1:'.$port.'/app/isolated-key?protocol=7&client=js&version=8.6.0');
                $cancellation = new TimeoutCancellation(4);
                $greeting = json_decode($socket->receive($cancellation)->buffer($cancellation), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('pusher:connection_established', $greeting['event']);
                foreach (array_keys(MatchBackup::SPORTS) as $sport) {
                    $prefix = MatchBackup::SPORTS[$sport][0];
                    $channel = 'sports.'.$sport.'.live';
                    $subscription = json_encode(['event' => 'pusher:subscribe', 'data' => ['channel' => $channel]], JSON_THROW_ON_ERROR);
                    foreach ([1, 2] as $subscriptionAttempt) {
                        $socket->sendText($subscription);
                        $ack = json_decode($socket->receive($cancellation)->buffer($cancellation), true, flags: JSON_THROW_ON_ERROR);
                        $this->assertSame('pusher_internal:subscription_succeeded', $ack['event']);
                    }
                    $class = 'App\\Events\\'.$prefix.'SportsScoreUpdated';
                    Broadcast::queue(new $class);
                    $this->work('realtime-'.$sport);
                    $update = json_decode($socket->receive($cancellation)->buffer($cancellation), true, flags: JSON_THROW_ON_ERROR);
                    $this->assertSame($channel, $update['channel']);
                    $this->assertSame('score.updated', $update['event']);
                    $this->assertTrue(json_decode($update['data'], true, flags: JSON_THROW_ON_ERROR)['refresh']);
                }
                try {
                    $extra = $socket->receive(new TimeoutCancellation(0.05));
                    $this->fail('Duplicate subscription produced an unexpected extra message.');
                } catch (CancelledException $exception) {
                    $this->addToAssertionCount(1);
                }
                $socket->close();
            }
        } finally {
            $server->stop(2);
        }
    }

    public function test_database_timeline_rehydrates_real_redis_without_resetting_its_ttl(): void
    {
        $match = ['unique_key' => 'known', 'sportsApiPro_match_id' => '123', 'timestamp' => time() - 1200, 'current_period_start_timestamp' => time() - 1210, 'result_status' => 'Live', 'minutes_status' => '1st half', 'home_score' => 1, 'away_score' => 0, 'team1' => 'Home Alpha', 'team2' => 'Away Beta', 'markets' => []];
        Redis::setex('Football:matches', 120, json_encode(['known' => $match], JSON_THROW_ON_ERROR));
        $sync = $this->sync('Football');
        $card = ['id' => 'yellow-1', 'incidentType' => 'card', 'incidentClass' => 'yellow', 'time' => 23, 'timestamp' => 1793362200, 'isHome' => true];
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [['id' => 'goal-1', 'incidentType' => 'goal', 'time' => 10, 'isHome' => true, 'homeScore' => 1, 'awayScore' => 0], $card]], 'snapshot');
        $this->assertSame(1, DB::table('football_timeline')->where('match_id', '123')->count());
        $restored = getInRedis('Football:matches');
        $this->assertSame('goal-1', $restored['known']['timeline']['home_goals'][0]['id']);
        $this->assertSame(10, $restored['known']['timeline']['home_goals'][0]['minute']);
        $this->assertSame($restored, json_decode(Redis::get('Football:matches'), true, flags: JSON_THROW_ON_ERROR));
        $this->assertGreaterThan(0, Redis::ttl('Football:matches'));
        $this->assertLessThanOrEqual(120, Redis::ttl('Football:matches'));
        $this->assertSame('2026-10-30T12:10:00Z', $restored['known']['timeline']['home_yellow_cards'][0]['timestamp']);
        $listener = app(ListenSportsApiPro::class);
        $config = (new ReflectionClass($listener))->getConstant('SPORTS')['football'];
        $socket = Mockery::mock(WebsocketConnection::class);
        $subscriptions = ['123' => ['last_frame_at' => 0, 'last_reconciled_at' => 0, 'reconciling' => false]];
        $frame = ['type' => 'update', 'channel' => 'match:123:incidents', 'data' => ['incidents' => [array_replace($card, ['time' => null, 'timestamp' => null]), array_replace($card, ['id' => 'yellow-2', 'time' => null, 'timestamp' => null, 'isHome' => false])]]];
        (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $frame, $socket, &$subscriptions]);
        Event::assertDispatched(FootballSportsScoreUpdated::class);
        $updated = getInRedis('Football:matches')['known']['timeline'];
        $this->assertSame($restored['known']['timeline']['home_yellow_cards'], $updated['home_yellow_cards']);
        $this->assertNull($updated['away_yellow_cards'][0]['timestamp']);
        $this->assertTrue($updated['away_yellow_cards'][0]['time_estimated']);
        $this->assertSame(20, $updated['away_yellow_cards'][0]['minute']);
        $this->assertEquals($updated, json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
        $this->assertSame($updated, json_decode(Redis::get('Football:matches'), true)['known']['timeline']);
        $frame['data']['incidents'] = [array_replace($card, ['id' => 'yellow-2', 'time' => 17, 'isHome' => false])];
        (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $frame, $socket, &$subscriptions]);
        $updated = getInRedis('Football:matches')['known']['timeline'];
        $this->assertCount(1, $updated['away_yellow_cards']);
        $this->assertSame(17, $updated['away_yellow_cards'][0]['minute']);
        $this->assertFalse($updated['away_yellow_cards'][0]['time_estimated']);
        $this->assertEquals($updated, json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
    }

    public function test_tennis_interruption_and_cricket_innings_break_do_not_invent_elapsed_time_expiry(): void
    {
        foreach (['tennis' => 'Delayed', 'cricket' => 'Innings Break'] as $sport => $minutes) {
            $match = ['unique_key' => 'known', 'timestamp' => time() - 86400, 'result_status' => 'Live', 'minutes_status' => $minutes, 'home_score' => 2, 'away_score' => 1];
            $match = app(MatchBackup::class)->transition($match, $sport, time());
            $this->assertFalse(MatchBackup::isHidden($match, $sport));
            MatchBackup::observe($match, $sport, ['status' => ['type' => 'interrupted'], 'changes' => ['changeTimestamp' => time()]], 'match:123');
            $this->assertFalse($match['provider_lifecycle']['active']);
            $this->assertSame(2, $match['home_score']);
            $this->assertSame(1, $match['away_score']);
            $this->assertFalse(MatchBackup::isHidden($match, $sport));
        }
    }

    public function test_private_redis_outage_reconnect_and_database_market_recovery(): void
    {
        $binary = (new ExecutableFinder)->find('redis-server');
        if ($binary === null) {
            $this->markTestSkipped('Redis server executable is required for the disposable outage test.');
        }
        $sync = $this->sync('Basketball');
        $fixture = $this->betfair('Basketball');
        $key = 'Basketball:matches';
        (new ReflectionMethod($sync, 'readBetFairData'))->invoke($sync, ['liveMap' => ['456' => $fixture]], $key, false, 10);
        (new ReflectionMethod($sync, 'readsportApiData'))->invoke($sync, ['events' => [$this->sportsEvent(['startTimestamp' => strtotime($fixture['openTimestamp'])])]], $key, false, 10);
        $saved = getInRedis($key);
        $socket = dirname(getenv('BFMARKETS_TEST_REDIS_SOCKET')).'/outage-'.bin2hex(random_bytes(4)).'.sock';
        $server = null;
        try {
            foreach ([1, 2] as $cycle) {
                $server = new Process([$binary, '--port', '0', '--unixsocket', $socket, '--unixsocketperm', '700', '--save', '', '--appendonly', 'no', '--dir', dirname($socket)], base_path());
                $server->start();
                for ($attempt = 0; $attempt < 100 && ! file_exists($socket); $attempt++) {
                    usleep(10000);
                    clearstatcache(true, $socket);
                }
                $this->assertFileExists($socket, $server->getErrorOutput());
                foreach (['default', 'cache'] as $connection) {
                    config(['database.redis.'.$connection.'.host' => $socket]);
                    Redis::purge($connection);
                }
                Redis::swap(new RedisManager($this->app, config('database.redis.client'), config('database.redis')));
                Cache::purge('redis');
                if ($cycle === 1) {
                    storeInRedis($key, $saved, 1);
                    $this->assertSame($saved, getInRedis($key));
                    $server->stop(1);
                    try {
                        $unavailable = Redis::get($key);
                    } catch (\RedisException $exception) {
                        $unavailable = null;
                    }
                    $this->assertTrue($unavailable === false || $unavailable === null);
                } else {
                    $this->assertNull(getInRedis($key));
                    $sync->mergeBetfairWebSocketData(['456' => $fixture], false);
                    $restored = array_values(getInRedis($key))[0];
                    $this->assertSame('123', (string) $restored['sportsApiPro_match_id']);
                    $this->assertTrue($restored['data_sync_complete']);
                    $expected = array_values(array_values($saved[array_key_first($saved)]['markets'])[0])[0];
                    $actual = array_values(array_values($restored['markets'])[0])[0];
                    foreach (['marketId', 'runnerId', 'initialBackOdds', 'initialBackOddsTimestamp', 'currentBackOdds', 'backOddsHistory'] as $field) {
                        $this->assertEquals($expected[$field], $actual[$field], $field);
                    }
                    $this->assertSame(1, BasketballMatch::query()->count());
                }
            }
        } finally {
            $server?->stop(1);
        }
    }
}
