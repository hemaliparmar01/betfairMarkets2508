<?php

namespace Tests\Feature;

use App\Console\Commands\ListenBetfairOdds;
use App\Events\MarketActivityUpdated;
use App\Http\Controllers\Users\Football\FootballController;
use App\Http\Controllers\Users\Football\FootballSyncController;
use App\Http\Controllers\Users\HorseRacing\HorseRacingSyncController;
use App\Jobs\PublishMarketActivity;
use App\Models\Favorite;
use App\Services\MarketActivityStream;
use App\Services\MatchBackup;
use App\Services\SportsMatchCache;
use Database\Factories\CustomerFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class SportsCacheOptimizationTest extends TestCase
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

    public static function sports(): array
    {
        return array_map(static fn (string $sport): array => [$sport], ['Football', 'Tennis', 'Basketball', ...array_keys(SportsMatchCache::SPORTS)]);
    }

    private function rows(string $sport): array
    {
        $race = in_array($sport, ['HorseRacing', 'Greyhounds'], true);
        $type = $race ? 'WIN' : ($sport === 'IceHockey' ? 'RT_MATCH_ODDS' : 'MATCH_ODDS');
        $runner = updateOddsMonitoring(['marketId' => '456', 'marketType' => $type, 'runnerId' => '11', 'runner' => 'Home Alpha',
            'sortPriority' => 1, 'handicap' => 0, 'backOdds' => 2.0, 'backSize' => 100, 'layOdds' => 2.02, 'laySize' => 150], [], time() - 100);
        $runner['backOddsHistory'] = array_fill(0, 1000, ['timestamp' => time() - 100, 'odds' => 2.0]);
        $rows = [];
        for ($i = 0; $i < 3; $i++) {
            $key = $race ? strtolower($sport).'|'.(456 + $i) : 'match-'.$i;
            $rows[$key] = ['unique_key' => $key, 'sportsApiPro_match_id' => 123 + $i, 'betfair_match_id' => (string) (456 + $i),
                'team1' => 'Home Alpha', 'team2' => 'Away Beta', 'home_score' => 0, 'away_score' => 0,
                'home_score_data' => ['current' => 0], 'away_score_data' => ['current' => 0],
                'tournament' => $sport === 'Tennis' ? 'ATP Finals Turin 2026' : 'Premier League',
                'tournament_country' => $sport === 'Tennis' ? 'ATP' : 'England', 'country' => 'England',
                'timestamp' => time() + 60 + $i, 'result_status' => 'Prematch', 'minutes_status' => 'Prematch',
                'data_sync_complete' => true, 'markets' => ['456_'.$type => ['11|0' => $runner]]];
        }

        return $rows;
    }

    private function install(string $sport, array $rows): object
    {
        config(['services.'.strtolower($sport).'_match_cache' => true]);
        Redis::setex($sport.':matches', 600, json_encode($rows));
        $cache = sportsMatchRecordCache($sport.':matches', activeOnly: false);
        $cache->migrate();

        return $cache;
    }

    private function comparable(array $rows): array
    {
        foreach ($rows as $key => $value) {
            if ($key === '_fixture_data_version') {
                unset($rows[$key]);
            } elseif (is_array($value)) {
                $rows[$key] = $this->comparable($value);
            }
        }

        return $rows;
    }

    #[DataProvider('sports')]
    public function test_migration_preserves_complete_history_and_reads_only_the_requested_record(string $sport): void
    {
        $rows = $this->rows($sport);
        $cache = $this->install($sport, $rows);
        $manager = Redis::getFacadeRoot();
        Redis::shouldReceive('get')->with($sport.':matches')->never();
        Redis::shouldReceive('get')->with($sport.':matches:individual')->andReturn('1');
        Redis::shouldReceive('hmget')->andReturnUsing(static fn (...$args): mixed => $manager->hmget(...$args));
        Redis::shouldReceive('mget')->once()->with([$cache->recordKey(array_key_first($rows))])->andReturnUsing(static fn (...$args): mixed => $manager->mget(...$args));
        try {
            $selected = getInRedis($sport.':matches', readOnly: true, matchIds: ['123']);
            $this->assertSame([array_key_first($rows)], array_keys($selected));
            $this->assertSame(json_encode($rows[array_key_first($rows)]['markets']), json_encode($selected[array_key_first($rows)]['markets']));
        } finally {
            Redis::swap($manager);
        }
        $this->assertSame(1000, count(array_values(array_values($selected)[0]['markets'])[0]['11|0']['backOddsHistory']));
        $this->assertSame(json_encode($rows), Redis::get($sport.':matches'));
    }

    #[DataProvider('sports')]
    public function test_fixture_pagination_and_market_activity_preserve_the_legacy_outputs(string $sport): void
    {
        $rows = $this->rows($sport);
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        $request = Request::create('/', 'GET', ['type' => 'upcoming', 'per_page' => 1]);
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);
        $key = $sport.':matches';
        config(['services.'.strtolower($sport).'_match_cache' => false]);
        Redis::setex($key, 600, json_encode($rows));
        $controllerClass = 'App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'Controller';
        $controller = app($controllerClass);
        $legacy = $controller->{'get'.$sport.'Matches'}();
        $legacyActivity = $controller->getMarketActivites();
        $cache = $this->install($sport, $rows);
        $this->assertSame($this->comparable($legacy), $this->comparable(app($controllerClass)->{'get'.$sport.'Matches'}()));
        $this->assertSame($this->comparable($legacyActivity), $this->comparable(app($controllerClass)->getMarketActivites()));
        Http::assertNothingSent();
    }

    #[DataProvider('sports')]
    public function test_lifecycle_cleanup_preserves_history_and_matches_legacy_state(string $sport): void
    {
        $rows = $this->rows($sport);
        $first = array_key_first($rows);
        $rows[$first]['timestamp'] = time() - 400 * 60;
        $rows[$first]['result_status'] = 'Live';
        $key = $sport.':matches';
        config(['services.'.strtolower($sport).'_match_cache' => false]);
        Redis::setex($key, 600, json_encode($rows));
        $backups = app(MatchBackup::class);
        $backups->process(strtolower($sport));
        $legacy = getInRedis($key, readOnly: true);
        $this->install($sport, $rows);
        $backups->process(strtolower($sport));
        $optimized = getInRedis($key, readOnly: true);
        foreach ($rows as $id => $row) {
            $this->assertSame($legacy[$id]['result_status'] ?? null, $optimized[$id]['result_status'] ?? null);
            $this->assertSame($legacy[$id]['markets'] ?? null, $optimized[$id]['markets'] ?? null);
            $this->assertSame(isset($legacy[$id]['internal_backup']), isset($optimized[$id]['internal_backup']));
        }
    }

    #[DataProvider('sports')]
    public function test_betfair_flush_publishes_committed_quotes_for_every_sport(string $sport): void
    {
        $rows = $this->rows($sport);
        $this->install($sport, $rows);
        $match = array_values($rows)[0];
        $runner = array_values(array_values($match['markets'])[0])[0];
        $runner = array_intersect_key($runner, array_flip(['marketId', 'marketType', 'runnerId', 'runner', 'sortPriority', 'handicap']));
        $runner += ['backOdds' => 1.8, 'layOdds' => 1.82, 'backSize' => 120, 'laySize' => 180];
        $sportName = ['Football' => 'soccer', 'IceHockey' => 'ice hockey', 'AmericanFootball' => 'american football', 'HorseRacing' => 'horse_racing'][$sport] ?? strtolower($sport);
        $event = ['sport' => $sportName, 'team1' => $match['team1'], 'team2' => $match['team2'], 'tournament' => $match['tournament'],
            'openTimestamp' => gmdate('c', $match['timestamp']), 'country' => 'England', 'markets' => [$runner]];
        $feed = match ($sport) {
            'HorseRacing' => 'horse_prematch', 'Greyhounds' => 'greyhounds', default => 'prematch'
        };
        $pending = [$feed => ['456' => $event]];

        (new ReflectionMethod(ListenBetfairOdds::class, 'flushPendingChanges'))->invokeArgs(app(ListenBetfairOdds::class), [&$pending]);

        Bus::assertDispatched(PublishMarketActivity::class);
        (new PublishMarketActivity($sport))->handle(app(MarketActivityStream::class));

        Event::assertDispatched(MarketActivityUpdated::class, function ($event) use ($sport): bool {
            $this->assertSame(strtolower($sport), $event->sport);

            return array_any($event->markets, static fn (array $market): bool => array_any($market['runners'], static fn (array $runner): bool => $runner['back'] === 1.8 && $runner['backSize'] === 120.0));
        });
        $this->assertSame([], $pending[$feed]);
    }

    public function test_stream_bursts_keep_one_pending_job_and_each_match_id_once(): void
    {
        $service = app(MarketActivityStream::class);
        for ($tick = 0; $tick < 50; $tick++) {
            $service->publish('Football', ['456' => [], '457' => []]);
        }

        Bus::assertDispatchedTimes(PublishMarketActivity::class, 1);
        $ids = Redis::smembers('MarketActivity:pending:Football');
        sort($ids);
        $this->assertSame(['456', '457'], $ids);
        $this->assertLessThanOrEqual(300, Redis::ttl('MarketActivity:pending:Football'));
    }

    private function mergeQuote(string $sport, array $rows): void
    {
        $match = array_values($rows)[0];
        $runner = array_values(array_values($match['markets'])[0])[0];
        $runner = array_intersect_key($runner, array_flip(['marketId', 'marketType', 'runnerId', 'runner', 'sortPriority', 'handicap']));
        $runner += ['backOdds' => 1.8, 'layOdds' => 1.82, 'backSize' => 120, 'laySize' => 180];
        $sportName = ['Football' => 'soccer', 'IceHockey' => 'ice hockey', 'AmericanFootball' => 'american football', 'HorseRacing' => 'horse_racing'][$sport] ?? strtolower($sport);
        $event = ['sport' => $sportName, 'team1' => $match['team1'], 'team2' => $match['team2'], 'tournament' => $match['tournament'],
            'openTimestamp' => gmdate('c', $match['timestamp']), 'country' => 'England', 'markets' => [$runner]];
        if ($sport === 'Football') {
            (new ReflectionMethod(ListenBetfairOdds::class, 'updateFootballCache'))->invoke(app(ListenBetfairOdds::class), ['456' => $event], false);
        } else {
            app('App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'SyncController')->mergeBetfairWebSocketData(['456' => $event], false);
        }
    }

    #[DataProvider('sports')]
    public function test_odds_updates_preserve_baselines_history_and_unaffected_matches(string $sport): void
    {
        $rows = $this->rows($sport);
        $cacheKey = $sport.':matches';
        config(['services.'.strtolower($sport).'_match_cache' => false]);
        Redis::setex($cacheKey, 600, json_encode($rows));
        $this->mergeQuote($sport, $rows);
        $legacy = getInRedis($cacheKey, readOnly: true);
        $this->install($sport, $rows);
        $this->mergeQuote($sport, $rows);
        $optimized = getInRedis($cacheKey, readOnly: true);
        $first = array_key_first($rows);
        $oldRunner = array_values(array_values($legacy[$first]['markets'])[0])[0];
        $newRunner = array_values(array_values($optimized[$first]['markets'])[0])[0];
        foreach (['initialBackOdds', 'previousBackOdds', 'currentBackOdds', 'backOdds', 'layOdds', 'backSize', 'laySize', 'opening_odds_source', 'pressure_type', 'prematch_drop_triggered'] as $field) {
            $this->assertSame($oldRunner[$field] ?? null, $newRunner[$field] ?? null, $field);
        }
        $this->assertSame(array_column($oldRunner['backOddsHistory'], 'odds'), array_column($newRunner['backOddsHistory'], 'odds'));
        $this->assertSame(1.8, (float) $newRunner['currentBackOdds']);
        foreach (array_slice(array_keys($rows), 1) as $key) {
            $this->assertSame(json_encode($rows[$key]['markets']), json_encode($optimized[$key]['markets']));
        }
        $model = 'App\\Models\\'.$sport.'Market';
        $this->assertTrue($model::query()->where('current_back_odds', 1.8)->exists());
        Http::assertNothingSent();
    }

    public static function newTeamSports(): array
    {
        return array_map(static fn (string $sport): array => [$sport], ['Cricket', 'IceHockey', 'AmericanFootball', 'Baseball']);
    }

    #[DataProvider('newTeamSports')]
    public function test_score_only_updates_preserve_database_odds_history_and_every_event_in_large_frames(string $sport): void
    {
        $rows = $this->rows($sport);
        $first = array_values($rows)[0];
        $rows = [];
        $events = [];
        for ($i = 0; $i < 27; $i++) {
            $row = array_replace($first, ['unique_key' => 'match-'.$i, 'sportsApiPro_match_id' => 123 + $i, 'betfair_match_id' => (string) (456 + $i)]);
            $rows['match-'.$i] = $row;
            $events[] = ['id' => 123 + $i, 'status' => ['type' => 'inprogress', 'code' => 13], 'homeScore' => ['current' => 5], 'awayScore' => ['current' => 1]];
        }
        $cache = $this->install($sport, $rows);
        $sync = app('App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'SyncController');
        $sync->persistMatches($rows);
        $marketModel = 'App\\Models\\'.$sport.'Market';
        $before = $marketModel::query()->pluck('payload', 'selection_key')->all();
        $this->assertTrue($sync->mergeSportsWebSocketData('live-scores', ['events' => $events]));
        $after = getInRedis($sport.':matches', readOnly: true);
        $this->assertCount(27, $after);
        foreach ($after as $match) {
            $this->assertSame(5, $match['home_score']);
            $this->assertSame(json_encode($first['markets']), json_encode($match['markets']));
        }
        $this->assertSame($before, $marketModel::query()->pluck('payload', 'selection_key')->all());
        Http::assertNothingSent();
    }

    public function test_race_snapshot_removes_absent_races_without_losing_their_prices(): void
    {
        foreach (['HorseRacing', 'Greyhounds'] as $sport) {
            $rows = $this->rows($sport);
            $this->install($sport, $rows);
            $match = array_values($rows)[0];
            $runner = array_values(array_values($match['markets'])[0])[0];
            $event = ['sport' => $sport === 'HorseRacing' ? 'horse_racing' : 'greyhounds', 'team1' => 'Home Alpha', 'team2' => 'Away Beta',
                'openTimestamp' => gmdate('c', $match['timestamp']), 'markets' => [$runner]];
            $sync = app('App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'SyncController');
            $sync->mergeBetfairWebSocketData(['456' => $event], false, true);
            $after = getInRedis($sport.':matches', readOnly: true);
            $this->assertSame('Prematch', $after[array_key_first($rows)]['result_status']);
            foreach (array_slice(array_keys($rows), 1) as $key) {
                $this->assertSame('Finished', $after[$key]['result_status']);
                $this->assertSame(json_encode($rows[$key]['markets']), json_encode($after[$key]['markets']));
            }
        }
    }

    public function test_racing_snapshot_reads_the_full_catalog_once_and_targets_each_small_batch(): void
    {
        foreach (['HorseRacing', 'Greyhounds'] as $sport) {
            $base = array_values($this->rows($sport))[0];
            $runner = array_values(array_values($base['markets'])[0])[0];
            $rows = [];
            $events = [];
            for ($number = 0; $number < 55; $number++) {
                $id = (string) (10000 + $number);
                $key = strtolower($sport).'|'.$id;
                $quote = array_replace($runner, ['marketId' => $id]);
                $rows[$key] = array_replace($base, ['unique_key' => $key, 'betfair_match_id' => $id, 'markets' => [$id.'_WIN' => ['11|0' => $quote]]]);
                $events[$id] = ['sport' => $sport === 'HorseRacing' ? 'horse_racing' : 'greyhounds',
                    'openTimestamp' => gmdate('c', $base['timestamp']), 'markets' => [$quote]];
            }
            $this->install($sport, $rows);
            $reads = $this->watchReads();
            $sync = app('App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'SyncController');

            $sync->mergeBetfairWebSocketData($events, false, true);

            $this->assertSame(1, $reads->catalog_reads);
            $this->assertSame([], $reads->whole);
            $this->assertLessThanOrEqual(25, max($reads->batch_sizes));
            $reads->catalog_reads = 0;
            $sync->mergeBetfairWebSocketData(array_slice($events, 0, 2, true), false);
            $this->assertSame(0, $reads->catalog_reads);
            $sample = getInRedis($sport.':matches', readOnly: true, matchKeys: [strtolower($sport).'|10000', strtolower($sport).'|10054']);
            $this->assertCount(2, $sample);
            $this->assertSame(array_column($runner['backOddsHistory'], 'odds'), array_map('floatval', array_column($sample[strtolower($sport).'|10054']['markets']['10054_WIN']['11|0']['backOddsHistory'], 'odds')));
        }
    }

    #[DataProvider('sports')]
    public function test_selected_market_recovery_reads_one_record_and_returns_its_latest_book(string $sport): void
    {
        $rows = $this->rows($sport);
        $cache = $this->install($sport, $rows);
        $key = array_key_first($rows);
        $market = array_key_first($rows[$key]['markets']);
        $runnerKey = array_key_first($rows[$key]['markets'][$market]);
        $reads = $this->watchReads();
        $service = app(MarketActivityStream::class);

        $quote = $service->quote($sport, $key, $market);

        $this->assertSame($key, $quote['matchKey']);
        $this->assertSame(100.0, $quote['runners'][0]['backSize']);
        $this->assertSame([], $reads->whole);
        $this->assertSame([1], $reads->batch_sizes);
        $updated = $rows[$key];
        $updated['markets'][$market][$runnerKey] = array_replace($updated['markets'][$market][$runnerKey], [
            'backSize' => 400, 'laySize' => 100, 'liquidity_updated_at' => microtime(true)]);
        $cache->store([$key => $updated], 60, false);
        $latest = $service->quote($sport, $key, $market);
        $this->assertSame(400.0, $latest['runners'][0]['backSize']);
        $this->assertSame(100.0, $latest['runners'][0]['laySize']);
        $missing = $service->quote($sport, $key, 'missing-market');
        $this->assertTrue($missing['removed']);
        $this->assertSame([], $missing['runners']);
    }

    private function watchReads(): object
    {
        $reads = (object) ['whole' => [], 'batch_sizes' => [], 'catalog_reads' => 0];
        $connection = Redis::connection();
        $connection->setEventDispatcher(new Dispatcher);
        $connection->listen(static function (CommandExecuted $event) use ($reads): void {
            if (strtolower($event->command) === 'get' && str_ends_with((string) ($event->parameters[0] ?? ''), ':matches')) {
                $reads->whole[] = $event->parameters[0];
            }
            if (strtolower($event->command) === 'hgetall' && str_ends_with((string) ($event->parameters[0] ?? ''), ':matches:catalog')) {
                $reads->catalog_reads++;
            }
            if (strtolower($event->command) === 'mget') {
                $reads->batch_sizes[] = count($event->parameters[0]);
            }
        });

        return $reads;
    }

    public static function restStates(): array
    {
        $cases = [];
        foreach (['Cricket', 'IceHockey', 'AmericanFootball', 'Baseball'] as $sport) {
            foreach ([false, true] as $live) {
                $cases[$sport.($live ? '-live' : '-prematch')] = [$sport, $live];
            }
        }

        return $cases;
    }

    #[DataProvider('restStates')]
    public function test_rest_sync_matches_existing_scores_and_preserves_market_history(string $sport, bool $live): void
    {
        $rows = $this->rows($sport);
        $first = array_values($rows)[0];
        $event = ['id' => 123, 'homeTeam' => ['name' => 'Home Alpha'], 'awayTeam' => ['name' => 'Away Beta'],
            'startTimestamp' => $first['timestamp'], 'tournament' => ['name' => 'Premier League'],
            'status' => ['type' => $live ? 'inprogress' : 'notstarted', 'code' => $live ? 13 : 0],
            'homeScore' => ['current' => 7], 'awayScore' => ['current' => 2]];
        $sync = app('App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'SyncController');
        config(['services.'.strtolower($sport).'_match_cache' => false]);
        Redis::setex($sport.':matches', 600, json_encode($rows));
        (new ReflectionMethod($sync, 'readsportApiData'))->invoke($sync, ['events' => [$event]], $sport.':matches', $live, 10);
        $legacy = getInRedis($sport.':matches', readOnly: true);
        $this->install($sport, $rows);
        $reads = $this->watchReads();
        (new ReflectionMethod($sync, 'readsportApiData'))->invoke($sync, ['events' => [$event]], $sport.':matches', $live, 10);
        $this->assertSame([], $reads->whole);
        $actual = getInRedis($sport.':matches', readOnly: true);
        foreach ($rows as $key => $row) {
            foreach (['home_score', 'away_score', 'result_status', 'minutes_status'] as $field) {
                $this->assertSame($legacy[$key][$field], $actual[$key][$field]);
            }
            $this->assertSame(json_encode($row['markets']), json_encode($actual[$key]['markets']));
        }
        Http::assertNothingSent();
    }

    public function test_horse_metadata_sync_processes_every_race_in_small_batches_without_changing_odds(): void
    {
        $first = array_values($this->rows('HorseRacing'))[0];
        $firstRunner = array_values(array_values($first['markets'])[0])[0];
        $rows = [];
        $leagues = [];
        for ($i = 0; $i < 27; $i++) {
            $key = 'horseracing|'.(456 + $i);
            $race = array_replace($first, ['unique_key' => $key, 'betfair_match_id' => (string) (456 + $i), 'team1' => 'Course '.$i]);
            $second = array_replace($firstRunner, ['runnerId' => '12', 'runner' => 'Away Beta']);
            $race['markets'] = [($i + 456).'_WIN' => ['11|0' => array_replace($firstRunner, ['marketId' => (string) (456 + $i)]),
                '12|0' => array_replace($second, ['marketId' => (string) (456 + $i)])]];
            $rows[$key] = $race;
            $leagues[] = ['league' => ['leagueId' => $i + 1, 'url' => 'https://example.test/course-'.$i.'/', 'country' => 'England', 'name' => 'Race '.$i],
                'events' => [['id' => $i * 2 + 1, 'startTimestamp' => $race['timestamp'], 'status' => 'notstarted', 'homeTeam' => ['name' => 'Home Alpha', 'shortName' => '1']],
                    ['id' => $i * 2 + 2, 'startTimestamp' => $race['timestamp'], 'status' => 'notstarted', 'homeTeam' => ['name' => 'Away Beta', 'shortName' => '2']]]];
        }
        $cache = $this->install('HorseRacing', $rows);
        $reads = $this->watchReads();
        $result = (new ReflectionMethod(HorseRacingSyncController::class, 'mergeSportsRaceMetadata'))->invoke(app(HorseRacingSyncController::class), ['leagues' => $leagues]);
        $this->assertTrue($result);
        $this->assertSame([], $reads->whole);
        $this->assertNotEmpty($reads->batch_sizes);
        $this->assertLessThanOrEqual(10, max($reads->batch_sizes));
        $after = $cache->read();
        $this->assertCount(27, $after);
        foreach ($after as $key => $race) {
            $runners = array_values(array_values($race['markets'])[0]);
            $this->assertSame(1, $runners[0]['number']);
            $this->assertSame(2, $runners[1]['number']);
            $this->assertSame($firstRunner['backOddsHistory'], array_map(static fn (array $point): array => ['timestamp' => $point['timestamp'], 'odds' => (float) $point['odds']], $runners[0]['backOddsHistory']));
            $this->assertSame(2.0, (float) $runners[0]['currentBackOdds']);
        }
        Http::assertNothingSent();
    }

    public function test_cache_migration_command_is_idempotent_and_keeps_the_legacy_snapshot(): void
    {
        $rows = $this->rows('Greyhounds');
        Redis::setex('Greyhounds:matches', 600, json_encode($rows));
        $this->artisan('sports:cache-migrate', ['--sport' => ['Greyhounds']])->assertSuccessful();
        $this->artisan('sports:cache-migrate', ['--sport' => ['Greyhounds']])->assertSuccessful();
        $this->artisan('sports:cache-migrate', ['--sport' => ['Greyhounds'], '--status' => true])->assertSuccessful();
        $this->assertSame(json_encode($rows), Redis::get('Greyhounds:matches'));
        $this->assertTrue(sportsMatchRecordCache('Greyhounds:matches')->active());
        $this->assertCount(3, getInRedis('Greyhounds:matches'));
        $this->artisan('sports:cache-migrate', ['--sport' => ['Unknown']])->assertFailed();
    }

    #[DataProvider('sports')]
    public function test_concurrent_score_and_odds_writers_preserve_each_others_changes(string $sport): void
    {
        $rows = $this->rows($sport);
        $cache = $this->install($sport, $rows);
        $key = array_key_first($rows);
        $before = $cache->read(matchKeys: [$key]);
        $scores = $before;
        $scores[$key]['home_score'] = 9;
        $cache->store($scores, 30, false, $before);
        $odds = $before;
        $market = array_key_first($odds[$key]['markets']);
        $runner = array_key_first($odds[$key]['markets'][$market]);
        $odds[$key]['markets'][$market][$runner]['backOdds'] = 1.5;
        $cache->store($odds, 30, false, $before);
        $after = $cache->read(matchKeys: [$key]);
        $this->assertSame(9, $after[$key]['home_score']);
        $this->assertSame(1.5, $after[$key]['markets'][$market][$runner]['backOdds']);
        $this->assertCount(1000, $after[$key]['markets'][$market][$runner]['backOddsHistory']);
    }

    #[DataProvider('sports')]
    public function test_smart_filters_and_multiple_pages_keep_existing_results(string $sport): void
    {
        $rows = $this->rows($sport);
        foreach ($rows as &$row) {
            foreach ($row['markets'] as &$runners) {
                foreach ($runners as &$runner) {
                    $runner['prematch_drop_triggered'] = true;
                    $runner['market_shock_detected_at'] = time();
                }
                unset($runner);
            }
            unset($runners);
        }
        unset($row);
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        Favorite::query()->create(['customer_id' => $customer->id, 'sport' => strtolower($sport), 'event_key' => array_key_first($rows), 'favorite_key' => hash('sha256', $sport.'-test-favorite')]);
        $request = Request::create('/', 'GET', ['per_page' => 1]);
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);
        $controllerClass = 'App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'Controller';
        config(['services.'.strtolower($sport).'_match_cache' => false]);
        Redis::setex($sport.':matches', 600, json_encode($rows));
        $expected = [];
        foreach (['all', 'upcoming', 'by_time', 'favorites', 'prematch_drops', 'market_shock', 'live', 'finished'] as $type) {
            foreach ([1, 2] as $page) {
                $request->merge(['type' => $type, 'page' => $page]);
                $expected[$type][$page] = app($controllerClass)->{'get'.$sport.'Matches'}();
            }
        }
        $this->install($sport, $rows);
        foreach ($expected as $type => $pages) {
            foreach ($pages as $page => $output) {
                $request->merge(['type' => $type, 'page' => $page]);
                $actual = app($controllerClass)->{'get'.$sport.'Matches'}();
                $this->assertSame($this->comparable($output), $this->comparable($actual), $type.' page '.$page);
            }
        }
    }

    #[DataProvider('sports')]
    public function test_refreshing_metadata_keeps_payload_bytes_and_record_expiry_unchanged(string $sport): void
    {
        $cache = $this->install($sport, $this->rows($sport));
        $index = $cache->index(ordered: false);
        $raw = [];
        $ttls = [];
        foreach ($index['matches'] as $key => $entry) {
            $raw[$key] = Redis::get($cache->recordKey($key));
            $ttls[$key] = Redis::ttl($cache->recordKey($key));
        }
        $this->assertSame(3, $cache->refreshMetadata());
        $after = $cache->index(ordered: false);
        foreach ($raw as $key => $payload) {
            $this->assertSame($payload, Redis::get($cache->recordKey($key)));
            $this->assertLessThanOrEqual($ttls[$key], Redis::ttl($cache->recordKey($key)));
            $this->assertSame($index['matches'][$key]['_expires_at'], $after['matches'][$key]['_expires_at']);
            $this->assertSame($index['matches'][$key]['_cache_updated_at'], $after['matches'][$key]['_cache_updated_at']);
        }
    }

    public function test_targeted_football_rest_odds_preserve_the_existing_prices_and_history(): void
    {
        $rows = $this->rows('Football');
        $first = array_values($rows)[0];
        $runner = array_values(array_values($first['markets'])[0])[0];
        $runner = array_replace($runner, ['backOdds' => 1.8, 'currentBackOdds' => 1.8, 'layOdds' => 1.82]);
        $event = ['sport' => 'soccer', 'team1' => 'Home Alpha', 'team2' => 'Away Beta', 'tournament' => 'Premier League',
            'country' => 'England', 'openTimestamp' => gmdate('c', $first['timestamp']), 'markets' => [$runner]];
        config(['services.football_match_cache' => false]);
        Redis::setex('Football:matches', 600, json_encode($rows));
        $sync = app(FootballSyncController::class);
        $parse = new ReflectionMethod($sync, 'readBetFairData');
        $parse->invoke($sync, ['liveMap' => ['456' => $event]], 'Football:matches', false, 30);
        $legacy = getInRedis('Football:matches', readOnly: true);
        $this->install('Football', $rows);
        $reads = $this->watchReads();
        $parse->invoke($sync, ['liveMap' => ['456' => $event]], 'Football:matches', false, 30, true);
        $this->assertSame([], $reads->whole);
        $after = getInRedis('Football:matches', readOnly: true);
        $key = array_key_first($rows);
        $oldRunner = array_values(array_values($legacy[$key]['markets'])[0])[0];
        $newRunner = array_values(array_values($after[$key]['markets'])[0])[0];
        $this->assertSame($oldRunner['currentBackOdds'], $newRunner['currentBackOdds']);
        $this->assertSame(array_column($oldRunner['backOddsHistory'], 'odds'), array_column($newRunner['backOddsHistory'], 'odds'));
        foreach (array_slice(array_keys($rows), 1) as $other) {
            $this->assertSame(json_encode($rows[$other]['markets']), json_encode($after[$other]['markets']));
        }
    }

    public static function additionalSports(): array
    {
        return array_map(static fn (string $sport): array => [$sport], array_keys(SportsMatchCache::SPORTS));
    }

    #[DataProvider('additionalSports')]
    public function test_explicit_cache_replacements_keep_legacy_lifecycle_semantics(string $sport): void
    {
        $rows = $this->rows($sport);
        $key = array_key_first($rows);
        $rows[$key]['result_status'] = 'Live';
        config(['services.'.strtolower($sport).'_match_cache' => false]);
        Redis::setex($sport.':matches', 600, json_encode($rows));
        $incoming = [$key => array_replace($rows[$key], ['result_status' => 'Prematch', 'minutes_status' => 'Prematch'])];
        storeInRedis($sport.':matches', $incoming, 30, false, $rows);
        $expected = getInRedis($sport.':matches', readOnly: true)[$key];
        $cache = $this->install($sport, $rows);
        storeInRedis($sport.':matches', $incoming, 30, false, $rows);
        $actual = $cache->read(matchKeys: [$key])[$key];
        $this->assertSame($expected['result_status'], $actual['result_status']);
        $this->assertSame($expected['minutes_status'], $actual['minutes_status']);
        $this->assertSame($expected['markets'], $actual['markets']);
    }

    #[DataProvider('sports')]
    public function test_favorite_pressure_and_rolling_indicators_match_the_full_history_path(string $sport): void
    {
        $rows = $this->rows($sport);
        $key = array_key_first($rows);
        $market = array_key_first($rows[$key]['markets']);
        $quote = array_values($rows[$key]['markets'][$market])[0];
        $quote['backOdds'] = 1.88;
        $quote['layOdds'] = 1.90;
        $runner = [];
        for ($offset = -920; $offset <= 0; $offset += 10) {
            $runner = updateOddsMonitoring(array_replace($quote, ['backSize' => $offset <= -60 ? 60 : 26, 'laySize' => $offset <= -60 ? 40 : 74]), $runner, time() + $offset);
        }
        $rows[$key]['markets'][$market] = ['11|0' => $runner];
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        $request = Request::create('/', 'GET', ['per_page' => 1, 'match' => '123']);
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);
        $controllerClass = 'App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'Controller';
        config(['services.'.strtolower($sport).'_match_cache' => false]);
        Redis::setex($sport.':matches', 600, json_encode($rows));
        $expected = app($controllerClass)->getMarketActivites();
        $this->assertNotEmpty($expected);
        $this->install($sport, $rows);
        $actual = app($controllerClass)->getMarketActivites();
        $this->assertSame($this->comparable($expected), $this->comparable($actual));
        $first = array_values($actual)[0];
        $this->assertEqualsWithDelta(74.0, (float) ($first['backPercent'] ?? $first['indicators']['favorite_back_percent']), 0.001);
    }

    public function test_equal_liquidity_market_activity_keeps_the_legacy_page_order(): void
    {
        $rows = $this->rows('Football');
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        $request = Request::create('/', 'GET', ['per_page' => 1]);
        $request->setLaravelSession($this->app['session']->driver());
        $this->app->instance('request', $request);
        config(['services.football_match_cache' => false]);
        Redis::setex('Football:matches', 600, json_encode($rows));
        $expected = [];
        foreach ([1, 2, 3] as $page) {
            $request->merge(['page' => $page]);
            $expected[$page] = app(FootballController::class)->getMarketActivites();
        }
        $cache = $this->install('Football', $rows);
        foreach ($expected as $page => $output) {
            $request->merge(['page' => $page]);
            $actual = app(FootballController::class)->getMarketActivites();
            $this->assertSame($this->comparable($output), $this->comparable($actual));
        }
        $cache->refreshMetadata();
        $this->assertSame(array_keys($rows), array_keys($cache->index(ordered: false)['matches']));
    }
}
