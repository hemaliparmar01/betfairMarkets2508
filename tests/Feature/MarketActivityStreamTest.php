<?php

namespace Tests\Feature;

use App\Events\MarketActivityUpdated;
use App\Http\Controllers\Users\Football\FootballController;
use App\Http\Controllers\Users\Football\FootballSyncController;
use App\Jobs\ProcessAlert;
use App\Services\AlertService;
use App\Services\CustomerAuthService;
use App\Services\FootballMatchCache;
use App\Services\MarketActivityStream;
use App\Services\MarketActivityTiming;
use Database\Factories\CustomerFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\FileViewFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MarketActivityStreamTest extends TestCase
{
    use RefreshDatabase;

    public static function sports(): array
    {
        return array_map(static fn (string $sport): array => [$sport], array_keys(MarketActivityStream::SPORTS));
    }

    public static function teamSports(): array
    {
        return array_filter(self::sports(), static fn (array $sport): bool => ! in_array($sport[0], ['HorseRacing', 'Greyhounds'], true));
    }

    #[DataProvider('teamSports')]
    public function test_real_rolling_quotes_keep_one_and_five_minute_indicators_consistent_across_sports(string $prefix): void
    {
        $now = microtime(true);
        $match = $this->match();
        foreach ($match['markets']['123_WIN'] as &$runner) {
            $previous = [];
            for ($ago = 310; $ago >= 0; $ago -= 10) {
                $interest = $ago >= 300 ? 20 : ($ago >= 60 ? 40 : 70);
                $previous = updateOddsMonitoring(array_replace($runner, ['backSize' => 100 - $interest, 'laySize' => $interest]), $previous, $now - $ago);
            }
            $runner = $previous;
        }
        unset($runner);

        $quotes = app(MarketActivityStream::class)->markets($prefix, 'match-a', $match);

        $this->assertSame('back', $quotes[0]['balance']);
        $this->assertSame('strong_back', $quotes[0]['momentum']);
        $this->assertEqualsWithDelta(30.0, $quotes[0]['deltaWindows'][60], 0.001);
        $this->assertEqualsWithDelta(50.0, $quotes[0]['deltaValue'], 0.001);
        $this->assertFalse($quotes[0]['stale']);
    }

    public function test_fixture_tabs_keep_racing_and_market_entry_defaults_to_football(): void
    {
        $this->withoutVite();
        Redis::shouldReceive('get')->andReturn(null);

        $response = $this->get(route('football.home'))->assertOk();

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(route('horseracing.home'), $xpath->evaluate('string(//*[@id="sportSelector"]/a[@data-sport="horseracing"]/@href)'));
        $this->assertSame(route('greyhounds.home'), $xpath->evaluate('string(//*[@id="sportSelector"]/a[@data-sport="greyhounds"]/@href)'));
        $this->assertSame(0, $xpath->query('//*[@id="sportSelector"][@data-market-activity-tabs]')->length);
        $this->assertGreaterThan(0, $xpath->query('//a[@href="'.route('football.market-activity').'"]')->length);
    }

    #[DataProvider('sports')]
    public function test_committed_exchange_quotes_stream_with_existing_indicators_and_without_history(string $prefix): void
    {
        config(['services.'.MarketActivityStream::SPORTS[$prefix].'_match_cache' => false]);
        $match = $this->match();
        Redis::shouldReceive('get')->with($prefix.':matches')->andReturn(json_encode(['match-a' => $match]));
        Event::fake([MarketActivityUpdated::class]);

        app(MarketActivityStream::class)->broadcastCurrent($prefix, [123 => []]);

        Event::assertDispatched(MarketActivityUpdated::class, function (MarketActivityUpdated $event) use ($prefix, $match): bool {
            $row = $event->markets[0];
            $expected = prepareMatchOddsMonitoring($match, true)['market_indicators']['123_WIN'];
            $this->assertSame('private-sports.'.MarketActivityStream::SPORTS[$prefix].'.market-activity', $event->broadcastOn()[0]->name);
            $this->assertSame('match-a', $row['matchKey']);
            $this->assertSame('123_WIN', $row['marketKey']);
            $this->assertSame(1.8, $row['runners'][0]['back']);
            $this->assertSame(1600.0, $row['runners'][0]['backSize']);
            $this->assertSame(40.72, $row['runners'][0]['laySize']);
            $this->assertSame($expected['balance'], $row['balance']);
            $this->assertSame($expected['momentum'], $row['momentum']);
            $this->assertSame($expected['delta'], $row['deltaValue']);
            $this->assertArrayNotHasKey('backOddsHistory', $row['runners'][0]);
            $this->assertArrayNotHasKey('liquidity_history', $row['runners'][0]);

            return ! $row['removed'];
        });
    }

    public function test_racing_books_remain_locked_before_the_existing_five_minute_window(): void
    {
        $match = array_replace($this->match(), ['timestamp' => time() + 600]);

        $this->assertSame([], app(MarketActivityStream::class)->markets('Greyhounds', 'match-a', $match));
        $rows = app(MarketActivityStream::class)->markets('Football', 'match-a', array_replace($match, ['result_status' => 'Live']));
        $this->assertTrue($rows[0]['removed']);
    }

    public function test_large_books_are_fragmented_without_dropping_runners_or_exceeding_reverb_limits(): void
    {
        $match = $this->match();
        $match['markets']['123_WIN'] = array_fill(0, 120, $match['markets']['123_WIN'][0]);
        $service = app(MarketActivityStream::class);
        $market = $service->markets('Football', 'match-a', $match)[0];

        $packets = $service->packets($market);

        $this->assertCount(12, $packets);
        $this->assertCount(120, array_merge(...array_column($packets, 'runners')));
        $this->assertCount(1, array_unique(array_column($packets, 'revision')));
        foreach ($packets as $packet) {
            $this->assertLessThan(8500, strlen(json_encode(json_encode(['markets' => [$packet]]))));
        }
    }

    public function test_market_channel_requires_an_active_verified_customer_and_valid_sport(): void
    {
        $this->withoutMiddleware(CustomerAuthService::class);
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        require base_path('routes/channels.php');
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-sports.football.market-activity', 'socket_id' => '123.456'])->assertOk();
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-sports.unknown.market-activity', 'socket_id' => '123.456'])->assertForbidden();
        $customer->update(['trial_ends_at' => now()->subDay()]);
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-sports.football.market-activity', 'socket_id' => '123.456'])->assertForbidden();
    }

    public function test_old_source_jobs_are_not_sent_as_current_trading_signals_when_the_worker_recovers(): void
    {
        config(['services.telegram.enabled' => true]);
        $alerts = \Mockery::mock(AlertService::class);
        $alerts->shouldNotReceive('source');
        $job = new ProcessAlert('source', ['message_id' => 12, 'received_at' => now()->subDay()->timestamp]);

        $job->handle($alerts);
    }

    public function test_fresh_source_jobs_are_processed_on_worker_recovery(): void
    {
        config(['services.telegram.enabled' => true]);
        $data = ['message_id' => 12, 'received_at' => now()->subSeconds(10)->timestamp];
        $alerts = \Mockery::mock(AlertService::class);
        $alerts->shouldReceive('source')->once()->with($data)->andReturn(123);

        (new ProcessAlert('source', $data))->handle($alerts);
    }

    public function test_selected_quote_endpoint_checks_access_and_returns_a_private_current_snapshot(): void
    {
        $this->withoutMiddleware(CustomerAuthService::class);
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        $quote = ['matchKey' => 'off-page', 'marketKey' => '123_WIN', 'runners' => [['backSize' => 400]]];
        $this->mock(MarketActivityStream::class)->shouldReceive('quote')->once()->with('Football', 'off-page', '123_WIN')->andReturn($quote);
        $query = ['sport' => 'football', 'match_key' => 'off-page', 'market_key' => '123_WIN'];

        $response = $this->getJson('/market-activity/quote?'.http_build_query($query));

        $response->assertJsonPath('markets.0', $quote)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/market-activity/quote?'.http_build_query(array_replace($query, ['sport' => 'invalid'])))->assertUnprocessable();
        $customer->update(['trial_ends_at' => now()->subDay()]);
        $this->getJson('/market-activity/quote?'.http_build_query($query))->assertForbidden();
    }

    #[DataProvider('sports')]
    public function test_all_market_pages_and_refresh_fragments_use_the_current_case_sensitive_shared_template(string $prefix): void
    {
        $this->withoutMiddleware(CustomerAuthService::class);
        $this->withoutVite();
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        $files = new Filesystem;
        $strictFiles = \Mockery::mock(Filesystem::class)->makePartial();
        $strictFiles->shouldReceive('exists')->andReturnUsing(static fn (string $path): bool => ! preg_match('~/users/sports/(football|cricket|icehockey|americanfootball)/marketActivity\.blade\.php$~', $path) && $files->exists($path));
        app('view')->setFinder(new FileViewFinder($strictFiles, [resource_path('views')]));
        config(['cache.stores.redis.driver' => 'array']);
        Cache::purge('redis');
        Redis::shouldReceive('get')->andReturn(null);
        $controller = 'App\\Http\\Controllers\\Users\\'.$prefix.'\\'.$prefix.'Controller';
        $this->partialMock($controller, function ($mock): void {
            $mock->shouldReceive('getMarketActivites')->andReturn([]);
            $mock->shouldReceive('getAllMarkets')->andReturn(['options' => []]);
        });
        $sport = MarketActivityStream::SPORTS[$prefix];

        $url = route($sport.'.market-activity', [], false);
        $response = $this->get($url)->assertOk()->assertSee('data-market-ui="selected-market-v2"', false)->assertSee('function refreshSelectedMarket()', false)->assertSee('function paintMarketDetail(event)', false);
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $tabs = $xpath->query('//*[@id="sportSelector"][@data-market-activity-tabs]/a');
        $this->assertCount(7, $tabs);
        foreach (['football', 'tennis', 'basketball', 'cricket', 'icehockey', 'americanfootball', 'baseball'] as $tab) {
            $this->assertSame(route($tab.'.market-activity'), $xpath->evaluate('string(//*[@id="sportSelector"]/a[@data-sport="'.$tab.'"]/@href)'));
        }
        $this->getJson($url.'?_market_rows=1')->assertOk()->assertJsonStructure(['html', 'has_more', 'next_page']);
        $this->getJson($url.'?_market_rows=1&_market_switch=1')->assertOk()->assertSee('data-market-options');
    }

    public function test_market_activity_includes_betfair_competitions_excluded_from_fixtures(): void
    {
        $match = $this->match() + ['sportsApiPro_match_id' => 123, 'team1' => 'Home', 'team2' => 'Away',
            'tournament' => 'Unlisted Regional Cup', 'tournament_country' => 'England', 'data_sync_complete' => true];
        config(['services.football_fixture_index' => false, 'services.football_match_cache' => false]);
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Football:matches' ? json_encode(['match-a' => $match]) : null);
        $controller = app(FootballController::class);
        $this->app->instance('request', Request::create('/football/market-activity', 'GET'));

        $rows = $controller->getMarketActivites();

        $this->assertSame(['match-a'], array_column($rows, 'streamMatchKey'));
        $this->assertFalse(FootballSyncController::allowsFootballCompetition($match));
        $this->app->instance('request', Request::create('/football'));
        $this->assertSame([], $controller->getFootballMatches());
    }

    public function test_football_market_activity_sorts_kickoff_before_league_priority_and_pagination(): void
    {
        $now = time();
        $base = $this->match() + ['sportsApiPro_match_id' => 1, 'team1' => 'Home', 'team2' => 'Away', 'tournament_country' => 'England', 'data_sync_complete' => true];
        $matches = [
            'far' => array_replace($base, ['unique_key' => 'far', 'timestamp' => $now + 2974 * 3600, 'tournament' => 'UEFA Nations League', 'tournament_country' => 'UEFA']),
            'soon' => array_replace($base, ['unique_key' => 'soon', 'sportsApiPro_match_id' => 2, 'betfair_match_id' => 124, 'timestamp' => $now + 3600, 'tournament' => 'Premier League']),
            'tomorrow' => array_replace($base, ['unique_key' => 'tomorrow', 'sportsApiPro_match_id' => 3, 'betfair_match_id' => 125, 'timestamp' => $now + 86400, 'tournament' => 'Premier League']),
        ];
        config(['services.football_fixture_index' => false, 'services.football_match_cache' => false]);
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Football:matches' ? json_encode($matches) : null);
        $this->app->instance('request', Request::create('/football/market-activity', 'GET', ['per_page' => 1]));

        $rows = app(FootballController::class)->getMarketActivites();

        $this->assertCount(1, $rows);
        $this->assertSame('soon', $rows[0]['streamMatchKey']);
    }

    #[DataProvider('teamSports')]
    public function test_market_start_window_filters_all_fixtures_before_pagination_and_keeps_future_days(string $prefix): void
    {
        $sport = MarketActivityStream::SPORTS[$prefix];
        $now = time();
        $base = $this->match() + ['sportsApiPro_match_id' => 1, 'team1' => 'Home', 'team2' => 'Away', 'tournament_country' => 'England', 'data_sync_complete' => true];
        $base['tournament'] = $prefix === 'Tennis' ? 'ATP Finals Turin' : 'Premier League';
        $matches = [];
        foreach (['far' => 90 * 86400, 'soon' => 2700, 'later' => 5 * 3600, 'tomorrow' => 36 * 3600] as $key => $offset) {
            $matches[$key] = array_replace($base, ['unique_key' => $key, 'sportsApiPro_match_id' => count($matches) + 1, 'betfair_match_id' => count($matches) + 101, 'timestamp' => $now + $offset]);
        }
        config(['services.football_fixture_index' => false, 'services.'.$sport.'_match_cache' => false]);
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === $prefix.':matches' ? json_encode($matches) : null);
        $controller = app('App\\Http\\Controllers\\Users\\'.$prefix.'\\'.$prefix.'Controller');
        $this->app->instance('request', Request::create('/'.$sport.'/market-activity', 'GET', ['start_seconds' => 3600, 'per_page' => 1]));

        $rows = $controller->getMarketActivites();

        $this->assertCount(1, $rows);
        $this->assertSame('soon', $rows[0]['streamMatchKey']);
        $this->app->instance('request', Request::create('/'.$sport.'/market-activity', 'GET', ['start_seconds' => 21600, 'per_page' => 100]));
        $this->assertSame(['later'], array_column($controller->getMarketActivites(), 'streamMatchKey'));
        $this->app->instance('request', Request::create('/'.$sport.'/market-activity', 'GET', ['per_page' => 100]));
        $this->assertSame(['soon', 'later', 'tomorrow'], array_column($controller->getMarketActivites(), 'streamMatchKey'));
    }

    #[DataProvider('teamSports')]
    public function test_time_intervals_filter_before_pagination_in_every_sport(string $prefix): void
    {
        $sport = MarketActivityStream::SPORTS[$prefix];
        $base = $this->match() + ['team1' => 'Home', 'team2' => 'Away', 'tournament' => 'Unknown Cup', 'data_sync_complete' => false];
        $matches = [];
        foreach ([21600 => 16200, 10800 => 9000, 7200 => 5400, 3600 => 2700, 1800 => 1500, 1200 => 900, 600 => 300] as $window => $remaining) {
            $key = 'window-'.$window;
            $matches[$key] = array_replace($base, ['unique_key' => $key, 'betfair_match_id' => $window, 'timestamp' => time() + $remaining]);
        }
        config(['services.football_fixture_index' => false, 'services.'.$sport.'_match_cache' => false]);
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === $prefix.':matches' ? json_encode($matches) : null);
        $controller = app('App\\Http\\Controllers\\Users\\'.$prefix.'\\'.$prefix.'Controller');

        foreach ([600, 1200, 1800, 3600, 7200, 10800, 21600] as $window) {
            $this->app->instance('request', Request::create('/'.$sport.'/market-activity', 'GET', ['start_seconds' => $window, 'per_page' => 1]));
            $this->assertSame(['window-'.$window], array_column($controller->getMarketActivites(), 'streamMatchKey'));
        }
    }

    #[TestWith([600, 0, 600])]
    #[TestWith([1200, 600, 1200])]
    #[TestWith([1800, 1200, 1800])]
    #[TestWith([3600, 1800, 3600])]
    #[TestWith([7200, 3600, 7200])]
    #[TestWith([10800, 7200, 10800])]
    #[TestWith([21600, 10800, 21600])]
    #[TestWith([0, 0, 172800])]
    public function test_interval_boundaries_do_not_overlap(int $window, int $minimum, int $maximum): void
    {
        $this->app->instance('request', Request::create('/football/market-activity', 'GET', ['start_seconds' => $window]));

        $this->assertFalse(MarketActivityTiming::matchesStartWindow($minimum, 0));
        $this->assertTrue(MarketActivityTiming::matchesStartWindow($minimum + 1, 0));
        $this->assertTrue(MarketActivityTiming::matchesStartWindow($maximum, 0));
        $this->assertFalse(MarketActivityTiming::matchesStartWindow($maximum + 1, 0));
    }

    public function test_georgian_market_controls_use_compact_labels_and_headers(): void
    {
        $this->withoutMiddleware(CustomerAuthService::class);
        $this->withoutVite();
        app()->setLocale('ka');
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        config(['cache.stores.redis.driver' => 'array']);
        Cache::purge('redis');
        Redis::shouldReceive('get')->andReturn(null);
        $this->partialMock(FootballController::class, function ($mock): void {
            $mock->shouldReceive('getMarketActivites')->andReturn([]);
            $mock->shouldReceive('getAllMarkets')->andReturn(['options' => []]);
        });

        $response = $this->get(route('football.market-activity', [], false));

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $buttons = $xpath->query('//*[@id="timeToStart"]/button');
        $this->assertSame(['10 წთ-ში', '20 წთ-ში', '30 წთ-ში', '1 სთ-ში', '2 სთ-ში', '3 სთ-ში', '6 სთ-ში', 'ყველა (48 სთ)'], array_map(static fn ($button): string => trim($button->textContent), iterator_to_array($buttons)));
        $this->assertSame('იწყება:', trim($xpath->evaluate('string(//*[@id="timeToStart"]/span)')));
        $this->assertSame('რყევა', trim($xpath->evaluate('string(//*[@id="eventsWrap"]//thead/tr/th[13])')));
        $this->assertSame('იმპ.', trim($xpath->evaluate('string(//*[@id="eventsWrap"]//thead/tr/th[11])')));
    }

    #[DataProvider('teamSports')]
    public function test_all_betfair_leagues_within_48_hours_are_visible_without_sportsapi_matching(string $prefix): void
    {
        $sport = MarketActivityStream::SPORTS[$prefix];
        $base = $this->match() + ['team1' => 'Home', 'team2' => 'Away', 'tournament_country' => 'England'];
        $base['tournament'] = 'Unlisted Regional Cup';
        $base['data_sync_complete'] = false;
        $matches = [];
        foreach (['outside' => 49 * 3600, 'tomorrow' => 36 * 3600, 'soon' => 1200, 'started' => -60] as $key => $offset) {
            $matches[$key] = array_replace($base, ['unique_key' => $key, 'betfair_match_id' => count($matches) + 101, 'timestamp' => time() + $offset]);
        }
        if ($prefix === 'Tennis') {
            $matches['tomorrow']['tournament'] = 'Wimbledon';
        }
        config(['services.football_fixture_index' => false, 'services.'.$sport.'_match_cache' => false]);
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === $prefix.':matches' ? json_encode($matches) : null);
        $controller = app('App\\Http\\Controllers\\Users\\'.$prefix.'\\'.$prefix.'Controller');
        $this->app->instance('request', Request::create('/'.$sport.'/market-activity', 'GET', ['per_page' => 100, 'start_seconds' => 9999999]));

        $rows = $controller->getMarketActivites();

        $this->assertSame(['soon', 'tomorrow'], array_column($rows, 'streamMatchKey'));
        $this->app->instance('request', Request::create('/'.$sport));
        $this->assertSame([], $controller->{'get'.$prefix.'Matches'}());
    }

    #[TestWith([0, false])]
    #[TestWith([1, true])]
    #[TestWith([172800, true])]
    #[TestWith([172801, false])]
    public function test_market_horizon_has_an_inclusive_48_hour_limit(int $timestamp, bool $visible): void
    {
        $this->assertSame($visible, MarketActivityTiming::withinHorizon($timestamp, 0));
    }

    public function test_websocket_quotes_remove_matches_outside_the_48_hour_window(): void
    {
        $match = array_replace($this->match(), ['timestamp' => time() + 49 * 3600]);

        $quotes = app(MarketActivityStream::class)->markets('Football', 'match-a', $match);

        $this->assertTrue($quotes[0]['removed']);
        $this->assertSame([], $quotes[0]['runners']);
    }

    #[TestWith([1, 'in 1 min'])]
    #[TestWith([3660, 'in 1h 1m'])]
    #[TestWith([86340, 'in 23h 59m'])]
    #[TestWith([86400, 'in 1d'])]
    #[TestWith([172800, 'in 2d'])]
    #[TestWith([10706820, 'in 123d 22h'])]
    public function test_market_countdown_uses_days_after_twenty_four_hours(int $timestamp, string $label): void
    {
        $this->assertSame($label, MarketActivityTiming::until($timestamp, 0));
    }

    public function test_indexed_market_activity_filters_and_sorts_before_reading_the_selected_fixture(): void
    {
        $base = $this->match() + ['sportsApiPro_match_id' => 1, 'team1' => 'Home', 'team2' => 'Away', 'tournament' => 'Unlisted Regional Cup', 'tournament_country' => 'England'];
        $current = [];
        $entries = [];
        foreach (['far' => 100 * 86400, 'soon' => 4 * 3600, 'outside' => 7 * 3600] as $key => $offset) {
            $current[$key] = array_replace($base, ['unique_key' => $key, 'timestamp' => time() + $offset]);
            $entries[$key] = array_diff_key($current[$key], ['markets' => true]);
            $entries[$key]['_market_activity'] = prepareMatchOddsMonitoring($current[$key], marketActivitySummary: true);
        }
        $index = ['market_activity' => 1, 'matches' => $entries, 'ids' => []];
        $cache = \Mockery::mock(FootballMatchCache::class);
        $cache->shouldReceive('active')->andReturnTrue();
        $cache->shouldReceive('index')->once()->andReturn($index);
        $cache->shouldReceive('read')->once()->with(null, ['soon'], $index)->andReturn(['soon' => $current['soon']]);
        $this->app->instance(FootballMatchCache::class, $cache);
        Redis::shouldReceive('get')->andReturn(null);
        Redis::shouldReceive('get')->with('Football:matches')->never();
        $this->app->instance('request', Request::create('/football/market-activity', 'GET', ['start_seconds' => 21600, 'per_page' => 1]));

        $rows = app(FootballController::class)->getMarketActivites();

        $this->assertSame(['soon'], array_column($rows, 'streamMatchKey'));
    }

    private function match(): array
    {
        return ['unique_key' => 'match-a', 'betfair_match_id' => 123, 'result_status' => 'Prematch', 'timestamp' => time() + 120,
            'markets' => ['123_WIN' => [
                ['runnerId' => 1, 'runner' => 'Favorite', 'marketType' => 'WIN', 'backOdds' => 1.8, 'layOdds' => 1.82,
                    'backSize' => 1600.0, 'laySize' => 40.72, 'liquidity_updated_at' => microtime(true),
                    'backOddsHistory' => [['odds' => 2.0, 'timestamp' => time() - 600]],
                    'liquidity_history' => [['back' => 30, 'lay' => 10, 'timestamp' => time() - 310], ['back' => 40, 'lay' => 10, 'timestamp' => time() - 70]]],
                ['runnerId' => 2, 'runner' => 'Away', 'marketType' => 'WIN', 'backOdds' => 3.0, 'layOdds' => 3.1,
                    'backSize' => 50.0, 'laySize' => 100.0, 'liquidity_updated_at' => microtime(true)],
            ]]];
    }
}
