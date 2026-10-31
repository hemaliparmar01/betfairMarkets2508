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
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FixturesAuditTest extends TestCase
{
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
                'upcoming' => array_replace($base, ['unique_key' => 'upcoming', 'sportsApiPro_match_id' => '102', 'timestamp' => time() + 120, 'result_status' => 'Prematch', 'minutes_status' => 'Prematch']),
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
            }
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
            '2' => array_replace($base, ['unique_key' => '2', 'sportsApiPro_match_id' => '2']),
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
        Redis::shouldReceive('set')->andReturnUsing(static function (string $key, string $value) use (&$cache): void {
            $cache[$key] = $value;
        });
        Redis::shouldReceive('expire')->andReturn(true);
        Redis::shouldReceive('zrangebyscore')->andReturn([]);
        $lock = \Mockery::mock();
        $lock->shouldReceive('block')->andReturnUsing(static fn (int $seconds, callable $callback): mixed => $callback());
        Cache::shouldReceive('store')->with('redis')->andReturnSelf();
        Cache::shouldReceive('lock')->andReturn($lock);
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
        $controller->filterFootball();
        $this->assertSame(0, PredictionVote::query()->count());
        $requestFor($customer);
        $controller->filterFootball();
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
        $this->withoutMiddleware(CustomerAuthService::class);
        $this->app['auth']->guard('customers')->setUser(new Customer(['trial_ends_at' => now()->addDay()]));
        app()->instance(FootballSyncController::class, $this->sync());
        $start = time() + 3600;
        $match = ['unique_key' => 'matched', 'team1' => 'Galatasaray FC', 'team2' => 'Chennaiyin FC', 'timestamp' => $start, 'data_sync_complete' => true, 'result_status' => 'Prematch', 'markets' => [[['marketType' => 'MATCH_ODDS', 'runner' => 'Galatasaray', 'currentBackOdds' => 1.54, 'layOdds' => 1.56, 'backSize' => 10]]]];
        $matches = [$match];
        $alerts = [['id' => 1, 'sport' => 'soccer', 'home' => 'Galatasaray', 'away' => 'Chennaiyin', 'starts' => $start, 'sect' => 'moneyline', 'outcome' => 'home', 'is_live' => false, 'period' => 0, 'from_price' => 1.8, 'to_price' => 1.47, 'nvp' => 1.52]];
        Redis::shouldReceive('get')->andReturnUsing(function (string $key) use (&$matches, $alerts): ?string {
            return match ($key) {
                'Football:matches' => json_encode($matches),
                'Pinnacle:drops' => json_encode(['alerts' => $alerts]),
                default => null,
            };
        });
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', 1.54);
        $matches[] = array_replace($match, ['unique_key' => 'ambiguous']);
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', null);
        $matches = [array_replace($match, ['timestamp' => $start + 7200])];
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', null);
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
