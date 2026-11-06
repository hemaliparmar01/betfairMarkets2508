<?php

namespace Tests\Feature;

use App\Console\Commands\ListenSportsApiPro;
use App\Http\Controllers\Users\Football\FootballController;
use App\Http\Controllers\Users\Football\FootballSyncController;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\PredictionVote;
use App\Services\CustomerAuthService;
use App\Traits\FootballTrait;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FixturesAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.football_fixture_index' => false]);
    }

    public function test_market_pressure_uses_resting_order_interest_and_rolling_percentage_points(): void
    {
        $now = floor(microtime(true)) - 1;
        $runner = [];
        for ($offset = -920; $offset <= 0; $offset += 10) {
            $backInterest = $offset <= -800 ? 20 : ($offset <= -300 ? 40 : ($offset <= -60 ? 80 : 60));
            $runner = updateOddsMonitoring(['marketId' => '456', 'runnerId' => 11, 'backOdds' => 2.0, 'layOdds' => 2.02, 'backSize' => 100 - $backInterest, 'laySize' => $backInterest], $runner, $now + $offset);
        }

        $match = prepareMatchOddsMonitoring(['result_status' => 'Prematch', 'markets' => ['456_MATCH_ODDS' => [$runner]]], true);
        $indicator = $match['market_indicators']['456_MATCH_ODDS'];

        $this->assertSame(60.0, $indicator['back_percent']);
        $this->assertSame(40.0, $indicator['lay_percent']);
        $this->assertSame('back', $indicator['balance']);
        $this->assertSame('strong_lay', $indicator['momentum']);
        $this->assertSame([60 => -20.0, 300 => 20.0, 900 => 40.0], $indicator['delta_windows']);
        $this->assertSame(20.0, $indicator['delta']);
        $this->assertLessThanOrEqual(93, count($runner['liquidity_history']));
        $this->assertSame([['timestamp' => (int) ($now - 920), 'odds' => 2.0]], $runner['backOddsHistory']);
        $this->assertArrayNotHasKey('matched', $runner);
    }

    public function test_market_pressure_handles_empty_one_sided_invalid_stale_and_suspended_books(): void
    {
        $now = microtime(true);
        $quote = ['backOdds' => 2.0, 'layOdds' => 2.02, 'backSize' => 0, 'laySize' => 100];
        $runner = updateOddsMonitoring($quote, [], $now);
        $match = ['result_status' => 'Prematch', 'markets' => ['market' => [$runner]]];
        $indicator = prepareMatchOddsMonitoring($match, true)['market_indicators']['market'];
        $this->assertSame(100.0, $indicator['back_percent']);
        $this->assertSame('back', $indicator['balance']);
        $this->assertNull($indicator['delta']);
        $this->assertSame('unknown', $indicator['momentum']);

        foreach ([['backSize' => 0, 'laySize' => 0], ['backSize' => -1], ['laySize' => 'bad']] as $changes) {
            $invalid = updateOddsMonitoring(array_replace($quote, $changes), $runner, $now + 1);
            $indicator = prepareMatchOddsMonitoring(['markets' => ['market' => [$invalid]]], true)['market_indicators']['market'];
            $this->assertNull($indicator['back_percent']);
            $this->assertSame('unknown', $indicator['balance']);
            $this->assertNull($indicator['delta']);
        }

        $stale = array_replace($runner, ['liquidity_updated_at' => $now - 76]);
        $indicator = prepareMatchOddsMonitoring(['markets' => ['market' => [$stale]]], true)['market_indicators']['market'];
        $this->assertTrue($indicator['stale']);
        $this->assertSame('unknown', $indicator['balance']);
        $this->assertNull($indicator['delta']);
        $indicator = prepareMatchOddsMonitoring(array_replace($match, ['market_status' => 'SUSPENDED']), true)['market_indicators']['market'];
        $this->assertFalse($indicator['active']);
        $this->assertSame('unknown', $indicator['balance']);
    }

    public function test_team_market_volatility_uses_existing_football_thresholds_and_preserves_racing_values(): void
    {
        $now = microtime(true);
        $quote = ['backOdds' => 2.0, 'layOdds' => 2.02, 'backSize' => 100, 'laySize' => 100];
        $first = updateOddsMonitoring($quote, [], $now);
        foreach ([['price' => 2.0, 'volatility' => 'low'], ['price' => 1.9, 'volatility' => 'medium'], ['price' => 1.7, 'volatility' => 'high']] as $case) {
            $runner = updateOddsMonitoring(array_replace($quote, ['backOdds' => $case['price'], 'layOdds' => $case['price'] + 0.02]), $first, $now + 1);
            $result = prepareMatchOddsMonitoring(['markets' => ['market' => [$runner]]], true);
            $this->assertSame($case['volatility'], $result['market_indicators']['market']['volatility']);
        }
        $result = prepareMatchOddsMonitoring(['markets' => ['market' => [$first]], 'market_indicators' => ['market' => ['volatility' => 'high']]], true);
        $this->assertSame('high', $result['market_indicators']['market']['volatility']);
        $result = prepareMatchOddsMonitoring(['markets' => ['market' => [['backOdds' => 2.0, 'backSize' => 100, 'laySize' => 100]]]], true);
        $this->assertSame('unknown', $result['market_indicators']['market']['volatility']);
    }

    public function test_liquidity_history_is_bounded_rejects_old_quotes_and_resets_after_a_gap_or_selection_change(): void
    {
        $quote = ['marketId' => '456', 'runnerId' => 11, 'backOdds' => 2.0, 'layOdds' => 2.02, 'backSize' => 25, 'laySize' => 75];
        $runner = [];
        for ($second = 0; $second <= 3600; $second++) {
            $runner = updateOddsMonitoring($quote, $runner, 1000.0 + $second);
        }
        $this->assertLessThanOrEqual(93, count($runner['liquidity_history']));
        $this->assertSame($runner, updateOddsMonitoring(array_replace($quote, ['laySize' => 1]), $runner, 4599.0));
        $this->assertSame($runner['liquidity_history'], updateOddsMonitoring($quote, $runner, 4600.0)['liquidity_history']);
        $recovered = updateOddsMonitoring($quote, $runner, 4680.0);
        $this->assertCount(1, $recovered['liquidity_history']);
        $changed = updateOddsMonitoring(array_replace($quote, ['runnerId' => 12]), $runner, 4601.0);
        $this->assertCount(1, $changed['liquidity_history']);
        $partial = updateOddsMonitoring(['marketId' => '456', 'runnerId' => 11, 'backSize' => 50], $runner, 4601.0);
        $this->assertSame(75.0, $partial['liquidity_history'][array_key_last($partial['liquidity_history'])]['back']);
        $this->assertSame(50.0, $partial['liquidity_history'][array_key_last($partial['liquidity_history'])]['lay']);
    }

    public function test_a_fresh_rest_quote_does_not_inherit_an_old_websocket_session(): void
    {
        $now = microtime(true);
        $quote = ['marketId' => '456', 'runnerId' => 11, 'backOdds' => 2.0, 'layOdds' => 2.02, 'backSize' => 60, 'laySize' => 40];
        $previous = updateOddsMonitoring($quote + ['liquidity_session' => $now - 1000, 'liquidity_feed' => 'prematch'], [], $now - 61);

        $fresh = updateOddsMonitoring(array_replace($quote, ['backSize' => 26, 'laySize' => 74]), $previous, $now - 1);
        $indicator = prepareMatchOddsMonitoring(['markets' => ['market' => [$fresh]]], true)['market_indicators']['market'];

        $this->assertArrayNotHasKey('liquidity_session', $fresh);
        $this->assertArrayNotHasKey('liquidity_feed', $fresh);
        $this->assertFalse($indicator['stale']);
        $this->assertSame('strong_back', $indicator['momentum']);
        $this->assertSame(34.0, $indicator['delta_windows'][60]);
        $this->assertSame('low', $indicator['volatility']);
    }

    private function football(): object
    {
        return new class
        {
            use FootballTrait;
        };
    }

    private function sync(): FootballSyncController
    {
        return (new ReflectionClass(FootballSyncController::class))->newInstanceWithoutConstructor();
    }

    private function timeline(array $match): array
    {
        $trait = $this->football();

        return (new ReflectionMethod($trait, 'footballTimeline'))->invoke($trait, $match, $match['timeline'] ?? []);
    }

    public function test_timeline_read_fallback_retains_cached_matches_and_logs_the_actual_error(): void
    {
        $matches = ['match' => ['sportsApiPro_match_id' => '17365784', 'home_score' => 4, 'away_score' => 2]];
        $error = new \Error('Timeline read failed');
        Redis::shouldReceive('get')->once()->with('Football:matches')->andReturn(json_encode($matches));
        DB::shouldReceive('table')->once()->with('football_timeline')->andThrow($error);
        Log::shouldReceive('warning')->once()->with('Football timeline read fallback unavailable', [
            'exception' => \Error::class,
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);

        $result = getInRedis('Football:matches');

        $this->assertSame($matches, $result);
    }

    public function test_read_only_fixture_cache_access_never_restores_or_writes_timelines(): void
    {
        $matches = ['match' => ['sportsApiPro_match_id' => '123', 'timeline' => ['home_goals' => []]]];
        Redis::shouldReceive('get')->once()->with('Football:matches')->andReturn(json_encode($matches));
        Redis::shouldReceive('eval')->never();
        Redis::shouldReceive('set')->never();
        DB::shouldReceive('table')->never();

        $this->assertSame($matches, getInRedis('Football:matches', readOnly: true));
    }

    public function test_timeline_cache_fallback_preserves_the_cache_write_and_logs_the_actual_error(): void
    {
        $matches = ['match' => ['sportsApiPro_match_id' => '17365784', 'home_score' => 4, 'away_score' => 2]];
        $error = new \Error('Timeline cache fallback failed');
        $sync = \Mockery::mock(FootballSyncController::class)->makePartial();
        $sync->shouldReceive('storeTimeline')->once()->andReturn(false);
        $this->app->instance(FootballSyncController::class, $sync);
        Redis::shouldReceive('get')->once()->with('Football:matches')->andReturn(json_encode($matches));
        Redis::shouldReceive('set')->once()->with('Football:matches', json_encode($matches));
        Redis::shouldReceive('expire')->once()->with('Football:matches', 600);
        DB::shouldReceive('table')->once()->with('football_timeline')->andThrow($error);
        Log::shouldReceive('warning')->once()->with('Football timeline cache fallback unavailable', [
            'exception' => \Error::class,
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);

        storeInRedis('Football:matches', $matches, mergeLifecycle: false);
    }

    public function test_unsupported_smart_filters_return_empty_without_loading_cache_or_provider(): void
    {
        foreach (['Basketball', 'IceHockey', 'AmericanFootball', 'Baseball', 'Tennis', 'Cricket', 'HorseRacing', 'Greyhounds'] as $sport) {
            $class = 'App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'Controller';
            $instance = (new ReflectionClass($class))->newInstanceWithoutConstructor();
            $method = array_find(get_class_methods($instance), static fn (string $name): bool => preg_match('/^get\w+Matches$/', $name) === 1);
            foreach (['ht00', '00_70'] as $filter) {
                $this->app->instance('request', Request::create('/', 'GET', ['type' => $filter]));
                $this->assertSame([], $instance->$method());
            }
        }
    }

    public function test_live_upcoming_finished_and_guest_favorites_return_actual_matching_rows_across_sports(): void
    {
        Schema::create('prediction_votes', function (Blueprint $table): void {
            $table->id();
            $table->string('sport');
            $table->string('match_id');
            $table->string('poll');
            $table->string('value');
            $table->integer('customer_id')->nullable();
        });
        $cache = [];
        Redis::shouldReceive('get')->andReturnUsing(static function (string $key) use (&$cache): ?string {
            return $cache[$key] ?? null;
        });
        foreach (['Football', 'Basketball', 'IceHockey', 'AmericanFootball', 'Baseball', 'Tennis', 'Cricket'] as $sport) {
            $base = ['unique_key' => 'live', 'timestamp' => time() - 600, 'result_status' => 'Live', 'minutes_status' => 'Live', 'sportsApiPro_match_id' => '101', 'betfair_match_id' => '201', 'data_sync_complete' => true, 'tournament' => 'Test League', 'team1' => 'Home', 'team2' => 'Away', 'home_score' => 1, 'away_score' => 0, 'markets' => []];
            $cache[$sport.':matches'] = json_encode([
                'live' => $base,
                'upcoming' => array_replace($base, ['unique_key' => 'upcoming', 'sportsApiPro_match_id' => '102', 'timestamp' => time() + ($sport === 'Football' ? 172800 : 120), 'result_status' => 'Prematch', 'minutes_status' => 'Prematch']),
                'finished' => array_replace($base, ['unique_key' => 'finished', 'sportsApiPro_match_id' => '103', 'result_status' => 'Finished']),
                'hidden' => array_replace($base, ['unique_key' => 'hidden', 'sportsApiPro_match_id' => '104', 'timestamp' => time() - 86400, 'internal_backup' => ['since' => time()]]),
            ]);
            $class = 'App\\Http\\Controllers\\Users\\'.$sport.'\\'.$sport.'Controller';
            $controller = (new ReflectionClass($class))->newInstanceWithoutConstructor();
            $method = 'get'.$sport.'Matches';
            foreach (['live' => '101', 'upcoming' => '102', 'finished' => '103', 'favorites' => null] as $filter => $expectedId) {
                $request = Request::create('/', 'GET', ['type' => $filter]);
                $this->app->instance('request', $request);
                $groups = $controller->$method();
                $ids = [];
                foreach ($groups as $group) {
                    foreach ($group['matches'] as $match) {
                        $ids[] = (string) $match['sportsApiPro_match_id'];
                    }
                }
                $this->assertSame($expectedId === null ? [] : [$expectedId], $ids, $sport.' '.$filter);
                if ($expectedId !== null) {
                    $viewName = 'users.sports.'.strtolower($sport).'.'.strtolower($sport).'Details';
                    $html = view($viewName, ['data' => $groups])->render();
                    if ($sport === 'Football') {
                        $locale = app()->getLocale();
                        foreach (['en', 'ka'] as $language) {
                            app()->setLocale($language);
                            $localizedHtml = view($viewName, ['data' => $groups])->render();
                            $document = new \DOMDocument;
                            @$document->loadHTML('<?xml encoding="UTF-8">'.$localizedHtml);
                            $xpath = new \DOMXPath($document);
                            $status = ['live' => 'Live', 'upcoming' => 'Prematch', 'finished' => 'Finished'][$filter];
                            $this->assertSame($status, $xpath->evaluate('string(//*[@data-match-status]/@data-match-status)'), $language.' '.$filter);
                            $scores = $xpath->query('//*[@data-score-status]');
                            $this->assertGreaterThan(0, $scores->length);
                            foreach ($scores as $score) {
                                $this->assertSame($status, $score->getAttribute('data-score-status'), $language.' '.$filter);
                            }
                        }
                        app()->setLocale($locale);
                    }
                    preg_match_all('/<!--bf-fixture:([a-f0-9]{16}):([a-f0-9]{16})-->/', $html, $markers, PREG_SET_ORDER);
                    $this->assertCount(1, $markers, $sport.' fixture marker');
                    $versions = array_column($markers, 2, 1);
                    $request->merge(['_fixture_versions' => $versions]);
                    $patch = view($viewName, ['data' => $groups])->render();
                    $this->assertStringContainsString('<!--bf-fixture-reuse-->', $patch, $sport);
                    $this->assertStringNotContainsString('class="event-row', $patch, $sport);
                    $changed = $groups;
                    $groupKey = array_key_first($changed);
                    $changed[$groupKey]['matches'][0]['home_score'] = 9;
                    $changed[$groupKey]['matches'][0]['score'] = '9 - 0';
                    $updated = view($viewName, ['data' => $changed])->render();
                    $this->assertStringNotContainsString('<!--bf-fixture-reuse-->', $updated, $sport.' changed row');
                    if ($sport === 'Football' && $filter === 'live') {
                        $locale = app()->getLocale();
                        app()->setLocale('ka');
                        $this->assertStringNotContainsString('<!--bf-fixture-reuse-->', view($viewName, ['data' => $groups])->render());
                        app()->setLocale($locale);
                        $this->assertStringNotContainsString('<!--bf-fixture-reuse-->', view($viewName, ['data' => $groups, 'checkAuthAndSubscription' => ['is_authenticated' => true, 'has_active_subscription' => true, 'display_odds' => true]])->render());
                    }
                    $directory = getenv('BFMARKETS_TEST_BROWSER_DIR');
                    if ($sport === 'Basketball' && $filter === 'live' && $directory && str_starts_with($directory, '/private/tmp/bfmarkets-sports-tests-')) {
                        $request->merge(['_fixture_versions' => []]);
                        $two = $groups;
                        $second = $two[$groupKey]['matches'][0];
                        $second['unique_key'] = 'second';
                        $second['sportsApiPro_match_id'] = '103';
                        $second['team1'] = 'Second Home';
                        $two[$groupKey]['matches'][] = $second;
                        $initial = view($viewName, ['data' => $two])->render();
                        preg_match_all('/<!--bf-fixture:([a-f0-9]{16}):([a-f0-9]{16})-->/', $initial, $twoMarkers, PREG_SET_ORDER);
                        $request->merge(['_fixture_versions' => array_column($twoMarkers, 2, 1)]);
                        $same = view($viewName, ['data' => $two])->render();
                        $two[$groupKey]['matches'][0]['score'] = '9 - 0';
                        $two[$groupKey]['matches'][0]['home_score'] = 9;
                        $different = view($viewName, ['data' => $two])->render();
                        file_put_contents($directory.'/fixture-responses.json', json_encode(['initial' => $initial, 'same' => $same, 'different' => $different], JSON_THROW_ON_ERROR));
                    }

                    $request->merge(['_fixture_versions' => []]);
                }
            }
        }
    }

    public function test_football_filters_keep_same_named_competitions_in_their_own_country_groups(): void
    {
        Schema::create('prediction_votes', function (Blueprint $table): void {
            $table->id();
            $table->string('sport');
            $table->string('match_id');
            $table->string('poll');
            $table->string('value');
        });
        $start = time() - 600;
        $base = ['unique_key' => 'england-1', 'timestamp' => $start, 'result_status' => 'Live', 'minutes_status' => 'Live', 'sportsApiPro_match_id' => '101', 'betfair_match_id' => '201', 'tournament' => 'Premier League', 'tournament_country' => 'England', 'tournament_id' => 17, 'team1' => 'Chelsea', 'team2' => 'Manchester United', 'home_score' => 0, 'away_score' => 0, 'markets' => []];
        $matches = [
            $base,
            array_replace($base, ['unique_key' => 'singapore-1', 'sportsApiPro_match_id' => '102', 'tournament_country' => 'Singapore', 'tournament_id' => 679, 'team1' => 'Hougang United', 'team2' => 'Young Lions']),
            array_replace($base, ['unique_key' => 'england-2', 'sportsApiPro_match_id' => '103', 'timestamp' => $start + 60, 'team1' => 'Arsenal']),
        ];
        $cache = [];
        Redis::shouldReceive('get')->andReturnUsing(static function (string $key) use (&$cache): ?string {
            return $key === 'Football:matches' ? json_encode($cache) : null;
        });

        foreach ([true, false] as $withCompetitionIds) {
            $cache = $matches;
            if (! $withCompetitionIds) {
                foreach ($cache as &$match) {
                    unset($match['tournament_id']);
                }
                unset($match);
            }
            foreach ([null, 'all', 'live', 'by_time'] as $filter) {
                $this->app->instance('request', Request::create('/', 'GET', $filter === null ? [] : ['type' => $filter]));

                $groups = $this->football()->getFootballMatches();

                $this->assertCount($filter === 'by_time' ? 3 : 2, $groups, (string) $filter);
                $countries = [];
                foreach ($groups as $group) {
                    foreach ($group['matches'] as $match) {
                        $countries[$match['unique_key']] = $group['tournament_country'];
                    }
                }
                ksort($countries);
                $this->assertSame(['england-1' => 'England', 'england-2' => 'England', 'singapore-1' => 'Singapore'], $countries);
                if ($filter === 'by_time') {
                    $this->assertSame([$start, $start, $start + 60], array_column(array_merge(...array_column($groups, 'matches')), 'timestamp'));
                }
            }
        }
    }

    public function test_football_league_priorities_follow_all_75_document_entries(): void
    {
        $competitions = [
            ['FIFA', 'FIFA World Cup'],
            ['FIFA', 'FIFA World Cup Qualifiers'],
            ['FIFA', 'FIFA Club World Cup'],
            ['FIFA', 'Other Major FIFA International Competitions'],
            ['UEFA', 'UEFA European Championship (EURO)'],
            ['UEFA', 'EURO Qualifiers'],
            ['UEFA', 'UEFA Champions League'],
            ['UEFA', 'UEFA Champions League Qualifiers'],
            ['UEFA', 'UEFA Europa League'],
            ['UEFA', 'UEFA Europa League Qualifiers'],
            ['UEFA', 'UEFA Conference League'],
            ['UEFA', 'UEFA Conference League Qualifiers'],
            ['UEFA', 'UEFA Nations League'],
            ['UEFA', 'UEFA Super Cup'],
            ['England', 'Premier League'],
            ['Spain', 'La Liga'],
            ['Italy', 'Serie A'],
            ['Germany', 'Bundesliga'],
            ['France', 'Ligue 1'],
            ['Portugal', 'Primeira Liga'],
            ['Netherlands', 'Eredivisie'],
            ['Turkey', 'Süper Lig'],
            ['Belgium', 'Belgian Pro League'],
            ['Scotland', 'Scottish Premiership'],
            ['Greece', 'Super League Greece'],
            ['Austria', 'Austrian Bundesliga'],
            ['Switzerland', 'Swiss Super League'],
            ['Denmark', 'Danish Superliga'],
            ['Norway', 'Eliteserien'],
            ['Sweden', 'Allsvenskan'],
            ['Poland', 'Ekstraklasa'],
            ['Czech Republic', 'Czech First League'],
            ['Croatia', 'Croatian HNL'],
            ['Serbia', 'Serbian SuperLiga'],
            ['Romania', 'Romanian SuperLiga'],
            ['Ukraine', 'Ukrainian Premier League'],
            ['Israel', 'Israeli Premier League'],
            ['England', 'EFL Championship'],
            ['England', 'EFL League One'],
            ['England', 'EFL League Two'],
            ['Spain', 'LaLiga 2'],
            ['Italy', 'Serie B'],
            ['Italy', 'Serie C'],
            ['Germany', '2. Bundesliga'],
            ['Germany', '3. Liga'],
            ['France', 'Ligue 2'],
            ['France', 'Championnat National'],
            ['Portugal', 'Liga Portugal 2'],
            ['Netherlands', 'Eerste Divisie'],
            ['Belgium', 'Challenger Pro League'],
            ['Scotland', 'Scottish Championship'],
            ['Greece', 'Super League 2'],
            ['Austria', '2. Liga'],
            ['Denmark', 'Danish 1st Division'],
            ['Norway', 'OBOS-ligaen'],
            ['Sweden', 'Superettan'],
            ['Poland', 'I Liga'],
            ['Czech Republic', 'Czech National Football League'],
            ['Croatia', 'Croatian First Football League'],
            ['Serbia', 'Serbian First League'],
            ['Switzerland', 'Swiss Challenge League'],
            ['Brazil', 'Brasileirão Série A'],
            ['Argentina', 'Argentine Primera División'],
            ['Mexico', 'Liga MX'],
            ['USA / Canada', 'Major League Soccer (MLS)'],
            ['Saudi Arabia', 'Saudi Pro League'],
            ['Japan', 'J1 League'],
            ['South Korea', 'K League 1'],
            ['China', 'Chinese Super League'],
            ['Australia', 'A-League Men'],
            ['South Africa', 'South African Premier Division'],
            ['Egypt', 'Egyptian Premier League'],
            ['Morocco', 'Botola Pro'],
            ['UAE', 'UAE Pro League'],
            ['Qatar', 'Qatar Stars League'],
        ];
        $football = $this->football();
        $priority = new ReflectionMethod($football, 'footballLeaguePriority');

        $this->assertCount(75, $competitions);
        foreach ($competitions as $rank => [$country, $league]) {
            $this->assertSame($rank + 1, $priority->invoke($football, $league, $country), $country.' / '.$league);
        }
    }

    public function test_football_priority_normalizes_known_country_and_league_aliases_without_level_collisions(): void
    {
        $football = $this->football();
        $priority = new ReflectionMethod($football, 'footballLeaguePriority');
        foreach ([
            ['World', 'World Cup', 1], ['International', 'FIFA Club World Cup', 3],
            ['Europe', 'European Championship', 5], ['Europe', 'European Championship Qualification', 6],
            ['World', 'UEFA Champions League', 7], ['Europe', 'Champions League', 7],
            ['Europe', 'UEFA Champions League, Qualification', 8],
            ['Europe', 'UEFA Europa Conference League', 11], ['Europe', 'Conference League Qualification', 12],
            [' ENG ', ' English  Premier-League ', 15], ['España', 'LaLiga', 16],
            ['ITA', 'Italian Serie A', 17], ['DEU', '1. Bundesliga', 18],
            ['Türkiye', 'Super Lig', 22], ['Austria', 'Bundesliga', 26],
            ['Czechia', '1. Liga', 32], ['Germany', '2. Bundesliga', 44],
            ['Poland', 'I Liga', 57], ['POL', '1. Liga', 57],
            ['Brazil', 'Brasileirao Serie A', 62], ['United States', 'Major League Soccer', 65],
            ['USA', 'MLS', 65], ['Canada', 'MLS', 65], ['Korea Republic', 'K League 1', 68],
            ['United Arab Emirates', 'Pro League', 74],
        ] as [$country, $league, $rank]) {
            $this->assertSame($rank, $priority->invoke($football, $league, $country), $country.' / '.$league);
        }
        foreach ([
            ['Congo', 'Serie A'], ['Austria', 'German Bundesliga'], ['Germany', 'Austrian Bundesliga'],
            ['Singapore', 'Premier League'], ['Scotland', 'English Premier League'],
            ['United Kingdom', 'Premier League'], ['England', 'Premier League 2'],
            ['England', 'Premier League Women'], ['Italy', 'Serie A U19'],
            ['Germany', 'Bundesliga Qualification'], ['Europe', 'Champions League Women'],
            ['World', 'Champions League'], ['UEFA', 'FIFA World Cup'], ['England', 'UEFA Champions League'],
            ['FIFA', 'Copa America'], ['International', 'Unknown FIFA Tournament'],
            ['', 'FIFA World Cup'], [null, 'Serie A'], ['England', ''], ['', ''],
        ] as [$country, $league]) {
            $this->assertSame(PHP_INT_MAX, $priority->invoke($football, $league, $country), ($country ?? 'null').' / '.$league);
        }
    }

    public function test_football_priority_order_survives_filters_pagination_and_websocket_renders_while_by_time_stays_chronological(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        Schema::create('prediction_votes', function (Blueprint $table): void {
            $table->id();
            $table->string('sport');
            $table->string('match_id');
            $table->string('poll');
            $table->string('value');
        });
        $base = ['timestamp' => time() + 60, 'result_status' => 'Live', 'minutes_status' => 'Live', 'sportsApiPro_match_id' => '101', 'betfair_match_id' => '201', 'tournament' => 'Serie A', 'tournament_country' => 'Congo', 'tournament_id' => 1, 'team1' => 'Home', 'team2' => 'Away', 'home_score' => 0, 'away_score' => 0, 'markets' => []];
        $cache = [];
        Redis::shouldReceive('get')->andReturnUsing(static function (string $key) use (&$cache): ?string {
            return $key === 'Football:matches' ? json_encode($cache) : null;
        });
        foreach ([null, 'all', 'live', 'upcoming', 'finished', 'by_time'] as $filter) {
            $cache = [];
            foreach ([['Congo', 'Serie A'], ['Italy', 'Serie A'], ['England', 'Premier League'], ['Europe', 'UEFA Champions League']] as $index => [$country, $league]) {
                $cache[] = array_replace($base, ['unique_key' => 'match-'.$index, 'sportsApiPro_match_id' => (string) (101 + $index), 'tournament_id' => $index + 1, 'tournament' => $league, 'tournament_country' => $country, 'timestamp' => time() + ($index + 1) * 60, 'result_status' => $filter === 'finished' ? 'Finished' : ($filter === 'upcoming' ? 'Prematch' : 'Live')]);
            }
            $request = Request::create('/', 'GET', ['type' => $filter, 'per_page' => 10]);
            $this->app->instance('request', $request);
            $expected = $filter === 'by_time' ? ['Congo', 'Italy', 'England', 'Europe'] : ['Europe', 'England', 'Italy', 'Congo'];

            $this->assertSame($expected, array_column($this->football()->getFootballMatches(), 'tournament_country'), (string) $filter);
            $controller = app(FootballController::class);
            $response = $controller->websocketFootball()->getData(true);
            $this->assertSame(['html', 'has_more', 'next_page'], array_keys($response));
            foreach (['websocketFootball', 'filterFootball'] as $method) {
                $html = $controller->$method()->getData(true)['html'];
                $positions = array_map(static fn (string $country): int|false => strpos($html, $country), $expected);
                $this->assertNotContains(false, $positions);
                $sortedPositions = $positions;
                sort($sortedPositions);
                $this->assertSame($sortedPositions, $positions, $method.' '.($filter ?? 'default'));
            }
            $request->merge(['per_page' => 2]);
            $this->assertSame(array_slice($expected, 0, 2), array_column($this->football()->getFootballMatches(), 'tournament_country'));
            $request->merge(['page' => 2]);
            $this->assertSame(array_slice($expected, 2), array_column($this->football()->getFootballMatches(), 'tournament_country'));
        }
    }

    public function test_football_smart_filters_use_real_scores_existing_thresholds_and_triggered_drop_sources(): void
    {
        Schema::create('prediction_votes', function (Blueprint $table): void {
            $table->id();
            $table->string('sport');
            $table->string('match_id');
            $table->string('poll');
            $table->string('value');
            $table->integer('customer_id')->nullable();
        });
        $customer = new Customer(['trial_ends_at' => now()->addDay()]);
        $this->app['auth']->guard('customers')->setUser($customer);
        $sync = \Mockery::mock(FootballSyncController::class);
        $sync->shouldReceive('storeTimeline')->andReturn(false);
        $this->app->instance(FootballSyncController::class, $sync);
        $base = ['unique_key' => '1', 'timestamp' => time() - 6000, 'result_status' => 'Live', 'minutes_status' => '2nd half', 'current_period_start_timestamp' => time() - 1500, 'sportsApiPro_match_id' => '1', 'betfair_match_id' => '201', 'data_sync_complete' => true, 'tournament' => 'Test League', 'team1' => 'Home', 'team2' => 'Away', 'home_score' => 0, 'away_score' => 0, 'markets' => []];
        $runner = ['runner' => 'Home', 'runnerId' => '1', 'marketType' => 'MATCH_ODDS', 'backOdds' => 1.8, 'initialBackOdds' => 2.0, 'monitoring_baseline_version' => 1, 'opening_odds_source' => 'first_observed', 'initialBackOddsTimestamp' => time() - 1000, 'prematch_drop_triggered' => true];
        $detectedAt = time() - 300;
        $data = [
            '1' => array_replace($base, ['minutes_status' => 'Half Time']),
            '2' => array_replace($base, ['unique_key' => '2', 'sportsApiPro_match_id' => '2', 'markets' => ['m' => [
                array_replace($runner, ['liquidity_feed' => 'live']),
                array_replace($runner, ['runner' => 'Away', 'liquidity_feed' => 'prematch']),
            ]]]),
            '3' => array_replace($base, ['unique_key' => '3', 'sportsApiPro_match_id' => '3', 'away_score' => 1, 'bet365_prematch' => ['favorite_odds' => 1.8, 'favorite_side' => 'home']]),
            '4' => array_replace($base, ['unique_key' => '4', 'sportsApiPro_match_id' => '4', 'bet365_prematch' => ['favorite_odds' => 1.87, 'favorite_side' => 'home']]),
            '5' => array_replace($base, ['unique_key' => '5', 'sportsApiPro_match_id' => '5', 'timestamp' => time() + 120, 'result_status' => 'Prematch', 'markets' => ['m' => [$runner]]]),
            '6' => array_replace($base, ['unique_key' => '6', 'sportsApiPro_match_id' => '6', 'timestamp' => time() + 120, 'result_status' => 'Prematch', 'pinnacle_market_shock_detected_at' => $detectedAt, 'pinnacle_markets' => ['home' => ['sect' => 'moneyline', 'outcome' => 'home', 'baseline_odds' => 2.0, 'current_odds' => 1.8, 'shock_detected_at' => $detectedAt]], 'markets' => ['m' => [
                array_replace($runner, ['prematch_drop_triggered' => false, 'market_shock_detected_at' => $detectedAt]),
                array_replace($runner, ['runner' => 'Away', 'runnerId' => '2', 'prematch_drop_triggered' => false, 'market_shock_detected_at' => time() - 1201]),
                array_replace($runner, ['runner' => 'Draw', 'runnerId' => '3', 'prematch_drop_triggered' => false]),
            ]]]),
            '7' => array_replace($base, ['unique_key' => '7', 'sportsApiPro_match_id' => '7', 'minutes_status' => 'Half Time', 'home_score' => null, 'away_score' => null]),
        ];
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Football:matches' ? json_encode($data) : null);
        Redis::shouldReceive('mget')->andReturn([]);
        Cache::shouldReceive('store')->with('redis')->andReturnSelf();
        Cache::shouldReceive('many')->andReturn([]);
        foreach (['ht00' => ['1'], '00_70' => ['2', '4'], 'fav_losing' => ['3'], 'fav_not_winning' => ['3', '4'], 'prematch_drops' => ['5'], 'market_shock' => ['6']] as $filter => $expected) {
            $request = Request::create('/', 'GET', ['type' => $filter]);
            $this->app->instance('request', $request);
            $groups = $this->football()->getFootballMatches();
            $ids = [];
            foreach ($groups as $group) {
                foreach ($group['matches'] as $match) {
                    $ids[] = (string) $match['sportsApiPro_match_id'];
                }
            }
            sort($ids);
            $this->assertSame($expected, $ids, $filter);
            if ($filter === '00_70') {
                $html = view('users.sports.football.footballDetails', ['data' => $groups, 'append' => false])->render();
                $this->assertSame(1, substr_count($html, 'data-football-odds-source="Betfair"'));
                $this->assertSame(1, substr_count($html, '>Odds unavailable</span>'));
                $markets = array_column(array_merge(...array_column($groups, 'matches')), 'odds_markets', 'sportsApiPro_match_id');
                $this->assertSame(['Home'], array_column($markets['2'][0]['runners'], 'runner'));
                $this->assertSame([1.8], array_column($markets['2'][0]['runners'], 'currentBackOdds'));
            }
            if (in_array($filter, ['fav_losing', 'fav_not_winning', 'prematch_drops', 'market_shock'], true)) {
                foreach (['filterFootball', 'websocketFootball'] as $method) {
                    $response = app(FootballController::class)->{$method}()->getData(true);
                    preg_match_all('/data-sportApi-match-id="([^"]+)"/', $response['html'], $rendered);
                    $renderedIds = array_values(array_unique($rendered[1]));
                    sort($renderedIds);
                    $this->assertSame($expected, $renderedIds, $filter.' '.$method);
                }
            }
            if ($filter === 'market_shock') {
                $html = view('users.sports.football.footballDetails', ['data' => $groups, 'append' => false])->render();
                $this->assertSame(2, substr_count($html, 'data-shock-alert='));
                $this->assertSame(2, substr_count($html, 'data-shock-expires-at="'.($detectedAt + 1200).'"'));
                $this->assertSame(2, substr_count($html, 'data-shock-chart'));
                $this->assertStringContainsString('Alerts are automatically removed after 20 minutes.', $html);
            }
            if ($filter === 'ht00') {
                $html = view('users.sports.football.footballDetails', ['data' => $groups, 'append' => false])->render();
                foreach (['livestats', 'quickstats', 'stats', 'prediction'] as $action) {
                    $this->assertTrue(str_contains($html, 'data-action="'.$action.'"'));
                }
                $this->assertTrue(str_contains($html, 'data-quick-stats-panel="1"'));
            }
        }
    }

    public function test_football_smart_filter_boundaries_do_not_invent_missing_odds_or_retain_expired_shocks(): void
    {
        $controller = $this->football();
        $base = ['result_status' => 'Live', 'minutes_status' => '1st half', 'timestamp' => time() - 29 * 60, 'home_score' => 0, 'away_score' => 1, 'bet365_prematch' => ['favorite_odds' => 1.8, 'favorite_side' => 'home']];
        $runner = ['initialBackOdds' => 2.0, 'monitoring_baseline_version' => 1, 'opening_odds_source' => 'first_observed', 'initialBackOddsTimestamp' => time() - 1500, 'prematch_drop_triggered' => true, 'market_shock_detected_at' => time()];
        $prematch = array_replace($base, ['result_status' => 'Prematch', 'markets' => ['m' => [$runner]]]);
        $cases = [
            ['fav_losing', $base, true],
            ['fav_losing', array_replace($base, ['home_score' => 1]), false],
            ['fav_losing', array_replace($base, ['home_score' => null]), false],
            ['fav_losing', array_replace($base, ['bet365_prematch' => []]), false],
            ['fav_losing', array_replace($base, ['bet365_prematch' => ['favorite_odds' => 2.10, 'favorite_side' => 'home']]), true],
            ['fav_losing', array_replace($base, ['bet365_prematch' => ['favorite_odds' => 2.11, 'favorite_side' => 'home']]), false],
            ['fav_losing', array_replace($base, ['home_score' => 2, 'bet365_prematch' => ['favorite_odds' => 1.8, 'favorite_side' => 'away']]), true],
            ['fav_not_winning', array_replace($base, ['home_score' => 1]), true],
            ['fav_not_winning', array_replace($base, ['timestamp' => time() - 28 * 60]), false],
            ['fav_not_winning', array_replace($base, ['minutes_status' => 'Half Time']), true],
            ['fav_not_winning', array_replace($base, ['bet365_prematch' => ['favorite_odds' => 1.87, 'favorite_side' => 'home']]), true],
            ['fav_not_winning', array_replace($base, ['bet365_prematch' => ['favorite_odds' => 1.88, 'favorite_side' => 'home']]), false],
            ['fav_not_winning', array_replace($base, ['home_score' => 2]), false],
            ['prematch_drops', $prematch, true],
            ['prematch_drops', array_replace($prematch, ['result_status' => 'Live']), false],
            ['prematch_drops', array_replace($prematch, ['markets' => []]), false],
            ['prematch_drops', array_replace($prematch, ['markets' => ['m' => [array_replace($runner, ['initialBackOdds' => null])]]]), false],
            ['prematch_drops', array_replace($prematch, ['markets' => [], 'pinnacle_drop_triggered' => true]), true],
            ['market_shock', $prematch, true],
            ['market_shock', array_replace($prematch, ['markets' => ['m' => [array_replace($runner, ['market_shock_detected_at' => time() - 1201])]]]), false],
            ['market_shock', array_replace($prematch, ['result_status' => 'Live']), false],
            ['market_shock', array_replace($prematch, ['markets' => [], 'pinnacle_market_shock_detected_at' => time()]), true],
        ];

        foreach ($cases as $index => [$filter, $match, $expected]) {
            $this->assertSame($expected, ($controller->footballFilterPredicate($filter))($match), $filter.' case '.$index);
        }
    }

    public function test_predictions_require_login_preserve_one_vote_and_feed_favorites_and_poll_counts(): void
    {
        Schema::create('prediction_votes', function (Blueprint $table): void {
            $table->id();
            $table->string('vote_key')->unique();
            $table->integer('customer_id');
            $table->string('sport');
            $table->string('match_id');
            $table->string('poll');
            $table->string('value');
            $table->timestamps();
        });
        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->integer('customer_id');
            $table->string('sport');
            $table->string('event_key');
        });
        $match = ['unique_key' => 'm1', 'timestamp' => time() + 120, 'result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'sportsApiPro_match_id' => '123', 'betfair_match_id' => '201', 'data_sync_complete' => true, 'tournament' => 'Test League', 'team1' => 'Home', 'team2' => 'Away', 'markets' => []];
        $cache = ['Football:matches' => json_encode(['m1' => $match])];
        Redis::shouldReceive('get')->andReturnUsing(static function (string $key) use (&$cache): ?string {
            return $cache[$key] ?? null;
        });
        Redis::shouldReceive('set')->never();
        Redis::shouldReceive('expire')->never();
        Cache::shouldReceive('lock')->never();
        $controller = \Mockery::mock(FootballController::class)->makePartial();
        $controller->shouldReceive('getFootballMatches')->andReturn([]);
        $customer = new Customer(['trial_ends_at' => now()->addDay()]);
        $customer->id = 7;
        $session = $this->app['session']->driver();
        $session->start();
        $requestFor = function (?Customer $user, string $value = '1') use ($session): void {
            $request = Request::create('/', 'POST', ['prediction' => ['match_id' => '123', 'poll' => '1x2', 'value' => $value]]);
            $request->setUserResolver(static fn () => $user);
            $request->setLaravelSession($session);
            $this->app->instance('request', $request);
        };
        $requestFor(null);
        try {
            $controller->filterFootball();
            $this->fail('Guest vote should fail.');
        } catch (HttpException $error) {
            $this->assertSame(401, $error->getStatusCode());
        }
        $this->app['auth']->guard('customers')->setUser($customer);
        $requestFor($customer, 'invalid');
        try {
            $controller->filterFootball();
            $this->fail('Invalid prediction should fail.');
        } catch (HttpException $error) {
            $this->assertSame(422, $error->getStatusCode());
        }
        $this->assertSame(0, PredictionVote::query()->count());
        $requestFor($customer);
        $response = $controller->filterFootball()->getData(true);
        $this->assertSame(['match_id' => '123', 'poll' => '1x2', 'value' => '1', 'counts' => [1 => 1]], $response['prediction']);
        $this->assertArrayNotHasKey('html', $response);
        $this->assertSame(1, PredictionVote::query()->count());
        $session->flush();
        $requestFor($customer, '2');
        $controller->filterFootball();
        $this->assertSame('1', PredictionVote::query()->value('value'));
        $this->assertSame(1, PredictionVote::query()->count());
        Favorite::query()->insert(['customer_id' => 7, 'sport' => 'football', 'event_key' => 'm1']);
        $request = Request::create('/', 'GET', ['type' => 'favorites']);
        $request->setUserResolver(static fn () => $customer);
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);
        $groups = $this->football()->getFootballMatches();
        $event = array_values($groups)[0]['matches'][0];
        $this->assertTrue($event['is_favorite']);
        $this->assertSame(1, $event['predictions']['1x2']['1']);
        $this->assertSame('1', $event['prediction_votes']['1x2']);
    }

    public function test_clock_requires_provider_phase_and_anchor_and_stops_for_halftime_and_finished(): void
    {
        $match = ['timestamp' => time() - 7000, 'result_status' => 'Live', 'minutes_status' => 'Live', 'home_score' => 0, 'away_score' => 0];
        $unknown = $this->timeline($match);
        $this->assertFalse($unknown['running']);
        $this->assertSame(0, $unknown['minute']);
        $match['minutes_status'] = '2nd half';
        $match['current_period_start_timestamp'] = time() - 20 * 60;
        $active = $this->timeline($match);
        $this->assertTrue($active['running']);
        $this->assertSame(65, $active['minute']);
        $stale = array_replace($match, ['minutes_status' => '1st half', 'current_period_start_timestamp' => time() - 5400]);
        $this->assertSame('45+', $this->timeline($stale)['minute_label']);
        $this->assertSame(45, $this->timeline($stale)['minute']);
        $this->assertFalse($this->timeline($stale)['running']);
        $match['minutes_status'] = 'Half Time';
        $this->assertFalse($this->timeline($match)['running']);
        $this->assertSame(45, $this->timeline($match)['minute']);
        $match['result_status'] = 'Finished';
        $finished = $this->timeline($match);
        $this->assertFalse($finished['running']);
        $this->assertSame('FT', $finished['minute_label']);
    }

    public function test_football_normalization_requires_provider_confirmation_not_finished_display_text(): void
    {
        $matches = [
            'text-only' => ['result_status' => 'Live', 'minutes_status' => 'Finished'],
            'confirmed' => ['result_status' => 'Live', 'minutes_status' => 'Live', 'internal_backup' => ['since' => time()], 'timeline' => ['clock' => ['finished' => true, 'status_code' => 'FT']]],
        ];
        Redis::shouldReceive('get')->andReturnUsing(static function () use (&$matches): string {
            return json_encode($matches);
        });
        Redis::shouldReceive('set')->andReturnUsing(static function (string $key, string $value) use (&$matches): void {
            $matches = json_decode($value, true);
        });
        Redis::shouldReceive('expire')->andReturn(true);
        $listener = (new ReflectionClass(ListenSportsApiPro::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod($listener, 'updateFootballCache'))->invoke($listener, 'unrelated', []);
        $this->assertSame('Live', $matches['text-only']['result_status']);
        $this->assertSame('Live', $matches['text-only']['minutes_status']);
        $this->assertSame('Finished', $matches['confirmed']['result_status']);
        $this->assertArrayNotHasKey('internal_backup', $matches['confirmed']);
    }

    public function test_interruption_freezes_the_timeline_at_provider_pause(): void
    {
        $now = time();
        $match = ['timestamp' => $now - 6000, 'result_status' => 'Live', 'minutes_status' => '2nd half', 'home_score' => 0, 'away_score' => 0,
            'timeline' => ['clock' => ['status_code' => 7, 'status_type' => 'interrupted', 'period_start' => $now - 1800, 'initial' => 2700, 'paused_at' => $now - 600]]];
        $timeline = $this->timeline($match);
        $this->assertSame(65, $timeline['minute']);
        $this->assertFalse($timeline['running']);
    }

    public function test_missing_card_statistics_are_not_zero_but_explicit_zero_is_preserved(): void
    {
        $sync = $this->sync();
        $match = ['timeline' => ['events' => [], 'initialized' => false]];
        $sync->storeTimeline($match);
        $this->assertNull($match['timeline']['reported_counts']['home_yellow_cards'] ?? null);
        $this->assertNull($match['timeline']['reported_counts']['away_red_cards'] ?? null);
        $match['card_stats'] = ['yellow_cards' => ['home' => 0, 'away' => 2], 'red_cards' => ['home' => 1, 'away' => 0]];
        $sync->storeTimeline($match);
        $this->assertSame(0, $match['timeline']['reported_counts']['home_yellow_cards'] ?? null);
        $this->assertSame(2, $match['timeline']['reported_counts']['away_yellow_cards'] ?? null);
        $this->assertSame(1, $match['timeline']['reported_counts']['home_red_cards'] ?? null);
        $this->assertSame(0, $match['timeline']['reported_counts']['away_red_cards'] ?? null);
    }

    public function test_statistics_update_correct_team_and_reject_out_of_order_payloads(): void
    {
        $match = [];
        $sync = $this->sync();
        $payload = ['changes' => ['changeTimestamp' => 20], 'statistics' => [
            ['key' => 'yellowCards', 'home' => 1, 'away' => 3],
            ['key' => 'redCards', 'home' => 0, 'away' => 1],
            ['key' => 'cornerKicks', 'home' => 4, 'away' => 6],
        ]];
        $this->assertTrue($sync->applyMatchStatistics($match, $payload));
        $this->assertSame(4, $match['timeline']['reported_counts']['home_corners']);
        $this->assertSame(6, $match['timeline']['reported_counts']['away_corners']);
        $this->assertSame(1, $match['timeline']['reported_counts']['home_yellow_cards'] ?? null);
        $this->assertSame(3, $match['timeline']['reported_counts']['away_yellow_cards'] ?? null);
        $payload['changes']['changeTimestamp'] = 19;
        $this->assertFalse($sync->applyMatchStatistics($match, $payload));
    }

    public function test_complete_goal_corrections_remove_obsolete_events_and_restore_exactly(): void
    {
        (require database_path('migrations/2026_10_26_110659_create_football_timeline_table.php'))->up();
        (require database_path('migrations/2026_10_26_124628_add_timeline_to_football_timeline_table.php'))->up();
        $sync = $this->sync();
        $goals = [
            ['id' => 'away-1', 'incidentType' => 'goal', 'isHome' => false, 'time' => 44, 'homeScore' => 0, 'awayScore' => 1],
            ['id' => 'home-1', 'incidentType' => 'goal', 'isHome' => true, 'time' => 90, 'addedTime' => 2, 'homeScore' => 1, 'awayScore' => 1],
            ['id' => 'cancelled', 'incidentType' => 'goal', 'isHome' => false, 'time' => 90, 'addedTime' => 3, 'homeScore' => 1, 'awayScore' => 2],
        ];

        foreach (['Live', 'Finished'] as $index => $status) {
            $id = (string) (123 + $index);
            $match = ['sportsApiPro_match_id' => $id, 'timestamp' => time() - 6000, 'result_status' => 'Live', 'home_score' => 1, 'away_score' => 2];
            $sync->storeTimeline($match, "match:{$id}:incidents", ['incidents' => $goals], 'snapshot');
            $oldTimeline = $match['timeline'];
            $match['away_score'] = 1;
            $period = ['incidentType' => 'period', 'text' => $status === 'Finished' ? 'FT' : 'Second half', 'isLive' => $status === 'Live', 'time' => 90, 'homeScore' => 1, 'awayScore' => 1];
            $sync->storeTimeline($match, "match:{$id}:incidents", ['incidents' => [$period, $goals[0], $goals[1]]]);

            $this->assertSame(['away-1'], array_column($match['timeline']['away_goals'], 'id'));
            $this->assertArrayHasKey('cancelled', $match['timeline']['removed']);
            $this->assertSame($status === 'Finished', $match['timeline']['finalized']);
            $saved = json_decode(DB::table('football_timeline')->where('match_id', $id)->value('timeline'), true);
            $this->assertSame($match['timeline'], $saved);
            $stale = array_replace($match, ['timeline' => $oldTimeline]);
            $sync->storeTimeline($stale, frameType: 'restore', previous: ['timeline' => $saved], storedTimeline: []);
            $this->assertSame(['away-1'], array_column($stale['timeline']['away_goals'], 'id'));
            $match['home_score'] = $match['away_score'] = 1;
            $match['timeline'] = $oldTimeline;
            $sync->storeTimeline($match, frameType: 'restore', storedTimeline: $saved);
            $this->assertSame(['away-1'], array_column($match['timeline']['away_goals'], 'id'));
            $this->assertCount(2, array_filter($this->timeline($match)['events'], static fn (array $event): bool => $event['kind'] === 'goal'));
            unset($match['timeline']);
            $sync->storeTimeline($match, frameType: 'restore');
            $this->assertSame($saved, $match['timeline']);
        }
    }

    public function test_complete_card_histories_replace_changed_ids_and_restore_without_duplicate_cards(): void
    {
        (require database_path('migrations/2026_10_26_110659_create_football_timeline_table.php'))->up();
        (require database_path('migrations/2026_10_26_124628_add_timeline_to_football_timeline_table.php'))->up();
        Bus::fake();
        $sync = $this->sync();
        $goals = [
            ['id' => 'g1', 'incidentType' => 'goal', 'isHome' => true, 'time' => 8, 'homeScore' => 1, 'awayScore' => 0],
            ['id' => 'g2', 'incidentType' => 'goal', 'isHome' => true, 'time' => 79, 'homeScore' => 2, 'awayScore' => 0],
            ['id' => 'g3', 'incidentType' => 'goal', 'isHome' => false, 'time' => 90, 'addedTime' => 2, 'homeScore' => 2, 'awayScore' => 1],
        ];
        $cards = array_map(static fn (int $minute): array => ['id' => 'old-'.$minute, 'incidentType' => 'card', 'incidentClass' => 'yellow', 'isHome' => false, 'time' => $minute, 'rescinded' => false], [51, 58, 79]);
        $latestCards = array_map(static fn (array $card): array => array_replace($card, ['id' => 'new-'.$card['time']]), $cards);
        foreach (['Live', 'Finished'] as $index => $status) {
            $id = (string) (123 + $index);
            $match = ['sportsApiPro_match_id' => $id, 'timestamp' => time() - 5400, 'result_status' => $status, 'home_score' => 2, 'away_score' => 1];
            $period = ['incidentType' => 'period', 'text' => $status === 'Live' ? 'Second half' : 'FT', 'isLive' => $status === 'Live', 'homeScore' => 2, 'awayScore' => 1];
            $initial = ['incidents' => [$period, ...$goals, ...$cards], 'changes' => ['changeTimestamp' => 20]];
            $sync->storeTimeline($match, "match:{$id}:incidents", $initial, 'snapshot');
            $oldTimeline = $match['timeline'];
            $sync->storeTimeline($match, "match:{$id}:incidents", ['incidents' => [array_replace($cards[0], ['id' => 'temporary', 'time' => 0])], 'changes' => ['changeTimestamp' => 21]]);
            $this->assertCount(4, $match['timeline']['away_yellow_cards']);

            $latest = ['incidents' => [$period, ...$goals, ...$latestCards], 'changes' => ['changeTimestamp' => 22]];
            $sync->storeTimeline($match, "match:{$id}:incidents", $latest, 'snapshot');

            $this->assertSame(['new-51', 'new-58', 'new-79'], array_column($match['timeline']['away_yellow_cards'], 'id'));
            $this->assertSame([51, 58, 79], array_column($match['timeline']['away_yellow_cards'], 'minute'));
            $this->assertSame([], $match['timeline']['home_yellow_cards']);
            $this->assertCount(3, array_filter($this->timeline($match)['events'], static fn (array $event): bool => $event['kind'] === 'yellow'));
            $saved = json_decode(DB::table('football_timeline')->where('match_id', $id)->value('timeline'), true);
            $this->assertSame($match['timeline'], $saved);
            $stale = array_replace($match, ['timeline' => $oldTimeline]);
            $sync->storeTimeline($stale, frameType: 'restore', storedTimeline: $saved);
            $this->assertSame($saved['away_yellow_cards'], $stale['timeline']['away_yellow_cards']);
            $this->assertSame($saved['removed'], $stale['timeline']['removed']);
            unset($match['timeline']);
            $sync->storeTimeline($match, frameType: 'restore');
            $this->assertSame($saved, $match['timeline']);
            $sync->storeTimeline($match, "match:{$id}:incidents", $initial, 'snapshot', microtime(true) - 100);
            $this->assertSame($saved, $match['timeline']);
            $initial['changes']['changeTimestamp'] = 23;
            $sync->storeTimeline($match, "match:{$id}:incidents", $initial, 'snapshot');
            $this->assertSame(['old-51', 'old-58', 'old-79'], array_column($match['timeline']['away_yellow_cards'], 'id'));
        }
    }

    public function test_card_reconciliation_preserves_partial_updates_and_distinct_cards_at_the_same_minute(): void
    {
        $sync = $this->sync();
        $match = ['sportsApiPro_match_id' => '123', 'result_status' => 'Live', 'home_score' => 0, 'away_score' => 0];
        $period = ['incidentType' => 'period', 'text' => 'Second half', 'isLive' => true, 'homeScore' => 0, 'awayScore' => 0];
        $yellow = ['id' => 'yellow-1', 'incidentType' => 'card', 'incidentClass' => 'yellow', 'isHome' => false, 'time' => 58, 'rescinded' => false];
        $red = array_replace($yellow, ['id' => 'red-1', 'incidentClass' => 'yellowRed']);
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$period, $yellow, $red]], 'snapshot', storedTimeline: []);
        $second = array_replace($yellow, ['id' => 'yellow-2']);
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$second]], 'snapshot', storedTimeline: []);
        $this->assertSame(['yellow-1', 'yellow-2'], array_column($match['timeline']['away_yellow_cards'], 'id'));
        $this->assertSame(['red-1'], array_column($match['timeline']['away_red_cards'], 'id'));
        $third = array_replace($yellow, ['id' => 'yellow-3', 'time' => 79]);
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$third]], storedTimeline: []);
        $this->assertCount(3, $match['timeline']['away_yellow_cards']);
        $history = ['incidents' => [$period, $yellow, $second, array_replace($red, ['id' => 'red-2', 'incidentClass' => 'red'])], 'home' => [], 'away' => []];

        $sync->storeTimeline($match, 'match:123:incidents', $history, storedTimeline: []);

        $this->assertSame(['yellow-1', 'yellow-2'], array_column($match['timeline']['away_yellow_cards'], 'id'));
        $this->assertSame(['red-2'], array_column($match['timeline']['away_red_cards'], 'id'));
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$period]], 'snapshot', storedTimeline: []);
        $this->assertSame([], $match['timeline']['away_yellow_cards']);
        $this->assertSame([], $match['timeline']['away_red_cards']);
        $this->assertSame(0, $match['timeline']['reported_counts']['away_yellow_cards']);
    }

    public function test_partial_and_older_goal_histories_do_not_remove_confirmed_goals(): void
    {
        $sync = $this->sync();
        $goals = [
            ['id' => 'g1', 'incidentType' => 'goal', 'isHome' => true, 'time' => 14, 'homeScore' => 1, 'awayScore' => 0],
            ['id' => 'g2', 'incidentType' => 'goal', 'isHome' => true, 'time' => 60, 'homeScore' => 2, 'awayScore' => 0],
        ];
        $match = ['sportsApiPro_match_id' => '123', 'result_status' => 'Live', 'home_score' => 2, 'away_score' => 0];
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => $goals, 'changes' => ['changeTimestamp' => 20]], 'snapshot', storedTimeline: []);
        $period = ['incidentType' => 'period', 'text' => 'Second half', 'isLive' => true, 'homeScore' => 2, 'awayScore' => 0];
        $partial = ['incidents' => [$period, $goals[1]], 'changes' => ['changeTimestamp' => 21]];

        $sync->storeTimeline($match, 'match:123:incidents', $partial, 'snapshot', storedTimeline: []);

        $this->assertSame(['g1', 'g2'], array_column($match['timeline']['home_goals'], 'id'));
        $older = ['incidents' => [array_replace($period, ['homeScore' => 1]), $goals[0]], 'changes' => ['changeTimestamp' => 19]];
        $this->assertFalse($sync->storeTimeline($match, 'match:123:incidents', $older, storedTimeline: []));
        $this->assertSame(['g1', 'g2'], array_column($match['timeline']['home_goals'], 'id'));
        $sync->storeTimeline($match, 'match:123:incidents', $older, 'snapshot', microtime(true) - 100, storedTimeline: []);
        $this->assertSame(['g1', 'g2'], array_column($match['timeline']['home_goals'], 'id'));
    }

    public function test_incident_snapshots_preserve_known_goal_minutes_and_do_not_resurrect_deleted_goals(): void
    {
        $sync = $this->sync();
        $match = ['sportsApiPro_match_id' => '123', 'result_status' => 'Live'];
        $goal = ['id' => 'g1', 'incidentType' => 'goal', 'isHome' => true, 'time' => 14, 'homeScore' => 1, 'awayScore' => 0];
        $this->assertTrue($sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$goal], 'changes' => ['changeTimestamp' => 10]], 'snapshot'));
        $second = array_replace($goal, ['id' => 'g2', 'time' => 60, 'homeScore' => 2]);
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$second], 'changes' => ['changeTimestamp' => 11]], 'snapshot');
        $this->assertCount(2, $match['timeline']['home_goals']);
        $this->assertSame(14, $match['timeline']['home_goals'][0]['minute']);
        $sameTimestampGoal = array_replace($second, ['id' => 'g3', 'isHome' => false, 'time' => 61, 'awayScore' => 1]);
        $sameTimestampPayload = ['incidents' => [$sameTimestampGoal], 'changes' => ['changeTimestamp' => 11]];
        $this->assertTrue($sync->storeTimeline($match, 'match:123:incidents', $sameTimestampPayload));
        $this->assertSame(['g3'], array_column($match['timeline']['away_goals'], 'id'));
        $this->assertFalse($sync->storeTimeline($match, 'match:123:incidents', $sameTimestampPayload));
        $this->assertFalse($sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$goal], 'changes' => ['changeTimestamp' => 9]], 'update'));
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [['id' => 'g1', 'isDeleted' => true]], 'changes' => ['changeTimestamp' => 12]], 'update');
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$goal, $second], 'changes' => ['changeTimestamp' => 13]], 'snapshot');
        $this->assertNotContains('g1', array_column($match['timeline']['home_goals'], 'id'));
        $this->assertContains('g2', array_column($match['timeline']['home_goals'], 'id'));
    }

    public function test_delayed_goals_replace_the_observed_minute_and_restore_exactly_from_database(): void
    {
        (require database_path('migrations/2026_10_26_110659_create_football_timeline_table.php'))->up();
        (require database_path('migrations/2026_10_26_124628_add_timeline_to_football_timeline_table.php'))->up();
        Bus::fake();
        Cache::shouldReceive('store')->with('redis')->andReturnSelf();
        Cache::shouldReceive('lock')->andReturn(new class
        {
            public function get(): bool
            {
                return false;
            }
        });
        $sync = $this->sync();
        $this->freezeTime();
        $match = ['sportsApiPro_match_id' => '123', 'timestamp' => now()->timestamp - 1800, 'current_period_start_timestamp' => now()->timestamp - 1800, 'result_status' => 'Live', 'minutes_status' => '1st half', 'home_score' => 1, 'away_score' => 0];

        $sync->storeTimeline($match);

        $this->assertCount(1, $match['timeline']['home_goals']);
        $this->assertSame('score', $match['timeline']['home_goals'][0]['source']);
        $this->assertTrue($match['timeline']['home_goals'][0]['time_known']);
        $this->assertTrue($match['timeline']['home_goals'][0]['time_estimated']);
        $this->assertSame(30, $match['timeline']['home_goals'][0]['minute']);
        $this->assertSame([], $match['timeline']['away_goals']);
        $this->assertTrue($this->timeline($match)['goals_pending']);
        $saved = json_decode(DB::table('football_timeline')->value('timeline'), true);
        $this->assertSame($match['timeline'], $saved);
        unset($match['timeline']);
        $sync->storeTimeline($match, frameType: 'restore');
        $this->assertSame($saved, $match['timeline']);
        $this->assertSame(30 / 90 * 100, $this->timeline($match)['events'][0]['left']);
        $this->assertSame("~30'", $this->timeline($match)['events'][0]['minute_label']);

        $legacyTimeline = $saved;
        $legacyTimeline['home_goals'] = [['id' => 'score-home-goal-1', 'minute' => 30, 'added_time' => 0, 'seconds' => 1800, 'time_known' => true, 'time_estimated' => true, 'source' => 'score', 'home_score' => 1, 'away_score' => 0]];
        DB::table('football_timeline')->where('match_id', '123')->update(['timeline' => json_encode($legacyTimeline)]);
        unset($match['timeline']);
        $sync->storeTimeline($match, frameType: 'restore');
        $this->assertSame([30], array_column($match['timeline']['home_goals'], 'minute'));
        $this->assertTrue($match['timeline']['home_goals'][0]['time_estimated']);
        $sync->storeTimeline($match, frameType: 'persist');
        $this->assertSame($match['timeline'], json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));

        $goal = ['id' => 'actual-1', 'incidentType' => 'goal', 'isHome' => true, 'time' => 25, 'homeScore' => 1, 'awayScore' => 0];
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$goal]], 'snapshot');
        $this->assertCount(1, $match['timeline']['home_goals']);
        $this->assertSame('actual-1', $match['timeline']['home_goals'][0]['id']);
        $this->assertSame(25, $match['timeline']['home_goals'][0]['minute']);
        $this->assertFalse($match['timeline']['home_goals'][0]['time_estimated']);
        $this->assertSame("25'", $this->timeline($match)['events'][0]['minute_label']);
        $this->assertSame(25 / 90 * 100, $this->timeline($match)['events'][0]['left']);
        $match['current_period_start_timestamp'] -= 600;
        $sync->storeTimeline($match);
        $this->assertSame(['actual-1'], array_column($match['timeline']['home_goals'], 'id'));
        $this->assertSame(['25'], json_decode(DB::table('football_timeline')->value('home_goals'), true));
        $this->assertSame($match['timeline'], json_decode(DB::table('football_timeline')->value('timeline'), true));
        $saved = $match['timeline'];
        unset($match['timeline']);
        $sync->storeTimeline($match, frameType: 'restore');
        $this->assertSame($saved, $match['timeline']);
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [['id' => 'actual-1', 'isDeleted' => true]]]);
        $sync->storeTimeline($match);
        $this->assertSame([], $match['timeline']['home_goals']);

        $laterGoalFirst = array_replace($match, ['sportsApiPro_match_id' => '124', 'home_score' => 2, 'timeline' => []]);
        $sync->storeTimeline($laterGoalFirst, 'match:124:incidents', ['incidents' => [array_replace($goal, ['id' => 'actual-2', 'time' => 50, 'homeScore' => 2])]], 'snapshot');
        $this->assertCount(1, $laterGoalFirst['timeline']['home_goals']);
        $this->assertSame([2], array_column($laterGoalFirst['timeline']['home_goals'], 'home_score'));
        $this->assertTrue($this->timeline($laterGoalFirst)['goals_pending']);
        $untimed = array_replace($match, ['sportsApiPro_match_id' => '125', 'home_score' => 1, 'away_score' => 0, 'timeline' => []]);
        $sync->storeTimeline($untimed, 'match:125:incidents', ['incidents' => [array_replace($goal, ['time' => null])]], 'snapshot');
        $this->assertTrue($untimed['timeline']['home_goals'][0]['time_known']);
        $this->assertTrue($untimed['timeline']['home_goals'][0]['time_estimated']);
        $this->assertSame("~40'", $this->timeline($untimed)['events'][0]['minute_label']);
        $this->assertSame(40 / 90 * 100, $this->timeline($untimed)['events'][0]['left']);
        $legacyTimeline = $untimed['timeline'];
        $legacyTimeline['home_goals'][0] = array_replace($legacyTimeline['home_goals'][0], ['minute' => 30, 'seconds' => 1800, 'time_known' => true, 'time_estimated' => true]);
        DB::table('football_timeline')->where('match_id', '125')->update(['timeline' => json_encode($legacyTimeline)]);
        unset($untimed['timeline']);
        $sync->storeTimeline($untimed, frameType: 'restore');
        $this->assertTrue($untimed['timeline']['home_goals'][0]['time_known']);
        $this->assertSame(30, $untimed['timeline']['home_goals'][0]['minute']);
        $sync->storeTimeline($untimed, 'match:125:incidents', ['incidents' => [$goal]]);
        $this->assertSame(25, $untimed['timeline']['home_goals'][0]['minute']);
        $this->assertFalse($untimed['timeline']['home_goals'][0]['time_estimated']);
        $lateClock = array_replace($match, ['sportsApiPro_match_id' => '126', 'home_score' => 1, 'away_score' => 0, 'timeline' => []]);
        unset($lateClock['current_period_start_timestamp']);
        $sync->storeTimeline($lateClock);
        $this->assertCount(1, $lateClock['timeline']['home_goals']);
        $this->assertFalse($lateClock['timeline']['home_goals'][0]['time_known']);
        $lateClock['current_period_start_timestamp'] = time() - 1200;
        $sync->storeTimeline($lateClock);
        $this->assertSame([20], array_column($lateClock['timeline']['home_goals'], 'minute'));
        $countsOnly = array_replace($lateClock, ['sportsApiPro_match_id' => '127', 'home_score' => 2, 'timeline' => ['reported_counts' => ['home_goals' => 2]]]);
        $sync->storeTimeline($countsOnly);
        $this->assertCount(2, $countsOnly['timeline']['home_goals']);
        $this->assertSame([true, true], array_column($countsOnly['timeline']['home_goals'], 'time_known'));
        $this->assertSame([20, 20], array_column($countsOnly['timeline']['home_goals'], 'minute'));
    }

    public function test_goal_markers_preserve_provider_team_and_render_before_score_updates(): void
    {
        $goal = ['id' => 'goal-1', 'kind' => 'goal', 'minute' => 14, 'added_time' => 0, 'seconds' => 840, 'is_home' => false, 'home_score' => 1, 'away_score' => 0];
        $match = ['timestamp' => time() - 3600, 'result_status' => 'Live', 'minutes_status' => '2nd half', 'home_score' => 4, 'away_score' => 2, 'timeline' => ['events' => ['goal-1' => $goal]]];
        $timeline = $this->timeline($match);
        $goals = array_values(array_filter($timeline['events'], static fn (array $event): bool => $event['kind'] === 'goal'));
        $this->assertCount(1, $goals);
        $this->assertFalse($goals[0]['is_home']);
        $this->assertSame(14, $goals[0]['minute']);
        $match['away_score'] = 0;
        $this->assertSame($goals, array_values(array_filter($this->timeline($match)['events'], static fn (array $event): bool => $event['kind'] === 'goal')));
        $match['timeline']['events'] = [];
        $this->assertSame([], $this->timeline($match)['events']);
        $match['timeline']['events'] = ['goal-1' => array_replace($goal, ['minute' => 90, 'added_time' => 4, 'seconds' => 5640])];
        $timeline = $this->timeline($match);
        $this->assertSame(94, $timeline['duration']);
        $this->assertSame(100, $timeline['events'][0]['left']);
        $this->assertSame("90+4'", $timeline['events'][0]['minute_label']);
        $match['timeline']['events'] = [
            'home-goal' => array_replace($goal, ['id' => 'home-goal', 'is_home' => true]),
            'away-goal' => array_replace($goal, ['id' => 'away-goal']),
            'yellow' => array_replace($goal, ['id' => 'yellow', 'kind' => 'yellow', 'is_home' => true]),
            'red' => array_replace($goal, ['id' => 'red', 'kind' => 'red']),
        ];
        foreach ($this->timeline($match)['events'] as $incident) {
            $html = view('users.sports.football.partials.timelineEvent', ['incident' => $incident, 'positioned' => true])->render();
            $this->assertStringContainsString($incident['is_home'] ? 'bottom-1/2' : 'top-1/2', $html);
            $this->assertStringContainsString('left: '.$incident['left'].'%', $html);
        }
    }

    public function test_single_timeline_storage_preserves_event_details_and_removes_duplicate_cache_fields(): void
    {
        (require database_path('migrations/2026_10_26_110659_create_football_timeline_table.php'))->up();
        (require database_path('migrations/2026_10_26_124628_add_timeline_to_football_timeline_table.php'))->up();
        $sync = $this->sync();
        app()->instance(FootballSyncController::class, $sync);
        $match = ['sportsApiPro_match_id' => '123', 'timestamp' => time() - 3000, 'result_status' => 'Live', 'minutes_status' => '2nd half', 'home_score' => 2, 'away_score' => 0];
        $goal = ['id' => 'g1', 'incidentType' => 'goal', 'isHome' => true, 'time' => 45, 'addedTime' => 2, 'timestamp' => '2026-10-26T18:00:00+05:30', 'player' => ['name' => 'Test Player'], 'homeScore' => 1, 'awayScore' => 0];
        $payload = ['incidents' => [$goal, array_replace($goal, ['id' => 'g2', 'homeScore' => 2]), ['id' => 'corner', 'incidentType' => 'corner', 'time' => 60, 'isHome' => false]]];
        $sync->storeTimeline($match, 'match:123:incidents', $payload, 'snapshot');
        $this->assertCount(2, $match['timeline']['home_goals']);
        $this->assertSame('2026-10-26T12:30:00Z', $match['timeline']['home_goals'][0]['timestamp']);
        $this->assertSame(45, $match['timeline']['home_goals'][0]['minute']);
        $this->assertSame(2, $match['timeline']['home_goals'][0]['added_time']);
        $this->assertSame('Test Player', $match['timeline']['home_goals'][0]['player']);
        $this->assertCount(1, $match['timeline']['away_corners']);
        $this->assertNull($match['timeline']['away_corners'][0]['timestamp']);
        $this->assertSame([], $match['timeline']['home_corners']);
        $this->assertArrayNotHasKey('events', $match['timeline']);
        $first = $match['timeline']['home_goals'];
        DB::enableQueryLog();
        $sync->storeTimeline($match, 'match:123:incidents', $payload);
        $this->assertSame([], array_values(array_filter(DB::getQueryLog(), static fn (array $query): bool => preg_match('/^(insert|update|delete)/i', $query['query']) === 1)));
        DB::disableQueryLog();
        $this->assertSame($first, $match['timeline']['home_goals']);
        $this->assertDatabaseCount('football_timeline', 1);
        $this->assertDatabaseHas('football_timeline', ['match_id' => '123', 'home_goals' => '["45+2"]']);
        $match['home_score'] = 0;
        $rendered = $this->timeline($match);
        $this->assertCount(2, array_filter($rendered['events'], static fn (array $event): bool => $event['kind'] === 'goal'));
        $this->assertSame("45+2'", $rendered['events'][0]['minute_label']);
        $this->assertSame('Test Player', $rendered['events'][0]['player']);
        $cached = json_encode(['match-1' => $match]);
        Redis::shouldReceive('get')->with('Football:matches')->andReturnUsing(function () use (&$cached): string {
            return $cached;
        });
        Redis::shouldReceive('set')->andReturnUsing(function (string $key, string $value) use (&$cached): void {
            $cached = $value;
        });
        Redis::shouldReceive('expire')->andReturn(true);
        storeInRedis('Football:matches', ['match-1' => ['sportsApiPro_match_id' => '123', 'card_counts' => [], 'cards' => [], 'live_stats' => ['yellow_cards' => ['home' => 1, 'away' => 0]], 'timeline' => []]], 10, false);
        $restored = json_decode($cached, true)['match-1'];
        $this->assertSame($first, $restored['timeline']['home_goals']);
        $this->assertSame(1, $restored['timeline']['reported_counts']['home_yellow_cards']);
        foreach (['cards', 'card_counts', 'card_stats', 'corners', 'cards_updated_at', 'card_counts_updated_at', 'card_stats_updated_at', 'incidents_initialized', 'timeline_state', 'events', 'goals', 'goal_counts'] as $field) {
            $this->assertArrayNotHasKey($field, $restored);
        }
        $this->assertArrayNotHasKey('yellow_cards', $restored['live_stats']);
    }

    public function test_midnight_timeline_cleanup_deletes_only_records_at_least_three_days_old(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-11-04 18:30:00 UTC'));
        (require database_path('migrations/2026_10_26_110659_create_football_timeline_table.php'))->up();
        (require database_path('migrations/2026_10_26_124628_add_timeline_to_football_timeline_table.php'))->up();
        $scores = array_fill_keys(['home_goals', 'away_goals', 'home_yellow_cards', 'away_yellow_cards', 'home_red_cards', 'away_red_cards'], '[]');
        foreach (['old' => '2026-11-01 18:29:59', 'boundary' => '2026-11-01 18:30:00', 'recent' => '2026-11-01 18:30:01', 'today' => '2026-11-04 18:30:00', 'unknown' => null] as $matchId => $createdAt) {
            DB::table('football_timeline')->insert($scores + ['match_id' => $matchId, 'created_at' => $createdAt, 'updated_at' => now(), 'timeline' => '{"home_goals":[]}']);
        }
        $cleanup = collect(Schedule::events())->firstWhere('description', 'football-timeline-cleanup');
        $this->assertNotNull($cleanup);
        $this->assertTrue($cleanup->isDue($this->app));

        $cleanup->run($this->app);

        $this->assertSame(['recent', 'today', 'unknown'], DB::table('football_timeline')->orderBy('id')->pluck('match_id')->all());
        $this->assertDatabaseHas('football_timeline', ['match_id' => 'recent', 'timeline' => '{"home_goals":[]}', 'created_at' => '2026-11-01 18:30:01']);
        $cleanup->run($this->app);
        $this->assertDatabaseCount('football_timeline', 3);
        $this->travel(1)->minutes();
        $this->assertFalse($cleanup->isDue($this->app));
        $this->travelTo(new \DateTimeImmutable('2026-11-05 00:00:00 UTC'));
        $this->assertFalse($cleanup->isDue($this->app));
    }

    public function test_database_restores_exact_timeline_and_display_for_empty_and_partial_cache(): void
    {
        (require database_path('migrations/2026_10_26_110659_create_football_timeline_table.php'))->up();
        (require database_path('migrations/2026_10_26_124628_add_timeline_to_football_timeline_table.php'))->up();
        $sync = $this->sync();
        app()->instance(FootballSyncController::class, $sync);
        $match = ['sportsApiPro_match_id' => '123', 'timestamp' => time() - 4000, 'result_status' => 'Finished', 'minutes_status' => 'Finished', 'home_score' => 2, 'away_score' => 0];
        $goal = ['id' => 'g1', 'incidentType' => 'goal', 'isHome' => true, 'time' => 45, 'addedTime' => 2, 'player' => ['name' => 'Test Player'], 'homeScore' => 1, 'awayScore' => 0];
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$goal, array_replace($goal, ['id' => 'g2', 'homeScore' => 2]), ['id' => 'corner', 'incidentType' => 'corner', 'time' => 80, 'isHome' => false], ['id' => 'yellow', 'incidentType' => 'card', 'incClass' => 'yellow', 'time' => 90, 'addedTime' => 4, 'isHome' => true]]], 'snapshot', microtime(true));
        $saved = DB::table('football_timeline')->first();
        $this->assertEquals($match['timeline'], json_decode($saved->timeline, true));
        $expected = $this->timeline($match);
        $empty = $match;
        unset($empty['timeline']);
        $sync->storeTimeline($empty);
        $this->assertEquals($match['timeline'], $empty['timeline']);
        $this->assertSame($expected, $this->timeline($empty));
        $partial = $match;
        $partial['timeline']['home_goals'] = [$partial['timeline']['home_goals'][1]];
        $partial['timeline']['away_corners'] = [];
        $sync->storeTimeline($partial);
        $this->assertSame($expected, $this->timeline($partial));
        $this->assertSame(['g1', 'g2'], array_column($partial['timeline']['home_goals'], 'id'));
        $this->assertDatabaseCount('football_timeline', 1);
        $this->assertSame($saved->created_at, DB::table('football_timeline')->first()->created_at);
        $cached = json_encode(['match-1' => array_replace($match, ['timeline' => []])]);
        Redis::shouldReceive('get')->with('Football:matches')->andReturnUsing(function () use (&$cached): string {
            return $cached;
        });
        Redis::shouldReceive('eval')->andReturnUsing(function (string $script, int $keys, string $key, string $before, string $after) use (&$cached): int {
            if ($cached === $before) {
                $cached = $after;
            }

            return 1;
        });
        $fromCache = getInRedis('Football:matches')['match-1'];
        $this->assertSame($expected, $this->timeline($fromCache));
        $this->assertSame($fromCache['timeline'], json_decode($cached, true)['match-1']['timeline']);
        $sync->storeTimeline($partial, 'match:123:incidents', ['incidents' => [['id' => 'g1', 'isDeleted' => true]]], 'update');
        unset($empty['timeline']);
        $sync->storeTimeline($empty);
        $this->assertSame(['g2'], array_column($empty['timeline']['home_goals'], 'id'));
    }

    public function test_pinnacle_uses_shared_team_similarity_and_keeps_ambiguous_matches_unmatched(): void
    {
        config(['services.sportsmeld.key' => null]);
        $this->withoutMiddleware(CustomerAuthService::class);
        $this->app['auth']->guard('customers')->setUser(new Customer(['trial_ends_at' => now()->addDay()]));
        app()->instance(FootballSyncController::class, $this->sync());
        $start = time() + 3600;
        $match = ['unique_key' => 'matched', 'team1' => 'Galatasaray FC', 'team2' => 'Chennaiyin FC', 'timestamp' => $start, 'data_sync_complete' => true, 'result_status' => 'Prematch', 'markets' => [[['marketType' => 'MATCH_ODDS', 'runner' => 'Galatasaray', 'currentBackOdds' => 1.54, 'layOdds' => 1.56, 'backSize' => 10]]]];
        $matches = [$match];
        $alerts = [['id' => 1, 'sport' => 'soccer', 'home' => 'Galatasaray', 'away' => 'Chennaiyin', 'starts' => $start, 'sect' => 'moneyline', 'outcome' => 'home', 'is_live' => false, 'period' => 0, 'from_price' => 1.8, 'to_price' => 1.47, 'nvp' => 1.52]];
        Redis::shouldReceive('get')->andReturnUsing(function (string $key) use (&$matches, &$alerts): ?string {
            return match ($key) {
                'Football:matches' => json_encode($matches),
                'Pinnacle:drops' => json_encode(['alerts' => $alerts]),
                default => null,
            };
        });
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', 1.54)->assertJsonPath('data.0.start_timestamp', $start);
        foreach ([$start * 1000, gmdate('c', $start)] as $starts) {
            $alerts[0]['starts'] = $starts;
            $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.start_timestamp', $start);
        }
        $alerts[0]['starts'] = 'invalid';
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.start_timestamp', null);
        $alerts[0]['starts'] = $start;
        $alerts[] = array_replace($alerts[0], ['id' => 2, 'parent_id' => 1]);
        unset($alerts[1]['starts']);
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.1.start_timestamp', $start);
        unset($alerts[1]);
        $matches[] = array_replace($match, ['unique_key' => 'ambiguous']);
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', null);
        $matches = [array_replace($match, ['timestamp' => $start + 7200])];
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', null);
    }

    public function test_pinnacle_binary_selections_use_cached_parent_teams_and_never_become_fixture_names(): void
    {
        config(['services.sportsmeld.key' => null]);
        $this->withoutMiddleware(CustomerAuthService::class);
        $this->app['auth']->guard('customers')->setUser(new Customer(['trial_ends_at' => now()->addDay()]));
        app()->instance(FootballSyncController::class, $this->sync());
        $start = time() + 3600;
        $child = ['id' => 200, 'parent_id' => 100, 'sport' => 'soccer', 'home' => 'Yes', 'away' => 'No',
            'league' => 'Belarusian Championship', 'sect' => 'btts', 'outcome' => 'Yes',
            'is_live' => false, 'period' => 0, 'from_price' => 1.80, 'to_price' => 1.60, 'alerted' => time()];
        $parent = [...$child, 'id' => 100, 'parent_id' => null, 'home' => 'Home Club', 'away' => 'Away Club',
            'sect' => 'Moneyline', 'outcome' => 'Home', 'starts' => $start, 'to_price' => 1.79];
        $payload = ['alerts' => [$child], 'signals' => [$child, $parent,
            [...$parent, 'home' => 'Older Home Name', 'alerted' => time() - 60],
            [...$parent, 'home' => 'Yes', 'away' => 'No']]];
        Redis::shouldReceive('get')->andReturnUsing(function (string $key) use (&$payload): ?string {
            return match ($key) {
                'Pinnacle:drops' => json_encode($payload),
                'Football:matches' => '[]',
                default => null,
            };
        });
        Http::preventStrayRequests();

        $this->getJson(route('football.pinnacle-odds'))->assertOk()
            ->assertJsonPath('data.0.home', 'Home Club')->assertJsonPath('data.0.away', 'Away Club')
            ->assertJsonPath('data.0.outcome', 'Yes')->assertJsonPath('data.0.sect', 'btts')
            ->assertJsonPath('data.0.start_timestamp', $start);

        $payload['alerts'][0]['outcome'] = 'home';
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.outcome', 'yes');
        $payload['alerts'][0]['outcome'] = 'away';
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.outcome', 'no');

        $payload['signals'] = [$child, [...$parent, 'sport' => 'basketball']];
        $this->getJson(route('football.pinnacle-odds'))->assertOk()
            ->assertJsonPath('data.0.home', null)->assertJsonPath('data.0.away', null)
            ->assertJsonPath('data.0.outcome', 'no')->assertJsonPath('data.0.bf_back', null);
        Http::assertNothingSent();
    }

    public function test_pinnacle_persists_sportsmeld_event_matches_and_reads_the_latest_exact_handicap(): void
    {
        config(['services.sportsmeld.key' => 'test-key']);
        $this->withoutMiddleware(CustomerAuthService::class);
        $this->app['auth']->guard('customers')->setUser(new Customer(['trial_ends_at' => now()->addDay()]));
        (require database_path('migrations/2026_10_01_000003_create_football_matches_table.php'))->up();
        (require database_path('migrations/2026_10_01_000004_create_football_markets_table.php'))->up();
        (require database_path('migrations/2026_11_05_070607_create_pinnacle_betfair_events_table.php'))->up();

        $start = time() + 3600;
        DB::table('football_matches')->insert([
            'unique_key' => 'fixture', 'betfair_match_id' => '900', 'team1' => 'Manchester United',
            'team2' => 'Liverpool', 'result_status' => 'Prematch', 'start_timestamp' => $start,
            'data_sync_complete' => true, 'tournament' => 'Premier League', 'payload' => '{}',
        ]);
        foreach ([
            ['wrong', -3.75, 1.4, 'Asian Handicap'],
            ['exact', -4.0, 2.15, 'Asian Handicap'],
            ['first-half', -4.0, 2.35, 'First Half Asian Handicap'],
            ['second-half', -4.0, 1.6, 'Second Half Asian Handicap'],
        ] as [$id, $handicap, $odds, $marketName]) {
            DB::table('football_markets')->insert([
                'selection_key' => $id, 'football_match_unique_key' => 'fixture', 'market_id' => $id,
                'market_type' => 'ASIAN_HANDICAP', 'payload' => json_encode([
                    'marketType' => 'ASIAN_HANDICAP', 'name' => $marketName, 'runner' => 'Liverpool', 'handicap' => $handicap, 'currentBackOdds' => $odds,
                ]),
            ]);
        }
        $alert = [
            'id' => 100, 'sport' => 'soccer', 'home' => 'Man Utd', 'away' => 'Liverpool FC', 'starts' => $start,
            'league' => 'Premier League', 'sect' => 'spread', 'outcome' => 'away', 'hdp' => 4,
            'period' => 0, 'from_price' => 2.1, 'to_price' => 1.85,
        ];
        Redis::shouldReceive('get')->with('Pinnacle:drops')->andReturn(json_encode(['alerts' => [
            $alert,
            array_replace($alert, ['period' => 1, 'point' => -4]),
            array_replace($alert, ['period' => 1, 'hdp' => 3.75]),
        ]]));
        Http::preventStrayRequests();
        Http::fake(['https://sportsmeld.arbitragex.pro/api/match' => fn ($request) => Http::response([
            'details' => [['name' => $request['names'][0], 'match' => true, 'ambiguous' => false]],
        ])]);

        $this->getJson(route('football.pinnacle-odds'))->assertOk()
            ->assertJsonPath('data.0.home', 'Man Utd')
            ->assertJsonPath('data.0.bf_home', 'Manchester United')
            ->assertJsonPath('data.0.bf_selection', 'Liverpool')
            ->assertJsonPath('data.0.bf_back', 2.15)
            ->assertJsonPath('data.1.bf_back', 2.35)
            ->assertJsonPath('data.2.bf_back', null);
        $this->assertDatabaseHas('pinnacle_betfair_events', ['sport' => 'soccer', 'pinnacle_event_id' => '100', 'betfair_event_id' => '900']);

        DB::table('football_markets')->where('market_id', 'exact')->update(['payload' => json_encode([
            'marketType' => 'ASIAN_HANDICAP', 'runner' => 'Liverpool', 'handicap' => -4, 'currentBackOdds' => 2.2,
        ])]);
        DB::table('football_markets')->where('market_id', 'first-half')->update(['payload' => json_encode([
            'marketType' => 'ASIAN_HANDICAP', 'name' => 'First Half Asian Handicap', 'runner' => 'Liverpool', 'handicap' => -4,
            'currentBackOdds' => 2.35, 'market_status' => 'SUSPENDED',
        ])]);
        $this->getJson(route('football.pinnacle-odds'))->assertOk()
            ->assertJsonPath('data.0.bf_back', 2.2)
            ->assertJsonPath('data.1.bf_back', null);
        Http::assertSentCount(2);
    }

    public function test_missing_card_times_trigger_repair_even_with_complete_goals_and_finalized_history(): void
    {
        (require database_path('migrations/2026_10_26_110659_create_football_timeline_table.php'))->up();
        (require database_path('migrations/2026_10_26_124628_add_timeline_to_football_timeline_table.php'))->up();
        Bus::fake();
        $cache = Cache::store('array');
        Cache::shouldReceive('store')->with('redis')->andReturn($cache);
        $sync = $this->sync();
        $match = ['sportsApiPro_match_id' => '123', 'timestamp' => time() - 7200, 'result_status' => 'Finished', 'minutes_status' => 'Finished', 'home_score' => 0, 'away_score' => 0, 'timeline' => ['initialized' => true, 'finalized' => true, 'reported_counts' => ['home_yellow_cards' => 2, 'away_yellow_cards' => 5]]];
        $sync->storeTimeline($match);
        Bus::assertDispatched(CallQueuedClosure::class, 1);
        $sync->storeTimeline($match, frameType: 'history');
        Bus::assertDispatched(CallQueuedClosure::class, 1);
        $this->assertSame([], $match['timeline']['home_yellow_cards']);
        $this->assertSame([], $match['timeline']['away_yellow_cards']);
        $this->assertSame(2, $match['timeline']['reported_counts']['home_yellow_cards']);
        $this->assertSame(5, $match['timeline']['reported_counts']['away_yellow_cards']);
        $cards = [
            ['id' => 'h2', 'incidentType' => 'card', 'incClass' => '', 'incidentClass' => 'yellow', 'time' => '45+2', 'isHome' => true, 'playerName' => 'Home Player'],
            ['id' => 'h1', 'incidentType' => 'card', 'from' => 'yellow', 'time' => 23, 'isHome' => true],
        ];
        foreach ([15, 35, 56, 71, 89] as $index => $minute) {
            $cards[] = ['id' => 'a'.$index, 'incidentType' => 'card', 'incidentClass' => 'yellow', 'time' => $minute, 'isHome' => false];
        }
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => $cards], 'snapshot', microtime(true));
        $this->assertSame([23, 45], array_column($match['timeline']['home_yellow_cards'], 'minute'));
        $this->assertSame(2, $match['timeline']['home_yellow_cards'][1]['added_time']);
        $this->assertSame('Home Player', $match['timeline']['home_yellow_cards'][1]['player']);
        $this->assertSame([15, 35, 56, 71, 89], array_column($match['timeline']['away_yellow_cards'], 'minute'));
        $this->assertArrayNotHasKey('home_yellow_cards', $match['timeline']['reported_counts']);
        $this->assertArrayNotHasKey('away_yellow_cards', $match['timeline']['reported_counts']);
        $expected = $this->timeline($match);
        $this->assertSame([], $expected['untimed_events']['home']);
        $this->assertSame([], $expected['untimed_events']['away']);
        $restored = $match;
        unset($restored['timeline']);
        $sync->storeTimeline($restored);
        $this->assertSame($expected, $this->timeline($restored));
        $sync->storeTimeline($restored, 'match:123:incidents', ['incidents' => $cards], 'update');
        $this->assertCount(2, $restored['timeline']['home_yellow_cards']);
        $this->assertCount(5, $restored['timeline']['away_yellow_cards']);
        $this->assertDatabaseCount('football_timeline', 1);
    }

    public function test_count_only_halftime_cards_are_explicitly_untimed_and_not_positioned_at_zero(): void
    {
        $this->app['auth']->guard('customers')->setUser(new Customer(['trial_ends_at' => now()->addDay()]));
        $match = ['timestamp' => 1793361600, 'result_status' => 'Live', 'minutes_status' => 'Half Time', 'home_score' => 1, 'away_score' => 0,
            'timeline' => ['initialized' => true, 'reported_counts' => ['home_yellow_cards' => 1, 'away_yellow_cards' => 2],
                'home_goals' => [['id' => '367764118', 'minute' => 36, 'added_time' => 0, 'seconds' => 2160, 'time_known' => true, 'home_score' => 1, 'away_score' => 0]]]];
        $timeline = $this->timeline($match);
        $this->assertSame(45, $timeline['minute']);
        $this->assertCount(1, $timeline['untimed_events']['home']);
        $this->assertCount(2, $timeline['untimed_events']['away']);
        foreach (array_merge(...array_values($timeline['untimed_events'])) as $incident) {
            $this->assertFalse($incident['time_known']);
            $this->assertNull($incident['left']);
            $html = view('users.sports.football.partials.timelineEvent', ['incident' => $incident, 'positioned' => false])->render();
            $this->assertStringNotContainsString('left:', $html);
            $this->assertStringNotContainsString('Event time unavailable', $html);
        }
        $event = ['unique_key' => 'test-match', 'sportsApiPro_match_id' => '16604830', 'betfair_match_id' => 'test', 'team1' => 'Mahasarakham SBT', 'team2' => 'Chanthaburi FC', 'status' => 'Live', 'timestamp' => $match['timestamp'], 'minutes' => 'HT', 'score' => '1 - 0', 'home_score' => 1, 'away_score' => 0, 'timeline' => $timeline, 'markets' => []];
        $html = view('users.sports.football.footballDetails', ['data' => [['tournament_name' => 'Thai League 2', 'tournament_country' => 'Thailand', 'status' => 'Live', 'matches' => [$event]]], 'append' => true, 'checkAuthAndSubscription' => ['display_odds' => true]])->render();
        $this->assertStringNotContainsString('Event time unavailable', strip_tags($html));
        $this->assertStringNotContainsString('Home:', strip_tags($html));
        $this->assertStringNotContainsString('Away:', strip_tags($html));
        $this->assertStringContainsString('left: 40%', $html);
        $this->assertSame(3, substr_count($html, 'data-timeline-event="stats-'));
    }

    public function test_card_times_preserve_provider_values_through_updates_database_cache_and_restore(): void
    {
        (require database_path('migrations/2026_10_26_110659_create_football_timeline_table.php'))->up();
        (require database_path('migrations/2026_10_26_124628_add_timeline_to_football_timeline_table.php'))->up();
        Bus::fake();
        $cache = Cache::store('array');
        Cache::shouldReceive('store')->with('redis')->andReturn($cache);
        $sync = $this->sync();
        app()->instance(FootballSyncController::class, $sync);
        $match = ['sportsApiPro_match_id' => '123', 'timestamp' => 1793361600, 'result_status' => 'Live', 'minutes_status' => 'Half Time', 'home_score' => 0, 'away_score' => 0];
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => []], 'snapshot');
        $card = ['incidentType' => 'card', 'incidentClass' => 'yellow', 'isHome' => true];
        foreach (['missing' => [], 'null' => ['time' => null], 'empty' => ['time' => ''], 'invalid' => ['time' => 'unknown']] as $id => $fields) {
            $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [array_replace($card, ['id' => $id], $fields)]], 'snapshot');
            $event = array_find($match['timeline']['home_yellow_cards'], static fn (array $event): bool => $event['id'] === $id);
            $this->assertNotNull($event);
            $this->assertNull($event['timestamp'], $id);
            $this->assertFalse($event['time_known'], $id);
        }
        $firstHalf = array_replace($card, ['id' => 'first', 'time' => 23, 'timestamp' => '2026-10-30T18:00:00+05:30']);
        $secondHalf = array_replace($card, ['id' => 'second', 'isHome' => false, 'time' => 64, 'timestamp' => 1793364000000]);
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$firstHalf, $secondHalf, array_replace($card, ['id' => 'stoppage', 'time' => '45+2']), array_replace($card, ['id' => 'zero', 'time' => 0])], 'changes' => ['changeTimestamp' => 20]]);
        Bus::assertDispatched(CallQueuedClosure::class, 1);
        $first = array_find($match['timeline']['home_yellow_cards'], static fn (array $event): bool => $event['id'] === 'first');
        $second = $match['timeline']['away_yellow_cards'][0];
        $this->assertSame('2026-10-30T12:30:00Z', $first['timestamp']);
        $this->assertSame('2026-10-30T12:40:00Z', $second['timestamp']);
        $this->assertSame(23, $first['minute']);
        $this->assertSame(64, $second['minute']);
        foreach (['Half Time', '2nd half'] as $status) {
            $display = $this->timeline(array_replace($match, ['minutes_status' => $status]));
            $rendered = array_column($display['events'], null, 'id');
            $this->assertEqualsWithDelta(23 / 90 * 100, $rendered['first']['left'], 0.001);
            $this->assertEqualsWithDelta(64 / 90 * 100, $rendered['second']['left'], 0.001);
            $this->assertSame("45+2'", $rendered['stoppage']['minute_label']);
            $this->assertTrue($rendered['zero']['time_known']);
            $this->assertNull($rendered['missing']['left']);
        }
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [array_replace($card, ['id' => 'first', 'time' => null, 'timestamp' => null])], 'changes' => ['changeTimestamp' => 21]]);
        $this->assertSame($first, array_find($match['timeline']['home_yellow_cards'], static fn (array $event): bool => $event['id'] === 'first'));
        $this->assertFalse($sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [array_replace($firstHalf, ['time' => 1])], 'changes' => ['changeTimestamp' => 19]]));
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [array_replace($card, ['id' => 'missing', 'time' => 17])], 'changes' => ['changeTimestamp' => 22]]);
        $known = array_find($match['timeline']['home_yellow_cards'], static fn (array $event): bool => $event['id'] === 'missing');
        $this->assertSame(17, $known['minute']);
        $this->assertNull($known['timestamp']);
        $before = $match['timeline'];
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$firstHalf, $secondHalf], 'changes' => ['changeTimestamp' => 22]]);
        $this->assertSame($before, $match['timeline']);
        $this->assertEquals($before, json_decode(DB::table('football_timeline')->where('match_id', '123')->value('timeline'), true));
        $cached = json_encode(['match-1' => $match]);
        Redis::shouldReceive('get')->with('Football:matches')->andReturnUsing(function () use (&$cached): string {
            return $cached;
        });
        Redis::shouldReceive('set')->andReturnUsing(function (string $key, string $value) use (&$cached): void {
            $cached = $value;
        });
        Redis::shouldReceive('expire')->andReturn(true);
        Redis::shouldReceive('eval')->andReturnUsing(function (string $script, int $keys, string $key, string $before, string $after) use (&$cached): int {
            $cached = $after;

            return 1;
        });
        $incomplete = $match;
        $incomplete['timeline'] = [];
        storeInRedis('Football:matches', ['match-1' => $incomplete], 10, false);
        $restored = getInRedis('Football:matches')['match-1'];
        $this->assertSame($before, $restored['timeline']);
        $this->assertSame($this->timeline($match), $this->timeline($restored));
    }

    public function test_new_live_cards_estimate_the_match_minute_once_and_actual_events_replace_estimates(): void
    {
        (require database_path('migrations/2026_10_26_110659_create_football_timeline_table.php'))->up();
        (require database_path('migrations/2026_10_26_124628_add_timeline_to_football_timeline_table.php'))->up();
        Bus::fake();
        $cache = Cache::store('array');
        Cache::shouldReceive('store')->with('redis')->andReturn($cache);
        $sync = $this->sync();
        $match = ['sportsApiPro_match_id' => '123', 'timestamp' => time() - 2000, 'current_period_start_timestamp' => time() - 1930, 'result_status' => 'Live', 'minutes_status' => '1st half', 'home_score' => 0, 'away_score' => 0];
        $card = ['id' => 'historical', 'incidentType' => 'card', 'incidentClass' => 'yellow', 'isHome' => false];
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$card]], 'snapshot');
        $counts = ['home_yellow_cards' => 0, 'home_red_cards' => 0, 'away_yellow_cards' => 2, 'away_red_cards' => 0];
        $sync->storeTimeline($match, payload: ['timeline_counts' => $counts], frameType: 'snapshot');
        $this->assertFalse($match['timeline']['away_yellow_cards'][0]['time_known']);
        $this->assertSame([], $match['timeline']['home_yellow_cards']);
        $counts = array_replace($counts, ['home_yellow_cards' => 1, 'home_red_cards' => 1, 'away_yellow_cards' => 3]);
        $sync->storeTimeline($match, payload: ['timeline_counts' => $counts]);
        foreach (['home_yellow_cards', 'home_red_cards'] as $group) {
            $event = $match['timeline'][$group][0];
            $this->assertSame(32, $event['minute']);
            $this->assertTrue($event['time_estimated']);
            $this->assertNull($event['timestamp']);
        }
        $this->assertCount(2, $match['timeline']['away_yellow_cards']);
        $newCard = array_replace($card, ['id' => 'live-red', 'incidentClass' => 'yellowRed']);
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$newCard]]);
        $this->assertSame(32, $match['timeline']['away_red_cards'][0]['minute']);
        $this->assertTrue($match['timeline']['away_red_cards'][0]['time_estimated']);
        $display = array_column($this->timeline($match)['events'], null, 'id');
        $this->assertSame("~32'", $display['live-red']['minute_label']);
        $this->assertEqualsWithDelta(35.555, $display['live-red']['left'], 0.01);
        $estimated = $match['timeline'];
        $match['current_period_start_timestamp'] -= 600;
        $sync->storeTimeline($match, payload: ['timeline_counts' => $counts]);
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$newCard]]);
        $this->assertSame($estimated['home_yellow_cards'], $match['timeline']['home_yellow_cards']);
        $this->assertSame($estimated['away_red_cards'], $match['timeline']['away_red_cards']);
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [array_replace($card, ['id' => 'actual-home', 'isHome' => true])]]);
        $this->assertSame(['actual-home'], array_column($match['timeline']['home_yellow_cards'], 'id'));
        $this->assertSame(32, $match['timeline']['home_yellow_cards'][0]['minute']);
        $actual = array_replace($newCard, ['time' => 18]);
        $sync->storeTimeline($match, 'match:123:incidents', ['incidents' => [$actual, array_replace($card, ['id' => 'actual-home', 'time' => 23, 'isHome' => true])]], 'snapshot');
        $this->assertSame(['actual-home'], array_column($match['timeline']['home_yellow_cards'], 'id'));
        $this->assertSame(18, $match['timeline']['away_red_cards'][0]['minute']);
        $this->assertFalse($match['timeline']['away_red_cards'][0]['time_estimated']);
        $display = array_column($this->timeline($match)['events'], null, 'id');
        $this->assertSame("18'", $display['live-red']['minute_label']);
        $stale = $match;
        $stale['timeline'] = $estimated;
        $sync->storeTimeline($stale);
        $this->assertSame($match['timeline']['away_red_cards'], $stale['timeline']['away_red_cards']);
        unset($match['timeline']);
        $sync->storeTimeline($match, frameType: 'restore');
        $this->assertSame(18, $match['timeline']['away_red_cards'][0]['minute']);
        $this->assertSame(32, $match['timeline']['home_red_cards'][0]['minute']);
        $this->assertTrue($match['timeline']['home_red_cards'][0]['time_estimated']);
        Bus::assertDispatched(CallQueuedClosure::class, 1);
        foreach ([
            ['2nd half', time() - 1150, [], 'update', true, "~64'"],
            ['1st half', time() - 2830, ['first_half' => 2], 'update', true, "~45+2'"],
            ['1st half', null, [], 'update', true, ''],
            ['1st half', time() - 1930, [], 'snapshot', true, ''],
            ['1st half', time() - 1930, [], 'update', false, ''],
        ] as $index => [$status, $start, $added, $frameType, $isLive, $label]) {
            $sample = ['sportsApiPro_match_id' => (string) (200 + $index), 'timestamp' => time() - 5000, 'current_period_start_timestamp' => $start, 'minutes_status' => $status, 'result_status' => 'Live', 'added_time' => $added];
            $channel = 'match:'.$sample['sportsApiPro_match_id'].':incidents';
            $sync->storeTimeline($sample, $channel, ['incidents' => []], 'snapshot');
            $sync->storeTimeline($sample, $channel, ['incidents' => [array_replace($card, ['id' => 'sample', 'isLive' => $isLive])]], $frameType);
            $display = array_column($this->timeline($sample)['events'], null, 'id');
            $this->assertSame($label, $display['sample']['minute_label']);
            $this->assertSame($label !== '', $sample['timeline']['away_yellow_cards'][0]['time_estimated']);
        }
    }

    public function test_all_team_sports_preserve_tournament_country_from_rest_and_websocket_fields(): void
    {
        foreach (['Cricket', 'IceHockey', 'AmericanFootball', 'Baseball', 'Basketball', 'Tennis'] as $sport) {
            $class = "App\\Http\\Controllers\\Users\\{$sport}\\{$sport}SyncController";
            $sync = (new ReflectionClass($class))->newInstanceWithoutConstructor();
            $match = ['team1' => 'Home', 'team2' => 'Away', 'result_status' => 'Prematch', 'timestamp' => time() + 3600];
            $sync->applySportsApiEvent($match, ['tournament' => ['category' => ['country' => ['name' => 'Japan']]]], 'rest');
            $this->assertSame('Japan', $match['tournament_country'], $sport);
            $sync->applySportsApiEvent($match, ['tournament.category.country.name' => 'England'], 'match:123');
            $this->assertSame('England', $match['tournament_country'], $sport);
            $sync->applySportsApiEvent($match, ['homeScore.current' => 1], 'match:123');
            $this->assertSame('England', $match['tournament_country'], $sport);
        }
    }

    public function test_sport_league_headers_display_country_without_an_empty_separator(): void
    {
        foreach (['cricket', 'icehockey', 'americanfootball', 'baseball', 'basketball', 'tennis', 'horseracing'] as $sport) {
            $name = $sport;
            $group = ['tournament_name' => 'Test League', 'tournament_country' => 'Japan', 'status' => 'Prematch', 'matches' => []];
            $html = view("users.sports.{$sport}.{$name}Details", ['data' => [$group], 'append' => true])->render();
            $this->assertStringContainsString('Japan : Test League', preg_replace('/\s+/', ' ', strip_tags($html)), $sport);
            unset($group['tournament_country']);
            $html = view("users.sports.{$sport}.{$name}Details", ['data' => [$group], 'append' => true])->render();
            $this->assertStringNotContainsString(': Test League', strip_tags($html), $sport);
        }
        $html = view('users.sports.greyhounds.greyhoundsDetails', ['data' => [['country' => 'United Kingdom', 'tournament_name' => 'Hove', 'matches' => []]], 'append' => true])->render();
        $this->assertStringContainsString('United Kingdom : Hove', strip_tags($html));
    }

    public function test_prematch_statistics_cards_show_provider_values_colored_form_and_zero_counts(): void
    {
        $event = ['team1' => 'Home', 'team2' => 'Away', 'status' => 'Prematch', 'prematch_statistics' => [
            'pregame-form' => ['homeTeam' => ['avgRating' => '7.21', 'position' => 17, 'value' => '0', 'form' => ['W', 'D', 'L']], 'awayTeam' => ['avgRating' => '7.31', 'position' => 10, 'value' => '15', 'form' => ['L', 'W']]],
            'h2h' => ['teamDuel' => ['homeWins' => 4, 'draws' => 0, 'awayWins' => 3]],
            'streaks' => ['general' => [['name' => 'No wins', 'value' => '4', 'team' => 'away'], ['name' => 'More than 2.5 goals', 'value' => '9/10', 'team' => 'home']]],
            'overview' => ['venue' => ['name' => 'Test Ground'], 'referee' => ['name' => 'Test Referee'], 'startTimestamp' => 1792843200, 'Info' => ['round' => 'Regular Season - 12']],
            'home-season' => ['avgRating' => 7.2, 'matches' => 12, 'awardedMatches' => 0],
        ]];

        $html = view('users.sports.football.partials.statisticsContent', compact('event'))->render();

        foreach (['Team Form', 'Head-to-Head', 'Team Streaks', 'Match Overview', 'Team Season Statistics', '7.21', '7.31', '9/10', 'Test Ground', 'Test Referee', 'Regular Season - 12', 'No season data available'] as $text) {
            $this->assertStringContainsString($text, strip_tags($html));
        }
        foreach (['Win', 'Draw', 'Loss'] as $result) {
            $this->assertStringContainsString('aria-label="'.$result.'"', $html);
        }
        $this->assertStringContainsString('bg-[var(--surface-emerald-600)]', $html);
        $this->assertStringContainsString('bg-[var(--surface-amber-400)]', $html);
        $this->assertStringContainsString('bg-[var(--surface-red-500)]', $html);
        $this->assertMatchesRegularExpression('~<dt[^>]*>Draws</dt><dd[^>]*>0</dd>~', $html);
        $this->assertStringNotContainsString('W · D · L', $html);
    }

    public function test_prematch_statistics_empty_data_has_placeholders_and_never_renders_live_only_fields(): void
    {
        $event = ['team1' => '<script>Home</script>', 'team2' => 'Away', 'status' => 'Live', 'prematch_statistics' => [], 'live_stats' => ['possession' => ['home' => 60, 'away' => 40], 'expected_goals' => ['home' => 2, 'away' => 1]]];

        $html = view('users.sports.football.partials.statisticsContent', compact('event'))->render();

        $this->assertStringContainsString('No data available', $html);
        $this->assertStringContainsString('No head-to-head data available', $html);
        $this->assertStringContainsString('—', $html);
        $this->assertStringContainsString('&lt;script&gt;Home&lt;/script&gt;', $html);
        foreach (['<script>', 'Live statistics', 'Ball possession', 'Expected goals'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
    }
}
