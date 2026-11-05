<?php

namespace Tests\Feature;

use Amp\CancelledException;
use Amp\TimeoutCancellation;
use Amp\Websocket\Client\WebsocketConnection;
use App\Console\Commands\ListenBetfairOdds;
use App\Console\Commands\ListenSportsApiPro;
use App\Events\CricketSportsScoreUpdated;
use App\Events\FootballSportsScoreUpdated;
use App\Http\Controllers\Users\Cricket\CricketSyncController;
use App\Http\Controllers\Users\Football\FootballController;
use App\Models\BasketballMatch;
use App\Models\Favorite;
use App\Services\CustomerAuthService;
use App\Services\MatchBackup;
use Database\Factories\CustomerFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
        config(['services.football_fixture_index' => false]);
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

    public function test_football_provider_name_changes_reuse_existing_ids_and_keys(): void
    {
        $sync = $this->sync('Football');
        $sports = new ReflectionMethod($sync, 'readsportApiData');
        $betfair = new ReflectionMethod($sync, 'readBetFairData');
        $event = $this->sportsEvent(['homeTeam' => ['name' => 'Red Star FC'], 'awayTeam' => ['name' => 'Nancy']]);
        $sports->invoke($sync, ['events' => [$event]], 'Football:matches', false, 10);
        $originalKey = array_key_first(getInRedis('Football:matches'));
        $betfairFixture = $this->betfair('Football', ['team1' => 'Red Star', 'team2' => 'Nancy', 'openTimestamp' => gmdate('c', $event['startTimestamp'])]);

        $betfair->invoke($sync, ['liveMap' => ['456' => $betfairFixture]], 'Football:matches', false, 10);

        $matches = getInRedis('Football:matches');
        $this->assertSame([$originalKey], array_keys($matches));
        $this->assertSame($originalKey, $matches[$originalKey]['unique_key']);
        $this->assertSame('456', (string) $matches[$originalKey]['betfair_match_id']);
        $renamed = array_replace($event, ['homeTeam' => ['name' => 'Completely Renamed Club'], 'startTimestamp' => $event['startTimestamp'] + 300]);
        $sports->invoke($sync, ['events' => [$renamed, $renamed]], 'Football:matches', false, 10);
        $matches = getInRedis('Football:matches');
        $this->assertSame([$originalKey], array_keys($matches));
        $this->assertCount(1, $matches[$originalKey]['markets']);
        $sync->persistOddsSnapshots($matches);
        $this->assertDatabaseCount('football_matches', 1);
        $this->assertDatabaseCount('football_markets', 1);

        $sports->invoke($sync, ['events' => [array_replace($event, ['id' => 124])]], 'Football:matches', false, 10);
        $this->assertCount(2, getInRedis('Football:matches'));
    }

    public function test_football_prematch_snapshots_preserve_live_state_and_true_prematch_hides_stale_events(): void
    {
        config(['services.football_fixture_index' => true]);
        $this->actingAs(CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]), 'customers');
        $sync = $this->sync('Football');
        $event = $this->sportsEvent();
        $sports = new ReflectionMethod($sync, 'readsportApiData');
        $betfair = new ReflectionMethod($sync, 'readBetFairData');
        $sports->invoke($sync, ['events' => [$event]], 'Football:matches', false, 10);
        $betfair->invoke($sync, ['liveMap' => ['456' => $this->betfair('Football', ['openTimestamp' => gmdate('c', $event['startTimestamp'])])]], 'Football:matches', false, 10);
        $listener = app(ListenSportsApiPro::class);
        (new ReflectionMethod($listener, 'updateFootballCache'))->invoke($listener, 'match:123', [
            'id' => 123, 'homeScore.current' => 0, 'awayScore.current' => 1, 'status.code' => 6, 'status.type' => 'inprogress',
            'time.currentPeriodStartTimestamp' => time() - 120, 'changes.changeTimestamp' => 10,
        ]);
        (new ReflectionMethod($listener, 'updateFootballCache'))->invoke($listener, 'match:123:incidents', ['incidents' => [
            ['id' => 'away-goal', 'incidentType' => 'goal', 'isHome' => false, 'time' => 2, 'homeScore' => 0, 'awayScore' => 1],
            ['id' => 'home-yellow', 'incidentType' => 'card', 'incidentClass' => 'yellow', 'isHome' => true, 'time' => 1],
        ]]);
        $live = array_values(getInRedis('Football:matches', readOnly: true))[0];
        $key = $live['unique_key'];
        $betfair->invoke($sync, ['liveMap' => ['456' => $this->betfair('Football', ['openTimestamp' => gmdate('c', $event['startTimestamp']), 'markets' => [array_replace($this->betfair('Football')['markets'][0], ['backOdds' => 1.8])]])]], 'Football:matches', false, 10);
        $afterOdds = getInRedis('Football:matches', readOnly: true)[$key];
        $this->assertSame('Live', $afterOdds['result_status']);
        $stale = array_replace($event, ['time' => ['currentPeriodStartTimestamp' => time() + 3600, 'injuryTime1' => 5]]);
        $sports->invoke($sync, ['events' => [$stale]], 'Football:matches', false, 10);
        $after = getInRedis('Football:matches', readOnly: true)[$key];
        $this->assertSame('Live', $after['result_status']);
        $this->assertSame('1st half', $after['minutes_status']);
        $this->assertSame([0, 1], [$after['home_score'], $after['away_score']]);
        $this->assertSame($live['current_period_start_timestamp'], $after['current_period_start_timestamp']);
        $this->assertSame(['away-goal'], array_column($after['timeline']['away_goals'], 'id'));
        $this->assertSame(1.8, array_values($after['markets']['456_MATCH_ODDS'])[0]['currentBackOdds']);
        $sync->persistOddsSnapshots([$key => $after]);
        $this->assertSame('Live', DB::table('football_matches')->where('sports_api_pro_match_id', '123')->value('result_status'));
        $this->assertEquals($after['timeline'], json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
        $request = Request::create('/', 'POST', ['type' => 'upcoming']);
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);
        $this->assertSame([], app(FootballController::class)->getFootballMatches());
        $request->merge(['type' => 'all']);
        $prematch = array_replace($after, ['result_status' => 'Prematch', 'minutes_status' => 'Prematch']);
        storeInRedis('Football:matches', [$key => $prematch], 10, false, replace: true);
        $storedTimeline = DB::table('football_timeline')->where('match_id', '123')->value('timeline');
        $groups = app(FootballController::class)->getFootballMatches();
        $shown = array_values($groups)[0]['matches'][0];
        $this->assertSame('-', $shown['score']);
        $this->assertSame([], $shown['timeline']['events']);
        $this->assertNull($shown['card_counts']['home']['yellow']);
        $this->assertNull($shown['corners']['home']);
        $this->assertSame($storedTimeline, DB::table('football_timeline')->where('match_id', '123')->value('timeline'));
        (new ReflectionMethod($listener, 'updateFootballCache'))->invoke($listener, 'match:123', ['status.type' => 'inprogress', 'changes.changeTimestamp' => 11]);
        $resumed = getInRedis('Football:matches', readOnly: true)[$key];
        $this->assertSame('Live', $resumed['result_status']);
        $this->assertSame('Live', $resumed['minutes_status']);
        $this->assertSame(['away-goal'], array_column($resumed['timeline']['away_goals'], 'id'));
        Http::assertNothingSent();
    }

    public function test_football_existing_duplicate_rows_merge_before_live_updates_and_stale_writes(): void
    {
        $sync = $this->sync('Football');
        $start = time() - 3700;
        $base = ['sportsApiPro_match_id' => 123, 'team2' => 'Nancy', 'timestamp' => $start, 'result_status' => 'Live', 'minutes_status' => '2nd half', 'home_score' => 1];
        $fresh = $base + ['unique_key' => 'sports-key', 'team1' => 'Red Star FC', 'away_score' => 3, 'websocket_change_timestamps' => ['match:123' => 20]];
        $old = $base + ['unique_key' => 'betfair-key', 'team1' => 'Red Star', 'betfair_match_id' => '456', 'away_score' => 2, 'websocket_change_timestamps' => ['match:123' => 10], 'markets' => ['456_MATCH_ODDS' => $this->betfair('Football')['markets']]];
        Redis::set('Football:matches', json_encode(['sports-key' => $fresh, 'betfair-key' => $old]));
        Redis::expire('Football:matches', 500);

        $matches = getInRedis('Football:matches');

        $this->assertSame(['betfair-key'], array_keys($matches));
        $this->assertSame(3, $matches['betfair-key']['away_score']);
        $this->assertSame('Red Star', $matches['betfair-key']['team1']);
        $this->assertCount(1, $matches['betfair-key']['markets']);
        $this->assertLessThanOrEqual(500, Redis::ttl('Football:matches'));
        $this->assertGreaterThan(490, Redis::ttl('Football:matches'));
        $this->assertCount(1, json_decode(Redis::get('Football:matches'), true));
        $listener = app(ListenSportsApiPro::class);
        (new ReflectionMethod($listener, 'updateFootballCache'))->invoke($listener, 'match:123', ['id' => 123, 'homeScore' => ['current' => 1], 'awayScore' => ['current' => 4], 'status' => ['code' => 7, 'type' => 'inprogress'], 'changes' => ['changeTimestamp' => 21]]);
        storeInRedis('Football:matches', ['sports-key' => $fresh], 10, false);
        $matches = getInRedis('Football:matches');
        $this->assertSame(['betfair-key'], array_keys($matches));
        $this->assertSame(4, $matches['betfair-key']['away_score']);
        $correction = array_replace($fresh, ['away_score' => 2, 'websocket_change_timestamps' => ['match:123' => 22]]);
        storeInRedis('Football:matches', ['sports-key' => $correction], 10, false);
        $matches = getInRedis('Football:matches');
        $this->assertSame(['betfair-key'], array_keys($matches));
        $this->assertSame(2, $matches['betfair-key']['away_score']);
        $sync->persistOddsSnapshots($matches);
        $this->assertDatabaseCount('football_matches', 1);
        $this->assertSame(2, json_decode(DB::table('football_matches')->value('payload'), true)['away_score']);
        Bus::assertDispatched(CallQueuedClosure::class, static fn (CallQueuedClosure $job): bool => $job->queue === 'sports-timeline');
        $matches['betfair-key']['result_status'] = 'Finished';
        storeInRedis('Football:matches', $matches, 10, false);
        storeInRedis('Football:matches', ['sports-key' => array_replace($fresh, ['websocket_change_timestamps' => ['match:123' => 30]])], 10, false);
        $finished = getInRedis('Football:matches');
        $this->assertSame(['betfair-key'], array_keys($finished));
        $this->assertSame('Finished', $finished['betfair-key']['result_status']);
        $this->assertSame(2, $finished['betfair-key']['away_score']);
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

    public function test_football_prematch_sync_restores_missing_future_database_fixtures_and_markets(): void
    {
        $sync = $this->sync('Football');
        $future = ['unique_key' => 'future-fixture', 'sportsApiPro_match_id' => 123, 'betfair_match_id' => '456', 'team1' => 'Home Alpha', 'team2' => 'Away Beta', 'tournament' => 'Test League', 'timestamp' => time() + 172800, 'result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'data_sync_complete' => true, 'markets' => ['456_MATCH_ODDS' => $this->betfair('Football')['markets']]];
        $sync->persistOddsSnapshots(['future-fixture' => $future]);
        Redis::set('Football:matches', '[]');
        $started = $this->sportsEvent(['id' => 999, 'startTimestamp' => time() - 60, 'status' => ['code' => 7, 'type' => 'inprogress']]);

        (new ReflectionMethod($sync, 'readsportApiData'))->invoke($sync, ['events' => [$started]], 'Football:matches', false, 30);

        $restored = getInRedis('Football:matches');
        $this->assertSame(['future-fixture'], array_keys($restored));
        $this->assertSame(123, (int) $restored['future-fixture']['sportsApiPro_match_id']);
        $this->assertSame('Prematch', $restored['future-fixture']['result_status']);
        $this->assertSame($future['timestamp'], $restored['future-fixture']['timestamp']);
        $this->assertSame(2.0, (float) $restored['future-fixture']['markets']['456_MATCH_ODDS'][0]['backOdds']);
        $this->assertGreaterThan(0, Redis::ttl('Football:matches'));
    }

    public function test_football_database_recovery_keeps_newer_cache_and_does_not_restore_provider_terminal_or_past_matches(): void
    {
        $sync = $this->sync('Football');
        $future = ['unique_key' => 'cached-live', 'sportsApiPro_match_id' => 123, 'team1' => 'Home Alpha', 'team2' => 'Away Beta', 'tournament' => 'Test League', 'timestamp' => time() + 3600, 'result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'markets' => []];
        $cancelled = array_replace($future, ['unique_key' => 'cancelled', 'sportsApiPro_match_id' => 456]);
        $past = array_replace($future, ['unique_key' => 'past', 'sportsApiPro_match_id' => 789, 'timestamp' => time() - 60]);
        $sync->persistOddsSnapshots(['cached-live' => $future, 'cancelled' => $cancelled, 'past' => $past]);
        $live = array_replace($future, ['result_status' => 'Live', 'minutes_status' => '1st half', 'home_score' => 1]);
        Redis::set('Football:matches', json_encode(['cached-live' => $live]));
        $event = $this->sportsEvent(['id' => 456, 'status' => ['code' => 70, 'type' => 'canceled']]);

        (new ReflectionMethod($sync, 'readsportApiData'))->invoke($sync, ['events' => [$event]], 'Football:matches', false, 30);

        $restored = getInRedis('Football:matches');
        $this->assertSame(['cached-live'], array_keys($restored));
        $this->assertSame('Live', $restored['cached-live']['result_status']);
        $this->assertSame(1, $restored['cached-live']['home_score']);
    }

    public function test_hidden_football_database_restore_keeps_market_backups_and_recovers_quotes_on_resume(): void
    {
        $sync = $this->sync('Football');
        $match = ['unique_key' => 'hidden-football', 'sportsApiPro_match_id' => 123, 'betfair_match_id' => '456', 'team1' => 'Home Alpha', 'team2' => 'Away Beta', 'tournament' => 'Test League', 'timestamp' => time() - 10800, 'result_status' => 'Live', 'minutes_status' => '2nd half', 'data_sync_complete' => true, 'home_score' => 0, 'away_score' => 0, 'internal_backup' => ['since' => time(), 'checks' => 0], 'markets' => ['456_MATCH_ODDS' => $this->betfair('Football')['markets']]];
        $match['markets']['456_MATCH_ODDS'][0] = updateOddsMonitoring($match['markets']['456_MATCH_ODDS'][0], [], time() - 60);
        $sync->persistOddsSnapshots(['hidden-football' => $match]);
        Redis::del('Football:matches');

        app(MatchBackup::class)->process('football');

        $restored = getInRedis('Football:matches')['hidden-football'];
        $this->assertArrayNotHasKey('markets', $restored);
        $this->assertSame(1, DB::table('football_markets')->count());
        $this->assertSame([], getVisibleSportsMatches('Football:matches'));
        $restored['home_score'] = 1;
        MatchBackup::observe($restored, 'football', ['status' => ['type' => 'inprogress'], 'changes' => ['changeTimestamp' => time()]], 'match:123');
        storeInRedis('Football:matches', ['hidden-football' => $restored], mergeLifecycle: false);
        $event = $this->betfair('Football');
        $event['markets'][0]['backOdds'] = 1.8;

        $this->mergeBetfair('Football', ['456' => $event], true);

        $resumed = getInRedis('Football:matches')['hidden-football'];
        $quote = array_values($resumed['markets']['456_MATCH_ODDS'])[0];
        $this->assertSame(1.8, $quote['backOdds']);
        $this->assertSame(2.0, (float) $quote['previousBackOdds']);
        $this->assertSame(1, $resumed['home_score']);
        $this->assertSame(1, DB::table('football_markets')->count());
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
        $path = $sport === 'HorseRacing' ? 'horseracing' : 'greyhounds';
        $controllerClass = 'App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'Controller';
        $request = Request::create('/', 'GET', ['type' => 'all']);
        $this->app->instance('request', $request);
        $groups = app($controllerClass)->{'get'.$sport.'Matches'}();
        $html = view('users.sports.'.$path.'.'.$path.'Details', ['data' => $groups])->render();
        preg_match_all('/<!--bf-fixture:([a-f0-9]{16}):([a-f0-9]{16})-->/', $html, $markers, PREG_SET_ORDER);
        $this->assertCount(1, $markers);
        $request->merge(['_fixture_versions' => array_column($markers, 2, 1)]);
        $this->assertStringContainsString('<!--bf-fixture-reuse-->', view('users.sports.'.$path.'.'.$path.'Details', ['data' => $groups])->render());

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

    #[DataProvider('allSports')]
    public function test_market_activity_maps_pink_to_back_interest_for_every_sport_without_swapping_quotes(string $sport): void
    {
        $fixture = $this->betfair($sport);
        $quote = $fixture['markets'][0];
        $fixture['markets'] = [];
        foreach ([[11, 899.69, 1300], [12, 2200, 194.87], [13, 1100, 77.76]] as [$id, $back, $lay]) {
            $fixture['markets'][] = array_replace($quote, ['runnerId' => (string) $id, 'runner' => 'Selection '.$id, 'backSize' => $back, 'laySize' => $lay]);
        }
        $sync = $this->sync($sport);
        if (in_array($sport, ['HorseRacing', 'Greyhounds'], true)) {
            $sync->mergeBetfairWebSocketData(['456' => $fixture], false, true);
        } else {
            (new ReflectionMethod($sync, 'readBetFairData'))->invoke($sync, ['liveMap' => ['456' => $fixture]], $sport.':matches', false, 10);
            (new ReflectionMethod($sync, 'readsportApiData'))->invoke($sync, ['events' => [$this->sportsEvent(['startTimestamp' => strtotime($fixture['openTimestamp'])])]], $sport.':matches', false, 10);
            $this->mergeBetfair($sport, ['456' => $fixture]);
        }
        $this->app->instance('request', Request::create('/', 'GET', ['type' => 'all']));
        $controller = app('App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'Controller');

        $rows = $controller->getMarketActivites();

        $this->assertCount(1, $rows);
        $indicator = $sport === 'Football' ? $rows[0] : $rows[0]['indicators'];
        $this->assertEqualsWithDelta(27.2443281, $indicator[$sport === 'Football' ? 'backPercent' : 'back_percent'], 0.001);
        $this->assertSame('lay', $indicator['balance']);
        $this->assertSame('low', $indicator['volatility']);
        $cached = array_values(getInRedis($sport.':matches'))[0];
        $runner = array_values(array_values($cached['markets'])[0])[0];
        $this->assertSame(899.69, (float) $runner['backSize']);
        $this->assertSame(1300.0, (float) $runner['laySize']);
        $this->assertSame(2.0, (float) $runner['backOdds']);
        $this->assertSame(2.02, (float) $runner['layOdds']);
        $this->assertNotEmpty($runner['liquidity_history']);

        foreach ($fixture['markets'] as &$runner) {
            $runner['backSize'] = 26;
            $runner['laySize'] = 74;
        }
        unset($runner);
        $this->mergeBetfair($sport, ['456' => $fixture]);
        $updated = $controller->getMarketActivites()[0];
        $indicator = $sport === 'Football' ? $updated : $updated['indicators'];
        $this->assertEqualsWithDelta(74.0, $indicator[$sport === 'Football' ? 'backPercent' : 'back_percent'], 0.001);
        $this->assertSame('back', $indicator['balance']);
    }

    public function test_betfair_book_sequences_reject_older_and_duplicate_deltas_but_allow_snapshot_chunks(): void
    {
        $listener = app(ListenBetfairOdds::class);
        $method = new ReflectionMethod($listener, 'recordRacingFeedHealth');
        $snapshot = ['type' => 'snapshot', 'feed' => 'live', 'meta' => ['seq' => 10]];
        $this->assertTrue($method->invoke($listener, $snapshot));
        $this->assertTrue($method->invoke($listener, $snapshot));
        $delta = ['type' => 'delta', 'feed' => 'live', 'meta' => ['seq' => 10, 'delta_seq' => 2]];
        $this->assertTrue($method->invoke($listener, $delta));
        $this->assertFalse($method->invoke($listener, $delta));
        $this->assertFalse($method->invoke($listener, array_replace($delta, ['meta' => ['seq' => 10, 'delta_seq' => 1]])));
        $this->assertFalse($method->invoke($listener, $snapshot));
        $this->assertTrue($method->invoke($listener, array_replace($snapshot, ['meta' => ['seq' => 11]])));
        $this->assertTrue($method->invoke($listener, array_replace($delta, ['feed' => 'prematch'])));
        $this->assertTrue($method->invoke($listener, ['type' => 'update', 'feed' => 'prematch', 'meta' => ['seq' => 10]]));
    }

    public function test_market_pressure_preserves_quiet_books_until_feed_stales_and_resets_after_reconnect_or_membership_changes(): void
    {
        $now = microtime(true);
        $listener = app(ListenBetfairOdds::class);
        (new \ReflectionProperty($listener, 'connectionStartedAt'))->setValue($listener, $now - 1000);
        $health = new ReflectionMethod($listener, 'recordRacingFeedHealth');
        $health->invoke($listener, ['type' => 'snapshot', 'feed' => 'prematch', 'meta' => ['seq' => 1]]);
        $buffer = new ReflectionMethod($listener, 'bufferOddsEvent');
        $fixture = $this->betfair('Football', ['_liquidity_session' => $now - 1000, '_liquidity_feed' => 'prematch']);
        $event = $buffer->invoke($listener, [], $fixture, $now - 960);
        $quote = $event['markets'][0];
        $runner = updateOddsMonitoring($quote, [], $now - 960);
        $match = ['result_status' => 'Prematch', 'markets' => ['market' => [$runner]]];

        $indicator = prepareMatchOddsMonitoring($match, true)['market_indicators']['market'];

        $this->assertFalse($indicator['stale']);
        $this->assertSame([60 => 0.0, 300 => 0.0, 900 => 0.0], $indicator['delta_windows']);
        $health->invoke($listener, ['type' => 'stale', 'feed' => 'prematch', 'stale' => true]);
        $indicator = prepareMatchOddsMonitoring($match, true)['market_indicators']['market'];
        $this->assertTrue($indicator['stale']);
        $this->assertSame('unknown', $indicator['momentum']);
        $this->assertNull($indicator['delta']);

        $quote['liquidity_session'] = $now;
        $reconnected = updateOddsMonitoring($quote, $runner, $now);
        $this->assertCount(1, $reconnected['liquidity_history']);
        $recent = updateOddsMonitoring($event['markets'][0], [], $now - 10);
        $reconnected = updateOddsMonitoring($quote, $recent, $now);
        $this->assertCount(2, $reconnected['liquidity_history']);
        $this->assertSame($now - 10, $reconnected['liquidity_history'][0]['timestamp']);
        $fixture['markets'][] = array_replace($fixture['markets'][0], ['runnerId' => '12']);
        $event = $buffer->invoke($listener, [], $fixture, $now);
        $changed = updateOddsMonitoring($event['markets'][0], $runner, $now);
        $this->assertCount(1, $changed['liquidity_history']);
        $this->assertNotSame($runner['liquidity_book'], $changed['liquidity_book']);
    }

    public function test_football_lock_contention_does_not_starve_other_sports_or_discard_pending_football_updates(): void
    {
        $listener = app(ListenBetfairOdds::class);
        $pending = ['live' => ['football' => $this->betfair('Football'), 'cricket' => $this->betfair('Cricket')]];
        $originalFootball = $pending['live']['football'];
        $cricket = Mockery::mock(CricketSyncController::class);
        $cricket->shouldReceive('mergeBetfairWebSocketData')->once()->with(['cricket' => $pending['live']['cricket']], true)->andReturn(true);
        $this->app->instance(CricketSyncController::class, $cricket);
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->twice()->andThrow(new LockTimeoutException);
        $store = Mockery::mock();
        $store->shouldReceive('lock')->with('Football:matches:write-lock', 30)->twice()->andReturn($lock);
        Cache::shouldReceive('store')->with('redis')->twice()->andReturn($store);
        $method = new ReflectionMethod($listener, 'flushPendingChanges');

        $this->assertFalse($method->invokeArgs($listener, [&$pending]));
        $this->assertSame(['football' => $originalFootball], $pending['live']);
        $this->assertFalse($method->invokeArgs($listener, [&$pending]));
        $this->assertSame(['football' => $originalFootball], $pending['live']);
        Event::assertDispatchedTimes(CricketSportsScoreUpdated::class, 1);
    }

    public function test_market_activity_page_renders_real_interest_and_rolling_window_definitions(): void
    {
        $customer = CustomerFactory::new()->create(['admin_free_days' => -1, 'active_session_id' => str_repeat('a', 40), 'timezone' => 'UTC']);
        $this->actingAs($customer, 'customers')->withCookie(config('session.cookie'), $customer->active_session_id)->withCredentials();
        $now = floor(microtime(true)) - 1;
        $runner = [];
        for ($offset = -920; $offset <= 0; $offset += 10) {
            $runner = updateOddsMonitoring(['marketId' => '456', 'marketType' => 'MATCH_ODDS', 'runnerId' => '11', 'runner' => 'Porto', 'name' => 'Match Odds', 'backOdds' => 1.88, 'layOdds' => 1.90, 'backSize' => $offset <= -60 ? 60 : 26, 'laySize' => $offset <= -60 ? 40 : 74], $runner, $now + $offset);
        }
        storeInRedis('Football:matches', ['pressure-preview' => ['unique_key' => 'pressure-preview', 'sportsApiPro_match_id' => 123, 'betfair_match_id' => '456', 'team1' => 'Porto', 'team2' => 'Napoli', 'tournament' => 'Test League', 'timestamp' => time() + 3600, 'result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'data_sync_complete' => true, 'markets' => ['456_MATCH_ODDS' => [$runner]]]], 10, false);

        $response = $this->get('/football/market-activity');

        $response->assertSee('Porto vs Napoli')->assertSee('+34.0 pp')->assertSee('Back ↑')->assertSee('Change in Back interest over 5 minutes, in percentage points');
        $response->assertSee('"backPercent":74', false)->assertSee('"layPercent":26', false);
        if ($directory = getenv('BFMARKETS_MARKET_PREVIEW_DIR')) {
            $this->assertStringStartsWith('/private/tmp/bfmarkets-market-preview-', $directory);
            file_put_contents($directory.'/index.html', $response->getContent());
        }
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
        $this->assertSame('sports-recovery', $job->queue);
        $this->assertSame('sports-realtime', $job->connection);
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

    public function test_reusing_a_verified_cache_snapshot_preserves_the_full_write_and_ttl(): void
    {
        $snapshot = ['unchanged' => ['score' => 1], 'updated' => ['score' => 0, 'markets' => ['back' => 2.0]]];
        $updates = ['updated' => ['score' => 2, 'markets' => ['back' => 1.8]]];
        Redis::set('snapshot-original', json_encode($snapshot));
        Redis::set('snapshot-reused', json_encode($snapshot));

        storeInRedis('snapshot-original', $updates, 3, false);
        storeInRedis('snapshot-reused', $updates, 3, false, $snapshot);

        $this->assertSame(Redis::get('snapshot-original'), Redis::get('snapshot-reused'));
        $this->assertSame(['unchanged' => ['score' => 1], 'updated' => ['score' => 2, 'markets' => ['back' => 1.8]]], json_decode(Redis::get('snapshot-reused'), true));
        $this->assertGreaterThan(0, Redis::ttl('snapshot-reused'));
        $this->assertLessThanOrEqual(180, Redis::ttl('snapshot-reused'));
    }

    public function test_reusing_a_cache_snapshot_reads_newer_data_and_handles_expiry(): void
    {
        $old = ['unchanged' => ['score' => 0]];
        $fresh = ['unchanged' => ['score' => 3], 'new-match' => ['score' => 1]];
        Redis::set('snapshot-race', json_encode($fresh));

        storeInRedis('snapshot-race', ['changed' => ['score' => 2]], 3, false, $old);

        $this->assertSame($fresh + ['changed' => ['score' => 2]], json_decode(Redis::get('snapshot-race'), true));
        storeInRedis('snapshot-missing', ['changed' => ['score' => 2]], 3, false, $old);
        $this->assertSame(['changed' => ['score' => 2]], json_decode(Redis::get('snapshot-missing'), true));
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
        $scoreFrame = ['type' => 'update', 'channel' => 'match:123', 'data' => ['id' => 123, 'homeScore.current' => 2, 'awayScore.current' => 0]];
        (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $scoreFrame, $socket, &$subscriptions]);
        $scored = getInRedis('Football:matches')['known'];
        $this->assertCount(2, $scored['timeline']['home_goals']);
        $this->assertSame(2, $scored['home_score']);
        $this->assertSame([10, 20], array_column($scored['timeline']['home_goals'], 'minute'));
        $this->assertTrue($scored['timeline']['home_goals'][1]['time_known']);
        $this->assertTrue($scored['timeline']['home_goals'][1]['time_estimated']);
        $this->assertSame($scored['timeline'], json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
        $withoutTimeline = $scored;
        unset($withoutTimeline['timeline']);
        Redis::setex('Football:matches', 120, json_encode(['known' => $withoutTimeline], JSON_THROW_ON_ERROR));
        $this->assertSame($scored['timeline'], getInRedis('Football:matches')['known']['timeline']);
        $goalFrame = ['type' => 'update', 'channel' => 'match:123:incidents', 'data' => ['incidents' => [['id' => 'goal-2', 'incidentType' => 'goal', 'time' => 19, 'isHome' => true, 'homeScore' => 2, 'awayScore' => 0]]]];
        (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $goalFrame, $socket, &$subscriptions]);
        $confirmed = getInRedis('Football:matches')['known'];
        $this->assertSame(['goal-1', 'goal-2'], array_column($confirmed['timeline']['home_goals'], 'id'));
        $this->assertSame(19, $confirmed['timeline']['home_goals'][1]['minute']);
        $this->assertFalse($confirmed['timeline']['home_goals'][1]['time_estimated']);
        $this->assertSame($confirmed['timeline'], json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
        $historyFrame = ['type' => 'snapshot', 'channel' => 'match:123:incidents', 'data' => ['incidents' => [
            ['incidentType' => 'period', 'text' => 'Second half', 'isLive' => true, 'homeScore' => 2, 'awayScore' => 0],
            ['id' => 'goal-1', 'incidentType' => 'goal', 'time' => 10, 'isHome' => true, 'homeScore' => 1, 'awayScore' => 0],
            $goalFrame['data']['incidents'][0],
            array_replace($card, ['id' => 'replacement-home', 'rescinded' => false]),
            array_replace($card, ['id' => 'replacement-away', 'time' => 17, 'isHome' => false, 'rescinded' => false]),
        ], 'home' => [], 'away' => []]];
        (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $historyFrame, $socket, &$subscriptions]);
        $reconciled = getInRedis('Football:matches')['known'];
        $this->assertSame(['replacement-home'], array_column($reconciled['timeline']['home_yellow_cards'], 'id'));
        $this->assertSame(['replacement-away'], array_column($reconciled['timeline']['away_yellow_cards'], 'id'));
        $this->assertSame($reconciled['timeline'], json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
        $this->assertSame($reconciled['timeline'], json_decode(Redis::get('Football:matches'), true)['known']['timeline']);
        (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $historyFrame, $socket, &$subscriptions]);
        $this->assertSame($reconciled['timeline'], getInRedis('Football:matches')['known']['timeline']);
        unset($reconciled['timeline']);
        Redis::setex('Football:matches', 120, json_encode(['known' => $reconciled], JSON_THROW_ON_ERROR));
        $rehydrated = getInRedis('Football:matches')['known']['timeline'];
        $this->assertSame(['replacement-home'], array_column($rehydrated['home_yellow_cards'], 'id'));
        $this->assertSame(['replacement-away'], array_column($rehydrated['away_yellow_cards'], 'id'));
        $this->assertGreaterThan(0, Redis::ttl('Football:matches'));
        $this->assertLessThanOrEqual(120, Redis::ttl('Football:matches'));

        config(['services.football_fixture_index' => true]);
        storeInRedis('Football:matches', ['known' => $reconciled], 2, false, replace: true);
        $cached = Redis::get('Football:matches');
        $index = getInRedis('Football:matches', fixtureIndex: true);
        $manager = Redis::getFacadeRoot();
        Redis::shouldReceive('get')->with('Football:matches')->never();
        Redis::shouldReceive('get')->with('Football:matches:fixtures')->andReturn(json_encode($index));
        Redis::shouldReceive('getrange')->andReturnUsing(static fn (...$arguments): mixed => $manager->getrange(...$arguments));
        Redis::shouldReceive('eval')->once()->withArgs(static fn (string $script, mixed ...$arguments): bool => ! str_contains($script, '"SET"'))->andReturnUsing(static fn (...$arguments): mixed => $manager->eval(...$arguments));
        try {
            $selected = getInRedis('Football:matches', matchIds: ['123']);
            $this->assertSame(['known'], array_keys($selected));
            $this->assertSame($rehydrated, $selected['known']['timeline']);
        } finally {
            Redis::swap($manager);
        }
        $this->assertSame($cached, Redis::get('Football:matches'));
        $this->assertGreaterThan(0, Redis::ttl('Football:matches'));
    }

    public function test_fixture_timeline_reads_are_page_scoped_without_row_locks_or_cache_writes(): void
    {
        $sync = $this->sync('Football');
        $matches = [];
        for ($id = 101; $id <= 124; $id++) {
            $match = ['unique_key' => 'match-'.$id, 'sportsApiPro_match_id' => (string) $id, 'timestamp' => time() - 600 + $id,
                'result_status' => 'Live', 'minutes_status' => '1st half', 'home_score' => 0, 'away_score' => 0,
                'tournament' => 'Test League', 'team1' => 'Home', 'team2' => 'Away', 'markets' => [], 'timeline' => ['initialized' => true]];
            $sync->storeTimeline($match, frameType: 'persist');
            $matches[$match['unique_key']] = $match;
        }
        $encoded = json_encode($matches);
        Redis::setex('Football:matches', 120, $encoded);
        $this->actingAs(CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]), 'customers');
        $request = Request::create('/', 'POST', ['type' => 'live', 'page' => 2, 'per_page' => 3]);
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();

        $groups = app(FootballController::class)->getFootballMatches();

        $queries = array_values(array_filter(DB::connection()->getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'football_timeline')));
        $this->assertCount(1, $queries);
        $this->assertSame(['104', '105', '106'], $queries[0]['bindings']);
        $this->assertStringStartsWith('select', $queries[0]['query']);
        $this->assertStringNotContainsString('for update', $queries[0]['query']);
        $this->assertSame(['104', '105', '106'], array_column(array_values($groups)[0]['matches'], 'sportsApiPro_match_id'));
        $this->assertSame($encoded, Redis::get('Football:matches'));
        $this->assertGreaterThan(100, Redis::ttl('Football:matches'));
        Bus::assertNotDispatched(CallQueuedClosure::class);
        $closed = view('users.sports.football.footballDetails', ['data' => $groups])->render();
        $this->assertStringNotContainsString('data-statistics-content', $closed);
        $request->merge(['_statistics_match_id' => '104']);
        $opened = view('users.sports.football.footballDetails', ['data' => $groups])->render();
        $this->assertSame(1, substr_count($opened, 'data-statistics-content'));
        $this->assertGreaterThan(strlen($closed), strlen($opened));
    }

    public function test_fixture_byte_index_preserves_legacy_json_and_never_gets_the_whole_blob_for_selected_matches(): void
    {
        config(['services.football_fixture_index' => true]);
        $data = ['quoted"key/}' => ['unique_key' => 'quoted"key/}', 'sportsApiPro_match_id' => '123', 'team1' => 'თბილისი / "Home"', 'team2' => 'Away', 'timestamp' => time(), 'tournament' => 'Test', 'markets' => []],
            'large' => ['unique_key' => 'large', 'sportsApiPro_match_id' => '124', 'timestamp' => time(), 'tournament' => 'Test', 'markets' => ['456_MATCH_ODDS' => [['backOddsHistory' => array_fill(0, 4000, ['timestamp' => 1700000000, 'odds' => 2.15])]]]]];
        storeInRedis('Football:matches', $data, 2, false, replace: true);
        $this->assertSame($data, json_decode(Redis::get('Football:matches'), true));
        $this->assertSame(Redis::ttl('Football:matches'), Redis::ttl('Football:matches:fixtures'));
        $index = getInRedis('Football:matches', fixtureIndex: true);
        $this->assertCount(2, $index['matches']);
        $this->assertLessThan(strlen(Redis::get('Football:matches')) / 10, strlen(json_encode($index)));
        $manager = Redis::getFacadeRoot();
        Redis::shouldReceive('get')->with('Football:matches')->never();
        Redis::shouldReceive('get')->with('Football:matches:fixtures')->andReturn(json_encode($index));
        Redis::shouldReceive('getrange')->andReturnUsing(static fn (...$arguments): mixed => $manager->getrange(...$arguments));
        Redis::shouldReceive('eval')->andReturnUsing(static fn (...$arguments): mixed => $manager->eval(...$arguments));
        try {
            $selected = getInRedis('Football:matches', readOnly: true, matchIds: ['123']);
            $this->assertSame(['quoted"key/}' => $data['quoted"key/}']], $selected);
        } finally {
            Redis::swap($manager);
        }
        Redis::set('Football:matches', json_encode(['new' => array_replace($data['quoted"key/}'], ['unique_key' => 'new', 'team1' => 'Corrected'])]));
        $this->assertNull(getInRedis('Football:matches', fixtureIndex: true));
        $this->assertNull(getInRedis('Football:matches', readOnly: true, matchIds: ['123'], snapshot: $index));
        $this->assertSame('Corrected', getInRedis('Football:matches', readOnly: true, matchIds: ['123'])['new']['team1']);
        storeInRedis('Football:matches', array_values($data), 2, false, replace: true);
        $this->assertSame([0 => $data['quoted"key/}']], getInRedis('Football:matches', readOnly: true, matchIds: ['123']));
        $unchanged = Redis::get('Football:matches');
        storeInRedis('Football:matches', array_values($data), 5, false, replace: true);
        $this->assertSame($unchanged, Redis::get('Football:matches'));
        $this->assertGreaterThanOrEqual(299, Redis::ttl('Football:matches'));
        $index = getInRedis('Football:matches', fixtureIndex: true);
        $index['matches'][0]['result_status'] = 'Finished';
        Redis::set('Football:matches:fixtures', json_encode($index));
        $this->assertNull(getInRedis('Football:matches', fixtureIndex: true));
        storeInRedis('Football:matches', array_values($data), 2, false, replace: true);
        $this->assertArrayNotHasKey('result_status', getInRedis('Football:matches', fixtureIndex: true)['matches'][0]);
        Redis::del('Football:matches');
        $this->assertNull(getInRedis('Football:matches', fixtureIndex: true));
    }

    public function test_provider_and_backup_writers_keep_the_fixture_index_current_without_changing_match_identity(): void
    {
        config(['services.football_fixture_index' => true]);
        $sync = $this->sync('Football');
        $event = $this->sportsEvent();
        (new ReflectionMethod($sync, 'readsportApiData'))->invoke($sync, ['events' => [$event]], 'Football:matches', false, 10);
        $index = getInRedis('Football:matches', fixtureIndex: true);
        $this->assertNotNull($index);
        $key = $index['ids']['123'];
        (new ReflectionMethod($sync, 'readBetFairData'))->invoke($sync, ['liveMap' => ['456' => $this->betfair('Football')]], 'Football:matches', false, 10);
        $index = getInRedis('Football:matches', fixtureIndex: true);
        $this->assertSame($key, $index['ids']['123']);
        $selected = getInRedis('Football:matches', readOnly: true, matchIds: ['123']);
        $this->assertSame('456', (string) $selected[$key]['betfair_match_id']);
        $this->assertNotEmpty($selected[$key]['markets']);
        $this->assertSame($selected, getInRedis('Football:matches', readOnly: true));
        $sync->persistOddsSnapshots($selected);
        app(MatchBackup::class)->process('football');
        $after = getInRedis('Football:matches', fixtureIndex: true);
        $this->assertSame($key, $after['ids']['123']);
        $this->assertSame(Redis::ttl('Football:matches'), Redis::ttl('Football:matches:fixtures'));
        Http::assertNothingSent();
    }

    public function test_indexed_fixture_filters_pagination_and_time_boundaries_match_the_legacy_path(): void
    {
        $this->freezeTime();
        $this->actingAs(CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]), 'customers');
        $base = ['unique_key' => 'base', 'sportsApiPro_match_id' => '100', 'timestamp' => time() - 1800, 'result_status' => 'Live',
            'minutes_status' => '1st half', 'home_score' => 0, 'away_score' => 0, 'team1' => 'Home', 'team2' => 'Away',
            'tournament' => 'Premier League', 'tournament_country' => 'England', 'tournament_id' => 'eng', 'markets' => [], 'timeline' => ['initialized' => true]];
        $runner = updateOddsMonitoring(['backOdds' => 2.0, 'layOdds' => 2.02, 'backSize' => 20, 'laySize' => 50], [], microtime(true));
        $cases = [[], ['minutes_status' => 'Half Time'], ['minutes_status' => '2nd half', 'current_period_start_timestamp' => time() - 1800],
            ['home_score' => 1, 'away_score' => 2, 'bet365_prematch' => ['favorite_side' => 'home', 'favorite_odds' => 1.8]],
            ['result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'timestamp' => time() + 600, 'markets' => ['m' => [array_replace($runner, ['prematch_drop_triggered' => true, 'market_shock_detected_at' => time()])]]],
            ['result_status' => 'Finished'], ['tournament_country' => 'Georgia', 'tournament_id' => 'geo'],
            ['timestamp' => time() - 86400, 'internal_backup' => ['since' => time()]],
            ['timestamp' => time() - 8000, 'provider_lifecycle' => ['active' => true, 'resumed_at' => time(), 'meaningful_at' => time()]]];
        $matches = [];
        foreach ($cases as $number => $changes) {
            $match = array_replace($base, $changes, ['unique_key' => 'm'.$number, 'sportsApiPro_match_id' => (string) (100 + $number)]);
            $matches[$match['unique_key']] = $match;
        }
        config(['services.football_fixture_index' => true]);
        storeInRedis('Football:matches', $matches, 30, false, replace: true);
        Favorite::query()->insert(['favorite_key' => hash('sha256', 'fixture-favorite'), 'customer_id' => auth('customers')->id(), 'sport' => 'football', 'event_key' => 'm3', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([0, 1260] as $offset) {
            if ($offset > 0) {
                $aged = $matches;
                $aged['m4']['markets']['m'][0]['market_shock_detected_at'] -= $offset;
                $aged['m8']['provider_lifecycle']['meaningful_at'] -= $offset;
                storeInRedis('Football:matches', $aged, 30, false, replace: true);
            }
            foreach (['all', 'by_time', 'live', 'upcoming', 'finished', 'favorites', 'ht00', '00_70', 'fav_losing', 'fav_not_winning', 'prematch_drops', 'market_shock'] as $type) {
                foreach ([1, 2, 3] as $page) {
                    $request = Request::create('/', 'POST', ['type' => $type, 'page' => $page, 'per_page' => 2]);
                    $request->setLaravelSession($this->app['session']->driver());
                    $this->app->instance('request', $request);
                    $controller = app(FootballController::class);
                    config(['services.football_fixture_index' => false]);
                    $legacy = $controller->getFootballMatches();
                    config(['services.football_fixture_index' => true]);
                    $indexed = $controller->getFootballMatches();
                    foreach ($indexed as &$group) {
                        foreach ($group['matches'] as &$event) {
                            $event['_fixture_data_version'] = null;
                        }
                        unset($event);
                    }
                    unset($group);
                    $this->assertSame($legacy, $indexed, $type.' page '.$page.' after '.$offset.'s');
                }
            }
        }
        Http::assertNothingSent();
    }

    public function test_indexed_refresh_reads_only_changed_rows_and_keeps_personalized_versions(): void
    {
        config(['services.football_fixture_index' => true]);
        $this->freezeTime();
        $base = ['unique_key' => 'm1', 'sportsApiPro_match_id' => '123', 'timestamp' => time() + 600, 'result_status' => 'Prematch', 'minutes_status' => 'Prematch',
            'team1' => 'Home', 'team2' => 'Away', 'tournament' => 'Test League', 'tournament_logo' => '/league.png', 'home_score' => 0, 'away_score' => 0, 'timeline' => ['initialized' => true]];
        $matches = ['m1' => $base, 'm2' => array_replace($base, ['unique_key' => 'm2', 'sportsApiPro_match_id' => '124'])];
        storeInRedis('Football:matches', $matches, 10, false, replace: true);
        $request = Request::create('/', 'POST', ['type' => 'all']);
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);
        $controller = app(FootballController::class);
        $groups = $controller->getFootballMatches();
        $html = view('users.sports.football.footballDetails', ['data' => $groups])->render();
        preg_match_all('/<!--bf-fixture:([a-f0-9]+):([a-f0-9]+)-->/u', $html, $markers, PREG_SET_ORDER);
        $this->assertCount(2, $markers);
        $known = array_column($markers, 2, 1);
        $versions = array_column(array_values($groups)[0]['matches'], '_fixture_data_version', 'unique_key');
        $request->merge(['_fixture_versions' => $known, '_fixture_data_versions' => $versions]);

        $unchanged = $controller->getFootballMatches();

        $this->assertSame([true, true], array_column(array_values($unchanged)[0]['matches'], '_fixture_reuse'));
        $reuseHtml = view('users.sports.football.footballDetails', ['data' => $unchanged])->render();
        $this->assertSame(2, substr_count($reuseHtml, '<!--bf-fixture-reuse-->'));
        $this->assertStringNotContainsString('class="event-row', $reuseHtml);
        $matches['m1']['home_score'] = 1;
        storeInRedis('Football:matches', $matches, 10, false, replace: true);
        $updated = $controller->getFootballMatches();
        $events = array_values($updated)[0]['matches'];
        $this->assertSame(1, $events[0]['home_score']);
        $this->assertTrue($events[1]['_fixture_reuse']);
        $this->assertArrayNotHasKey('team1', $events[1]);
        app()->setLocale('ka');
        $translated = $controller->getFootballMatches();
        $this->assertSame(['Home', 'Home'], array_column(array_values($translated)[0]['matches'], 'team1'));
        $manager = Redis::getFacadeRoot();
        $races = 0;
        Redis::shouldReceive('get')->andReturnUsing(static fn (...$arguments): mixed => $manager->get(...$arguments));
        Redis::shouldReceive('getrange')->andReturnUsing(static fn (...$arguments): mixed => $manager->getrange(...$arguments));
        Redis::shouldReceive('eval')->andReturnUsing(static function (string $script, mixed ...$arguments) use ($manager, &$races): mixed {
            if (str_contains($script, 'local rows = {}')) {
                $races++;

                return false;
            }

            return $manager->eval($script, ...$arguments);
        });
        try {
            $fallback = $controller->getFootballMatches();
            $this->assertSame(2, $races);
            $this->assertTrue($request->attributes->get('football_fixture_index_disabled'));
            $this->assertSame([1, 0], array_column(array_values($fallback)[0]['matches'], 'home_score'));
            $this->assertArrayNotHasKey('_fixture_reuse', array_values($fallback)[0]['matches'][0]);
        } finally {
            Redis::swap($manager);
        }
        Http::assertNothingSent();
    }

    public function test_fixture_index_benchmark_reports_real_latency_memory_and_equivalent_rows(): void
    {
        $base = ['unique_key' => 'm0', 'sportsApiPro_match_id' => '1000', 'timestamp' => time() + 600, 'result_status' => 'Prematch', 'minutes_status' => 'Prematch',
            'team1' => 'Home', 'team2' => 'Away', 'tournament' => 'Test League', 'home_score' => 0, 'away_score' => 0, 'timeline' => ['initialized' => true], 'markets' => []];
        $history = array_fill(0, 80, ['timestamp' => 1700000000, 'odds' => 2.15]);
        for ($market = 0; $market < 6; $market++) {
            $base['markets']['market-'.$market] = array_fill(0, 3, ['backOdds' => 2.15, 'backOddsHistory' => $history]);
        }
        $matches = [];
        for ($number = 0; $number < 160; $number++) {
            $matches['m'.$number] = array_replace($base, ['unique_key' => 'm'.$number, 'sportsApiPro_match_id' => (string) (1000 + $number)]);
        }
        config(['services.football_fixture_index' => true]);
        storeInRedis('Football:matches', $matches, 10, false, replace: true);
        $request = Request::create('/', 'POST', ['type' => 'all', 'per_page' => 20]);
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);
        $controller = app(FootballController::class);
        $report = ['matches' => 160, 'page_size' => 20, 'payload_bytes' => Redis::strlen('Football:matches'), 'index_bytes' => Redis::strlen('Football:matches:fixtures')];
        foreach (['indexed' => true, 'legacy' => false] as $mode => $enabled) {
            config(['services.football_fixture_index' => $enabled]);
            $times = [];
            $peaks = [];
            for ($run = 0; $run < 5; $run++) {
                gc_collect_cycles();
                gc_mem_caches();
                memory_reset_peak_usage();
                $memory = memory_get_usage();
                $started = hrtime(true);
                $groups = $controller->getFootballMatches();
                $times[] = round((hrtime(true) - $started) / 1000000, 3);
                $peaks[] = memory_get_peak_usage() - $memory;
                $this->assertSame(range(1000, 1019), array_map('intval', array_column(array_values($groups)[0]['matches'], 'sportsApiPro_match_id')));
                unset($groups);
            }
            sort($times);
            $report[$mode] = ['median_ms' => $times[2], 'runs_ms' => $times, 'peak_increment_bytes' => max($peaks)];
        }
        $this->assertLessThan($report['legacy']['peak_increment_bytes'], $report['indexed']['peak_increment_bytes']);
        $path = getenv('BFMARKETS_FIXTURE_BENCHMARK_REPORT');
        if (is_string($path) && str_starts_with($path, '/private/tmp/')) {
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT));
        }
        Http::assertNothingSent();
    }

    public function test_read_only_timeline_reconciles_split_history_and_repairs_both_stores_without_provider_calls(): void
    {
        $sync = $this->sync('Football');
        $match = ['unique_key' => 'known', 'sportsApiPro_match_id' => '123', 'timestamp' => time() - 600,
            'result_status' => 'Live', 'minutes_status' => '1st half', 'home_score' => 2, 'away_score' => 0];
        $goals = [['id' => 'g1', 'incidentType' => 'goal', 'time' => 8, 'isHome' => true, 'homeScore' => 1, 'awayScore' => 0],
            ['id' => 'g2', 'incidentType' => 'goal', 'time' => 25, 'isHome' => true, 'homeScore' => 2, 'awayScore' => 0]];
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => $goals], 'snapshot', microtime(true));
        $complete = $match['timeline'];
        $database = $complete;
        $database['home_goals'] = [$complete['home_goals'][0]];
        DB::table('football_timeline')->where('match_id', '123')->update(['timeline' => json_encode($database)]);
        $match['timeline']['home_goals'] = [$complete['home_goals'][1]];
        $match['timeline']['home_goals'][] = array_replace($complete['home_goals'][0], ['minute' => 30, 'seconds' => 1800]);
        $match['timeline']['incidents_updated_at'] = $database['incidents_updated_at'] - 1;
        $encoded = json_encode(['known' => $match]);
        Redis::setex('Football:matches', 120, $encoded);

        $sync->storeTimeline($match, frameType: 'read', storedTimeline: $database);

        $this->assertSame(['g1', 'g2'], array_column($match['timeline']['home_goals'], 'id'));
        $this->assertSame([8, 25], array_column($match['timeline']['home_goals'], 'minute'));
        $this->assertSame($encoded, Redis::get('Football:matches'));
        $this->assertSame($database, json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
        $jobs = Bus::dispatched(CallQueuedClosure::class)->filter(static fn (CallQueuedClosure $job): bool => $job->queue === 'sports-timeline');
        $this->assertCount(1, $jobs);
        $sync->storeTimeline($match, frameType: 'read', storedTimeline: $database);
        Bus::assertDispatchedTimes(CallQueuedClosure::class, 1);
        $manager = DB::getFacadeRoot();
        $failure = new \RuntimeException('Disposable database temporarily unavailable');
        DB::shouldReceive('transaction')->once()->andThrow($failure);
        try {
            $jobs->first()->handle($this->app);
            $this->fail('Repair failure must remain retryable.');
        } catch (\RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        } finally {
            DB::swap($manager);
        }
        $this->assertSame($encoded, Redis::get('Football:matches'));
        $jobs->first()->handle($this->app);

        $cached = json_decode(Redis::get('Football:matches'), true)['known']['timeline'];
        $stored = json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true);
        $this->assertSame($stored, $cached);
        $this->assertSame(['g1', 'g2'], array_column($cached['home_goals'], 'id'));
        $corrected = array_replace($match, ['home_score' => 1, 'timeline' => $cached]);
        $sync->storeTimeline($corrected, 'match:123:incidents', ['incidents' => [['id' => 'g1', 'isDeleted' => true]]], 'update');
        Redis::setex('Football:matches', 120, json_encode(['known' => $corrected]));
        $jobs->first()->handle($this->app);
        $afterCorrection = json_decode(Redis::get('Football:matches'), true)['known']['timeline'];
        $this->assertSame(['g2'], array_column($afterCorrection['home_goals'], 'id'));
        $this->assertSame($afterCorrection, json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
        Redis::del('Football:matches');
        $jobs->first()->handle($this->app);
        $this->assertNull(Redis::get('Football:matches'));
        $this->assertSame(['g2'], array_column(json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true)['home_goals'], 'id'));
        Http::assertNothingSent();
    }

    public function test_prediction_and_statistics_requests_do_not_wait_for_the_football_write_lock(): void
    {
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $other = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        $this->withoutMiddleware(CustomerAuthService::class);
        $match = ['unique_key' => 'known', 'sportsApiPro_match_id' => '123', 'timestamp' => time() + 600,
            'result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'team1' => 'Home Alpha', 'team2' => 'Away Beta'];
        $encoded = json_encode(['known' => $match]);
        Redis::setex('Football:matches', 120, $encoded);
        $lock = Cache::store('redis')->lock('Football:matches:write-lock', 30);
        $this->assertTrue($lock->get());
        try {
            $this->postJson('/football/filter-football', ['prediction' => ['match_id' => '123', 'poll' => '1x2', 'value' => '1']])
                ->assertJsonPath('prediction.value', '1')->assertJsonPath('prediction.counts.1', 1)->assertJsonMissingPath('html');
            $this->postJson('/football/filter-football', ['prediction' => ['match_id' => '123', 'poll' => '1x2', 'value' => '2']])
                ->assertJsonPath('prediction.value', '1')->assertJsonPath('prediction.counts.1', 1);
            $this->actingAs($other, 'customers');
            $this->postJson('/football/filter-football', ['prediction' => ['match_id' => '123', 'poll' => '1x2', 'value' => '2']])
                ->assertJsonPath('prediction.counts.1', 1)->assertJsonPath('prediction.counts.2', 1);
            $this->getJson('/football/matches/123/statistics')->assertJsonPath('html', static fn (string $html): bool => str_contains($html, 'data-statistics-content'));
        } finally {
            $lock->release();
        }
        $this->assertDatabaseCount('prediction_votes', 2);
        $this->assertSame($encoded, Redis::get('Football:matches'));
        $this->assertGreaterThan(100, Redis::ttl('Football:matches'));
        Http::assertNothingSent();
        foreach (['Live', 'Finished'] as $status) {
            Redis::set('Football:matches', json_encode(['known' => array_replace($match, ['result_status' => $status])]));
            $this->postJson('/football/filter-football', ['prediction' => ['match_id' => '123', 'poll' => 'uo', 'value' => 'OVER']])->assertUnprocessable();
        }
        $this->assertDatabaseCount('prediction_votes', 2);
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

    public function test_concurrent_api_pool_preserves_results_errors_and_bounds_in_flight_requests(): void
    {
        $script = <<<'PYTHON'
import json,time,threading
from http.server import ThreadingHTTPServer,BaseHTTPRequestHandler
active=0
peak=0
lock=threading.Lock()
class Handler(BaseHTTPRequestHandler):
 def log_message(self,*args):pass
 def do_GET(self):
  global active,peak
  if self.path=='/metrics':
   body=json.dumps({'peak':peak}).encode();code=200
  else:
   with lock:active+=1;peak=max(peak,active)
   time.sleep(1.2 if self.path=='/slow' else .12)
   body=(b'not-json' if self.path=='/invalid' else json.dumps({'path':self.path,'ok':self.path!='/error'}).encode())
   code=503 if self.path=='/error' else 200
   with lock:active-=1
  self.send_response(code);self.send_header('Content-Type','application/json');self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
server=ThreadingHTTPServer(('127.0.0.1',0),Handler)
print(server.server_port,flush=True)
server.serve_forever()
PYTHON;
        $server = new Process(['python3', '-u', '-c', $script]);
        $server->start();
        try {
            $ready = $server->waitUntil(static fn (string $type, string $output): bool => preg_match('/^\d+$/m', $output) === 1);
            $this->assertTrue($ready);
            $base = 'http://127.0.0.1:'.trim($server->getOutput());
            $requests = [];
            foreach (['first' => '/first', 'second' => '/second', 'error' => '/error', 'invalid' => '/invalid'] as $name => $path) {
                $requests[$name] = ['url' => $base.$path, 'key' => ['x-test' => 'isolated'], 'options' => ['timeout' => 2]];
            }
            $results = ApiCallPool($requests, 2);

            $this->assertSame(array_keys($requests), array_keys($results));
            $this->assertSame(['path' => '/first', 'ok' => true], $results['first']['response']);
            $this->assertSame(503, $results['error']['diagnostics']['http_status']);
            $this->assertNull($results['invalid']['response']);
            $this->assertNotNull($results['invalid']['diagnostics']['json_error']);
            $this->assertSame(2, ApiCall($base.'/metrics', [])['peak']);
            $failures = ApiCallPool([
                'slow' => ['url' => $base.'/slow', 'key' => [], 'options' => ['timeout' => 1]],
                'refused' => ['url' => 'http://127.0.0.1:1/unavailable', 'key' => [], 'options' => ['timeout' => 1]],
                'healthy' => ['url' => $base.'/healthy', 'key' => [], 'options' => ['timeout' => 2]],
            ], 2);
            $this->assertNull($failures['slow']['response']);
            $this->assertSame(CURLE_OPERATION_TIMEDOUT, $failures['slow']['diagnostics']['curl_errno']);
            $this->assertNull($failures['refused']['response']);
            $this->assertSame(CURLE_COULDNT_CONNECT, $failures['refused']['diagnostics']['curl_errno']);
            $this->assertSame('/healthy', $failures['healthy']['response']['path']);

        } finally {
            $server->stop(1);
        }
    }

    public function test_scheduled_synchronization_is_deduplicated_and_released_after_worker_execution(): void
    {
        $controller = Mockery::mock(CricketSyncController::class);
        $controller->shouldReceive('syncSportsApiProLiveData')->once()->andReturn([]);
        $this->app->instance($controller::class, $controller);
        queueSportsSynchronization($controller::class, 'syncSportsApiProLiveData');
        queueSportsSynchronization($controller::class, 'syncSportsApiProLiveData');

        Bus::assertDispatchedTimes(CallQueuedClosure::class, 1);
        $job = Bus::dispatched(CallQueuedClosure::class)->first();
        $this->assertSame('sports-realtime', $job->connection);
        $this->assertSame('sports-sync-live', $job->queue);
        $job->handle($this->app);
        queueSportsSynchronization($controller::class, 'syncSportsApiProLiveData');
        Bus::assertDispatchedTimes(CallQueuedClosure::class, 2);
    }

    public function test_pending_timeline_history_is_not_duplicated_while_waiting_for_a_worker(): void
    {
        $controller = $this->sync('Football');
        $match = ['unique_key' => 'timeline-test', 'sportsApiPro_match_id' => '123', 'result_status' => 'Live', 'minutes_status' => '1st half', 'timestamp' => time() - 1200, 'home_score' => 0, 'away_score' => 0];
        $controller->storeTimeline($match, payload: ['timeline_counts' => ['home_yellow_cards' => 1]]);
        Cache::store('redis')->forget('Football:timeline-backfill:123');
        $controller->storeTimeline($match, payload: ['timeline_counts' => ['home_yellow_cards' => 2]]);

        $jobs = Bus::dispatched(CallQueuedClosure::class)->filter(static fn (CallQueuedClosure $job): bool => $job->queue === 'sports-timeline');
        $this->assertCount(1, $jobs);
        $this->assertSame('sports-realtime', $jobs->first()->connection);
    }

    public function test_live_goal_keeps_its_observed_minute_until_the_provider_supplies_the_exact_time(): void
    {
        config(['services.football_fixture_index' => true]);
        $match = ['unique_key' => 'known', 'sportsApiPro_match_id' => '123', 'timestamp' => time() - 1020,
            'current_period_start_timestamp' => time() - 1020, 'result_status' => 'Live', 'minutes_status' => '1st half',
            'home_score' => 0, 'away_score' => 0, 'team1' => 'Home', 'team2' => 'Away', 'tournament' => 'Test League', 'markets' => []];
        storeInRedis('Football:matches', ['known' => $match]);
        $listener = app(ListenSportsApiPro::class);
        $method = new ReflectionMethod($listener, 'updateFootballCache');
        $score = ['homeScore.current' => 1, 'changes.changeTimestamp' => 10];

        $method->invoke($listener, 'match:123', $score);

        $cached = getInRedis('Football:matches', readOnly: true, matchIds: ['123'])['known'];
        $goal = $cached['timeline']['home_goals'][0];
        $this->assertSame(17, $goal['minute']);
        $this->assertTrue($goal['time_known']);
        $this->assertTrue($goal['time_estimated']);
        $this->assertSame(['17'], json_decode(DB::table('football_timeline')->where('match_id', '123')->value('home_goals'), true));
        $this->assertSame($cached['timeline'], json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
        $shown = app(FootballController::class)->footballTimeline($cached, $cached['timeline']);
        $this->assertSame("~17'", $shown['events'][0]['minute_label']);
        $this->assertSame(17 / 90 * 100, $shown['events'][0]['left']);
        $this->assertSame([], $shown['untimed_events']['home']);
        $cached['current_period_start_timestamp'] -= 300;
        storeInRedis('Football:matches', ['known' => $cached]);
        $method->invoke($listener, 'match:123', $score);
        $incident = ['id' => 'real-goal', 'incidentType' => 'goal', 'isHome' => true, 'homeScore' => 1, 'awayScore' => 0];
        $method->invoke($listener, 'match:123:incidents', ['incidents' => [$incident]], 'update');
        $cached = getInRedis('Football:matches', readOnly: true, matchIds: ['123'])['known'];
        $this->assertSame(['real-goal'], array_column($cached['timeline']['home_goals'], 'id'));
        $this->assertSame([17], array_column($cached['timeline']['home_goals'], 'minute'));
        $this->assertTrue($cached['timeline']['home_goals'][0]['time_estimated']);
        $method->invoke($listener, 'match:123:incidents', ['incidents' => [array_replace($incident, ['time' => 16])]], 'update');
        $cached = getInRedis('Football:matches', readOnly: true, matchIds: ['123'])['known'];
        $this->assertSame([16], array_column($cached['timeline']['home_goals'], 'minute'));
        $this->assertFalse($cached['timeline']['home_goals'][0]['time_estimated']);
        $this->assertSame(['16'], json_decode(DB::table('football_timeline')->where('match_id', '123')->value('home_goals'), true));
        $this->assertSame($cached['timeline'], json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
        $method->invoke($listener, 'match:123', ['homeScore.current' => 2, 'status.code' => 7, 'status.type' => 'inprogress', 'time.currentPeriodStartTimestamp' => time() - 2880, 'time.injuryTime2' => 3, 'changes.changeTimestamp' => 11]);
        $cached = getInRedis('Football:matches', readOnly: true, matchIds: ['123'])['known'];
        $this->assertSame([16, 90], array_column($cached['timeline']['home_goals'], 'minute'));
        $this->assertSame([0, 3], array_column($cached['timeline']['home_goals'], 'added_time'));
        $this->assertSame(['16', '90+3'], json_decode(DB::table('football_timeline')->where('match_id', '123')->value('home_goals'), true));
        $this->assertSame($cached['timeline'], json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
        Http::assertNothingSent();
    }

    public function test_restart_snapshots_and_score_only_updates_recover_missing_goal_history(): void
    {
        $this->actingAs(CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]), 'customers');
        $request = Request::create('/', 'POST', ['type' => 'live']);
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);
        $listener = app(ListenSportsApiPro::class);
        $config = (new ReflectionClass($listener))->getConstant('SPORTS')['football'];
        $socket = Mockery::mock(WebsocketConnection::class);
        $subscriptions = [];
        foreach (['snapshot', 'update'] as $index => $type) {
            $id = (string) (123 + $index);
            $match = ['unique_key' => 'known', 'sportsApiPro_match_id' => $id, 'betfair_match_id' => null, 'tournament' => 'Test League', 'timestamp' => time() - 1800, 'current_period_start_timestamp' => time() - 1800, 'result_status' => 'Live', 'minutes_status' => '1st half', 'home_score' => $type === 'snapshot' ? 1 : 0, 'away_score' => 0, 'team1' => 'Home Alpha', 'team2' => 'Away Beta', 'markets' => []];
            Redis::setex('Football:matches', 120, json_encode(['known' => $match], JSON_THROW_ON_ERROR));
            $frame = ['type' => $type, 'channel' => 'match:'.$id, 'data' => ['id' => (int) $id, 'homeScore.current' => 1, 'awayScore.current' => 0, 'changes.changeTimestamp' => 20]];
            if ($type === 'snapshot') {
                $frame['data'] += ['status.code' => 6, 'status.type' => 'inprogress'];
            }

            (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $frame, $socket, &$subscriptions]);

            $updated = getInRedis('Football:matches')['known'];
            $this->assertSame(1, $updated['home_score']);
            $this->assertCount(1, $updated['timeline']['home_goals']);
            $this->assertTrue($updated['timeline']['home_goals'][0]['time_known']);
            $this->assertTrue($updated['timeline']['home_goals'][0]['time_estimated']);
            $this->assertSame(30, $updated['timeline']['home_goals'][0]['minute']);
            $this->assertSame('score', $updated['timeline']['home_goals'][0]['source']);
            $this->assertSame($updated['timeline'], json_decode(DB::table('football_timeline')->where('match_id', $id)->value('timeline'), true));
            $rendered = app(FootballController::class)->footballTimeline($updated, $updated['timeline']);
            $this->assertSame([], $rendered['untimed_events']['home']);
            $this->assertSame("~30'", $rendered['events'][0]['minute_label']);
            $groups = app(FootballController::class)->getFootballMatches();
            $html = view('users.sports.football.footballDetails', ['data' => $groups])->render();
            $this->assertStringContainsString('data-timeline-event="score-home-goal-1"', $html);
            $jobs = Bus::dispatched(CallQueuedClosure::class)->filter(static fn (CallQueuedClosure $job): bool => $job->queue === 'sports-timeline');
            $this->assertCount($index + 1, $jobs);
            $this->assertSame('sports-realtime', $jobs->last()->connection);
            (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $frame, $socket, &$subscriptions]);
            $this->assertCount($index + 1, Bus::dispatched(CallQueuedClosure::class)->filter(static fn (CallQueuedClosure $job): bool => $job->queue === 'sports-timeline'));
            $this->assertCount(1, getInRedis('Football:matches')['known']['timeline']['home_goals']);
            $manager = Redis::getFacadeRoot();
            $failure = new LockTimeoutException('Isolated recovery failure before outbound HTTP');
            Redis::shouldReceive('eval')->once()->andThrow($failure);
            try {
                $jobs->last()->handle($this->app);
                $this->fail('History failure must remain retryable.');
            } catch (LockTimeoutException $exception) {
                $this->assertSame($failure, $exception);
            } finally {
                Redis::swap($manager);
            }
            $this->assertFalse(Cache::store('redis')->has('Football:timeline-backfill:'.$id.':pending'));
            $this->assertCount(1, getInRedis('Football:matches')['known']['timeline']['home_goals']);

            $incidents = ['type' => 'snapshot', 'channel' => 'match:'.$id.':incidents', 'data' => ['incidents' => [['id' => 'goal-'.$id, 'incidentType' => 'goal', 'time' => 25, 'isHome' => true, 'homeScore' => 1, 'awayScore' => 0]]]];
            (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $incidents, $socket, &$subscriptions]);
            $restored = getInRedis('Football:matches')['known']['timeline'];
            $this->assertSame([25], array_column($restored['home_goals'], 'minute'));
            $this->assertSame(['goal-'.$id], array_column($restored['home_goals'], 'id'));
            $this->assertSame($restored, json_decode(DB::table('football_timeline')->where('match_id', $id)->value('timeline'), true));

            $frame['type'] = 'update';
            $frame['data'] = ['homeScore.current' => 3, 'changes.changeTimestamp' => 21];
            (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $frame, $socket, &$subscriptions]);
            $thirdGoal = ['id' => 'third-'.$id, 'incidentType' => 'goal', 'time' => 40, 'isHome' => true, 'homeScore' => 3, 'awayScore' => 0];
            $incidents['type'] = 'update';
            $incidents['data'] = ['incidents' => [$thirdGoal]];
            (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $incidents, $socket, &$subscriptions]);
            $partial = getInRedis('Football:matches')['known']['timeline'];
            $this->assertCount(3, $partial['home_goals']);
            $this->assertSame([25, 30, 40], array_column($partial['home_goals'], 'minute'));
            $this->assertSame('score-home-goal-2', $partial['home_goals'][1]['id']);
            $frame['data'] = ['homeScore.current' => 2, 'changes.changeTimestamp' => 22];
            (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $frame, $socket, &$subscriptions]);
            $incidents['data'] = ['incidents' => [['id' => 'third-'.$id, 'isDeleted' => true], array_replace($thirdGoal, ['id' => 'second-'.$id, 'time' => 35, 'homeScore' => 2])]];
            (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $incidents, $socket, &$subscriptions]);
            $corrected = getInRedis('Football:matches')['known']['timeline'];
            $this->assertSame([25, 35], array_column($corrected['home_goals'], 'minute'));
            $this->assertSame(['goal-'.$id, 'second-'.$id], array_column($corrected['home_goals'], 'id'));
            $this->assertSame($corrected, json_decode(DB::table('football_timeline')->where('match_id', $id)->value('timeline'), true));
            $frame['data'] = ['awayScore.current' => 1, 'changes.changeTimestamp' => 23];
            (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $frame, $socket, &$subscriptions]);
            $incidents['data'] = ['incidents' => [array_replace($thirdGoal, ['id' => 'own-'.$id, 'incidentClass' => 'ownGoal', 'homeScore' => 2, 'awayScore' => 1])]];
            (new ReflectionMethod($listener, 'processSportsFrame'))->invokeArgs($listener, ['football', $config, $incidents, $socket, &$subscriptions]);
            $ownGoal = getInRedis('Football:matches')['known']['timeline'];
            $this->assertCount(3, $ownGoal['home_goals']);
            $this->assertSame([], $ownGoal['away_goals']);
            $this->assertSame(['goal-'.$id, 'second-'.$id, 'own-'.$id], array_column($ownGoal['home_goals'], 'id'));
            $this->assertSame($ownGoal, json_decode(DB::table('football_timeline')->where('match_id', $id)->value('timeline'), true));
        }
        Http::assertNothingSent();
    }
}
