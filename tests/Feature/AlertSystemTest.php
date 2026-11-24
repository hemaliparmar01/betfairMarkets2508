<?php

namespace Tests\Feature;

use App\Console\Commands\ListenBetfairOdds;
use App\Console\Commands\ListenPinnodds;
use App\Http\Controllers\Users\Football\FootballSyncController;
use App\Http\Controllers\Users\Tennis\TennisController;
use App\Http\Controllers\Users\Tennis\TennisSyncController;
use App\Jobs\ProcessAlert;
use App\Models\Customer;
use App\Models\FootballMarket;
use App\Models\FootballMatch;
use App\Models\TennisMarket;
use App\Models\TennisMatch;
use App\Repositories\CustomerRepository;
use App\Services\AlertService;
use App\Services\CustomerAuthService;
use App\Services\FootballMatchCache;
use App\Services\TennisMatchCache;
use Database\Factories\CustomerFactory;
use Illuminate\Broadcasting\AnonymousEvent;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionMethod;
use Tests\TestCase;

class AlertSystemTest extends TestCase
{
    use RefreshDatabase;

    private ?array $onlineIds = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Redis::shouldReceive('getrange')->byDefault()->andReturnUsing(static fn (string $key, int $start, int $end): string => substr(Redis::get($key) ?? '', $start, $end < 0 ? null : $end - $start + 1));
        $auth = \Mockery::mock(CustomerAuthService::class, [app(CustomerRepository::class)])->makePartial();
        $auth->shouldReceive('handle')->andReturnUsing(fn ($request, $next) => $next($request));
        $this->app->instance(CustomerAuthService::class, $auth);
        Http::preventStrayRequests();
        config(['services.telegram.enabled' => true, 'services.telegram.bot_token' => 'test-token', 'services.telegram.webhook_secret' => 'test-webhook', 'services.telegram.source_secret' => 'test-source']);
        Bus::fake([ProcessAlert::class]);
        Event::fake([AnonymousEvent::class]);
        $pusher = \Mockery::mock();
        $pusher->shouldReceive('getChannels')->with(['filter_by_prefix' => 'private-customers.'])->andReturnUsing(function (): object {
            $ids = $this->onlineIds ?? Customer::query()->pluck('id')->all();

            return (object) ['channels' => (object) array_fill_keys(array_map(fn (int $id): string => 'private-customers.'.$id.'.alerts', $ids), (object) [])];
        });
        $reverb = \Mockery::mock();
        $reverb->shouldReceive('getPusher')->andReturn($pusher);
        $broadcast = \Mockery::mock(BroadcastManager::class, [$this->app])->makePartial();
        $broadcast->shouldReceive('connection')->with('reverb')->andReturn($reverb);
        Broadcast::swap($broadcast);
    }

    public function test_tennis_live_fixtures_display_sports_api_records_without_completed_betfair_sync(): void
    {
        config(['services.tennis_match_cache' => false]);
        $base = ['timestamp' => time() - 600, 'result_status' => 'Live', 'minutes_status' => '1st Set',
            'tournament' => 'ATP Finals', 'team1' => 'Fils', 'team2' => 'Jodar', 'markets' => []];
        $matches = [
            'sports-only' => $base + ['unique_key' => 'sports-only', 'sportsApiPro_match_id' => '123', 'data_sync_complete' => false],
            'complete' => $base + ['unique_key' => 'complete', 'sportsApiPro_match_id' => '124', 'betfair_match_id' => '456', 'data_sync_complete' => true],
            'betfair-only' => $base + ['unique_key' => 'betfair-only', 'betfair_match_id' => '457', 'data_sync_complete' => true],
            'empty-sports-id' => $base + ['unique_key' => 'empty-sports-id', 'sportsApiPro_match_id' => '', 'betfair_match_id' => '458'],
            'hidden' => $base + ['unique_key' => 'hidden', 'sportsApiPro_match_id' => '125', 'internal_backup' => ['since' => time()]],
        ];
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Tennis:matches' ? json_encode($matches) : null);
        $request = Request::create('/tennis', 'GET', ['type' => 'live']);
        $this->app->instance('request', $request);
        $controller = (new \ReflectionClass(TennisController::class))->newInstanceWithoutConstructor();

        $groups = $controller->getTennisMatches();

        $displayed = collect($groups)->flatMap(fn (array $group): array => $group['matches'])->pluck('fixture_id')->sort()->values()->all();
        $this->assertSame(['123', '124'], $displayed);
    }

    private function customer(array $attributes = []): Customer
    {
        $customer = CustomerFactory::new()->create();
        $customer->forceFill(['trial_ends_at' => now()->addDays(2), 'telegram_chat_id' => (string) (10000 + $customer->id), 'telegram_available' => true, 'tg_ltd' => true, ...$attributes])->save();

        return $customer->refresh();
    }

    private function fixture(array $attributes = []): FootballMatch
    {
        $payload = ['sportsApiPro_match_id' => 12345, 'unique_key' => 'torino|monza|12345', 'team1' => 'Torino', 'team2' => 'Monza 1912', 'tournament' => 'Coppa Italia',
            'timestamp' => time() - 9 * 60, 'result_status' => 'Live', 'minutes_status' => '1st half', 'home_score' => 0, 'away_score' => 0, ...$attributes];
        $record = FootballMatch::query()->create(['unique_key' => $payload['unique_key'], 'sports_api_pro_match_id' => (string) $payload['sportsApiPro_match_id'],
            'team1' => $payload['team1'], 'team2' => $payload['team2'], 'tournament' => $payload['tournament'], 'result_status' => $payload['result_status'],
            'start_timestamp' => $payload['timestamp'], 'payload' => $payload]);
        FootballMarket::query()->create(['selection_key' => hash('sha256', $payload['unique_key']), 'football_match_unique_key' => $payload['unique_key'],
            'market_type' => 'HALF_TIME', 'runner' => 'The Draw', 'lay_odds' => 2.25, 'payload' => ['runner' => 'The Draw', 'layOdds' => 2.25]]);

        return $record;
    }

    private function source(string $text = "Match: Torino vs Monza 1912\nLeague: Coppa Italia\n1st Half Over 0.5 Goals"): array
    {
        return ['source' => 'wageroddss', 'message_id' => 10, 'sent_at' => time(), 'text' => $text];
    }

    private function start(string $token, int $chatId = 555): array
    {
        return ['update_id' => 1, 'message' => ['text' => '/start '.$token, 'chat' => ['id' => $chatId, 'type' => 'private'], 'from' => ['id' => $chatId, 'is_bot' => false, 'username' => 'tester']]];
    }

    private function delivery(int $alertId, Customer $customer, string $channel = 'telegram'): int
    {
        app(AlertService::class)->fanout($alertId);

        return (int) DB::table('alert_deliveries')->where('alert_id', $alertId)->where('customer_id', $customer->id)->where('channel', $channel)->value('id');
    }

    public function test_sports_lock_contention_does_not_requeue_after_the_last_worker_attempt(): void
    {
        $job = \Mockery::mock(CallQueuedClosure::class);
        $job->shouldReceive('attempts')->andReturn(1, 2, 3);
        $job->shouldReceive('displayName')->once()->andReturn('Sports test');
        $job->shouldReceive('release')->once()->with(10);
        $job->shouldReceive('release')->once()->with(20);

        $this->assertTrue(releaseSportsJobAfterLockContention($job));
        $this->assertTrue(releaseSportsJobAfterLockContention($job));
        $this->assertFalse(releaseSportsJobAfterLockContention($job));
    }

    public function test_notifications_require_auth_and_new_preferences_have_document_defaults(): void
    {
        $this->get('/notifications')->assertRedirect(route('login'));
        $customer = CustomerFactory::new()->create();
        $page = $this->actingAs($customer, 'customers')->get('/notifications')->assertOk()->assertSee('Platform Live Pop-up Alerts')
            ->assertSee('data-status-url="'.route('notifications.status').'"', false)
            ->assertDontSee('id="btnDisconnectTelegram"', false)
            ->assertDontSee('Open Telegram Web');
        $this->assertSame(1, substr_count($page->getContent(), 'id="btnConnectTelegram"'));
        $response = $this->getJson('/notifications/status')->assertOk()->assertJsonPath('popup_enabled', true);
        foreach (AlertService::FILTERS as $type) {
            $response->assertJsonPath('preferences.'.$type, false);
        }
        $this->assertArrayNotHasKey('telegram_chat_id', $customer->toArray());
    }

    public function test_linking_is_single_use_and_disconnect_retains_preferences(): void
    {
        $customer = $this->customer(['telegram_chat_id' => null, 'telegram_available' => false]);
        $response = $this->actingAs($customer, 'customers')->postJson('/notifications/telegram/connect')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        parse_str(parse_url($response->json('url'), PHP_URL_QUERY), $query);
        $this->assertSame('https://web.telegram.org/k/#?tgaddr='.rawurlencode('tg://resolve?domain=BFMarketsAlertsBot&start='.$query['start']), $response->json('web_url'));
        $this->assertNotSame($query['start'], $customer->fresh()->telegram_token_hash);
        $this->postJson('/notifications/telegram/webhook', $this->start($query['start']), ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook'])->assertOk();
        $this->assertSame('555', $customer->fresh()->telegram_chat_id);
        $this->assertNull($customer->fresh()->telegram_token_hash);
        $this->actingAs($customer->fresh(), 'customers')->getJson('/notifications/status')->assertOk()->assertJsonPath('connected', true)
            ->assertJsonPath('username', 'tester')->assertJsonPath('preferences.ltd', true);
        $this->postJson('/notifications/telegram/webhook', $this->start($query['start'], 777), ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook'])->assertOk();
        $this->assertSame('555', $customer->fresh()->telegram_chat_id);
        $this->deleteJson('/notifications/telegram')->assertOk()->assertJsonPath('connected', false)->assertJsonPath('preferences.ltd', true);
        $this->assertTrue($customer->fresh()->tg_ltd);
    }

    public function test_invalid_expired_tokens_duplicate_accounts_and_untrusted_webhooks_cannot_bind(): void
    {
        $customer = $this->customer(['telegram_chat_id' => null, 'telegram_available' => false]);
        $this->postJson('/notifications/telegram/webhook', $this->start(str_repeat('a', 48)))->assertForbidden();
        app(AlertService::class)->bind($this->start(str_repeat('a', 48)));
        $this->assertNull($customer->fresh()->telegram_chat_id);
        $customer->forceFill(['telegram_token_hash' => hash('sha256', str_repeat('a', 48)), 'telegram_token_expires_at' => now()->subMinute()])->save();
        app(AlertService::class)->bind($this->start(str_repeat('a', 48)));
        $this->assertNull($customer->fresh()->telegram_chat_id);
        $this->assertStringContainsString('expired', $customer->fresh()->telegram_error);
        $other = $this->customer(['telegram_chat_id' => '555']);
        $customer->forceFill(['telegram_token_hash' => hash('sha256', str_repeat('b', 48)), 'telegram_token_expires_at' => now()->addMinute()])->save();
        app(AlertService::class)->bind($this->start(str_repeat('b', 48)));
        $this->assertNull($customer->fresh()->telegram_chat_id);
        $this->assertSame('555', $other->fresh()->telegram_chat_id);
    }

    public function test_bot_listener_reports_api_errors_without_exposing_credentials_or_advancing_updates(): void
    {
        $responses = Http::sequence()
            ->push(['ok' => false, 'error_code' => 409, 'description' => 'Conflict: terminated by other getUpdates request'], 409)
            ->push(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401)
            ->push(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests: retry after 30', 'parameters' => ['retry_after' => 30]], 429)
            ->push('<html>Service unavailable</html>', 503, ['Content-Type' => 'text/html'])
            ->push(['ok' => false, 'error_code' => 500, 'description' => 'Failed endpoint https://api.telegram.org/bottest-token/getUpdates'], 500);
        Http::fake(['https://api.telegram.org/bottest-token/getUpdates' => $responses]);
        Cache::put('alerts:bot-offset', 12);

        foreach ([
            'Bot updates unavailable (HTTP 409, code 409): Conflict: terminated by other getUpdates request',
            'Bot updates unavailable (HTTP 401, code 401): Unauthorized',
            'Bot updates unavailable (HTTP 429, code 429): Too Many Requests: retry after 30',
            'Bot updates unavailable (HTTP 503, code 503): Telegram returned no error description.',
            'Bot updates unavailable (HTTP 500, code 500): Failed endpoint https://api.telegram.org/bot[REDACTED]/getUpdates',
        ] as $message) {
            $this->artisan('telegram:listen', ['--once' => true])
                ->expectsOutput($message)
                ->doesntExpectOutputToContain('test-token')
                ->assertExitCode(1);

            $this->assertSame(12, Cache::get('alerts:bot-offset'));
            $lock = Cache::lock('alerts:bot-listener', 60);
            $this->assertTrue($lock->get());
            $lock->release();
        }
        Http::assertSentCount(5);
    }

    public function test_bot_listener_binds_received_start_and_advances_the_existing_offset(): void
    {
        $token = str_repeat('c', 48);
        $customer = $this->customer(['telegram_chat_id' => null, 'telegram_available' => false,
            'telegram_token_hash' => hash('sha256', $token), 'telegram_token_expires_at' => now()->addMinutes(5)]);
        Cache::put('alerts:bot-offset', 1);
        Http::fake(['https://api.telegram.org/bottest-token/getUpdates' => Http::response(['ok' => true, 'result' => [$this->start($token)]])]);

        $this->artisan('telegram:listen', ['--once' => true])->assertExitCode(0);

        $this->assertSame('555', $customer->fresh()->telegram_chat_id);
        $this->assertTrue($customer->fresh()->telegram_available);
        $this->assertNull($customer->fresh()->telegram_token_hash);
        $this->assertSame(2, Cache::get('alerts:bot-offset'));
        Http::assertSent(fn ($request): bool => $request->method() === 'POST' && $request['offset'] === 1
            && $request['timeout'] === 0 && $request['allowed_updates'] === ['message']);
    }

    public function test_save_and_popup_mute_are_independent_and_reject_unknown_preferences(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customers')->patchJson('/notifications', ['preferences' => array_fill_keys(AlertService::FILTERS, true)])->assertOk();
        $this->patchJson('/notifications', ['popup_enabled' => false])->assertOk()->assertJsonPath('preferences.ht00', true)->assertJsonPath('popup_enabled', false);
        $this->patchJson('/notifications', ['preferences' => ['admin' => true]])->assertUnprocessable();
        $this->assertFalse($customer->fresh()->tg_market_shock);
    }

    public function test_ltd_source_uses_existing_match_similarity_and_real_half_time_draw_lay_odds(): void
    {
        $match = $this->fixture();
        $id = app(AlertService::class)->source($this->source());
        $this->assertNotNull($id);
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertSame(2.25, $payload['context']['draw_lay']);
        $this->assertSame(AlertService::STRATEGY['en'], $payload['templates']['en']['strategy_label']);
        $this->assertSame(AlertService::STRATEGY['ka'], $payload['templates']['ka']['strategy_label']);
        $this->assertStringContainsString('Action: LAY THE DRAW (LTD)', app(AlertService::class)->text($payload));
        $this->assertStringContainsString('Informational signal only. Trade responsibly.', app(AlertService::class)->text($payload));
        $this->assertSame($id, app(AlertService::class)->source($this->source()));
        $this->assertDatabaseCount('alerts', 1);
        Bus::assertDispatched(ProcessAlert::class, fn ($job) => $job->task === 'fanout');
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_source_prematch_signal_sends_immediately_with_betfair_odds_and_deduplicates_after_kickoff(bool $hasSportsFixture): void
    {
        $fixture = $this->fixture(['team1' => 'Ukraine', 'team2' => 'Georgia', 'tournament' => 'UEFA Nations League',
            'result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'timestamp' => time() + 3600]);
        $fixture->update(['betfair_match_id' => '900', 'sports_api_pro_match_id' => $hasSportsFixture ? '12345' : null]);
        FootballMarket::query()->create(['selection_key' => hash('sha256', 'first-half-over'), 'football_match_unique_key' => $fixture->unique_key,
            'market_type' => 'FIRST_HALF_GOALS_05', 'runner' => 'Over 0.5 Goals', 'payload' => ['runner' => 'Over 0.5 Goals',
                'backOdds' => 1.49, 'currentBackOdds' => 9.99, 'backSize' => 100, 'liquidity_updated_at' => microtime(true), 'market_status' => 'OPEN']]);
        $customer = $this->customer(['popup_enabled' => false]);
        $text = "Football. UEFA Nations League\n17 Nov 2026 (21:45)\nUkraine VS Georgia\nTotal. Over (0.5). 1st half 1.49\nStatus: Accepted";
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);

        $id = app(AlertService::class)->source($this->source($text));
        $this->assertNotNull($id);
        app(AlertService::class)->deliver($this->delivery($id, $customer));

        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'Status: Prematch') && str_contains($request['text'], 'Betfair Back Odds: 1.49') && ! str_contains($request['text'], '9.99'));
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertNull($payload['minute']);
        $this->assertSame('PREMATCH', $payload['templates']['en']['status_label']);
        $this->assertSame('მატჩამდე', $payload['templates']['ka']['status_label']);
        $fixture->update(['result_status' => 'Live', 'start_timestamp' => time() - 600,
            'sports_api_pro_match_id' => '12345',
            'payload' => array_replace($fixture->payload, ['result_status' => 'Live', 'minutes_status' => '1st half', 'timestamp' => time() - 600])]);
        $again = app(AlertService::class)->source(array_replace($this->source(str_replace('1.49', '1.55', $text)), ['message_id' => 99]));
        $this->assertSame($id, $again);
        $this->assertDatabaseCount('alerts', 1);
        app(AlertService::class)->deliver($this->delivery($id, $customer));
        Http::assertSentCount(1);
        $followUp = app(AlertService::class)->source(array_replace($this->source($text."\nGOAL!\nScore: 0 - 1\nTime: 10'"), ['message_id' => 100]));
        $this->assertNotNull($followUp);
        $this->assertDatabaseHas('alerts', ['id' => $followUp, 'parent_alert_id' => $id]);
    }

    #[TestWith(['Total 1. (0.5) Over. 1st half 1.5'])]
    #[TestWith(['Total 1 (0,5) Over 1st-half 1.50'])]
    #[TestWith(['Total 1. 0.5 Over. 1st half 1.5'])]
    public function test_source_prematch_team_total_with_unreadable_league_sends_for_one_exact_fixture(string $market): void
    {
        $this->freezeTime();
        $fixture = $this->fixture(['team1' => 'Monaco', 'team2' => 'Auxerre', 'tournament' => 'French Ligue 1',
            'result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'timestamp' => now()->addHour()->timestamp]);
        $this->fixture(['unique_key' => 'betfair-monaco', 'sportsApiPro_match_id' => 98765, 'team1' => 'AS Monaco', 'team2' => 'AJ Auxerre',
            'tournament' => 'Ligue 1', 'result_status' => 'Prematch', 'timestamp' => now()->addHour()->timestamp]);
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
        $text = "Footba11. France. Ligue 1\n21 Nov 2026 (21:45)\nAS Monaco VS Auxerre\n".$market."\nStatus: Accepted";

        $id = app(AlertService::class)->source($this->source($text));

        $this->assertNotNull($id);
        app(AlertService::class)->deliver($this->delivery($id, $customer));
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertSame('team_total_goals', $payload['context']['signal']['family']);
        $this->assertSame(1, $payload['context']['signal']['team']);
        $this->assertFalse($payload['context']['signal']['is_ltd']);
        $this->assertNull($payload['minute']);
        $this->assertSame($fixture->tournament, $payload['league']);
        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'Status: Prematch') && str_contains($request['text'], 'Team 1'));
    }

    #[TestWith(['Auxerre United', 'Ligue 1', 0, false])]
    #[TestWith(['Auxerre', 'Ligue 2', 0, true])]
    #[TestWith(['Auxerre', 'Ligue 1', 7200, true])]
    public function test_source_without_a_readable_league_rejects_fuzzy_or_ambiguous_fixtures(string $away, string $league, int $offset, bool $secondFixture): void
    {
        $this->freezeTime();
        $this->fixture(['team1' => 'Monaco', 'team2' => $away, 'tournament' => $league,
            'result_status' => 'Prematch', 'timestamp' => now()->addHour()->timestamp + $offset]);
        if ($secondFixture) {
            $this->fixture(['unique_key' => 'other-monaco', 'sportsApiPro_match_id' => 98765, 'team1' => 'Monaco', 'team2' => 'Auxerre',
                'tournament' => 'Ligue 1', 'result_status' => 'Prematch', 'timestamp' => now()->addHour()->timestamp]);
        }
        $text = "AS Monaco VS Auxerre\nTotal 1. (0.5) Over. 1st half 1.5";

        $this->assertNull(app(AlertService::class)->source($this->source($text)));
        $this->assertDatabaseCount('alerts', 0);
        Http::assertNothingSent();
    }

    public function test_source_never_ignores_a_readable_conflicting_league(): void
    {
        $this->fixture(['team1' => 'Monaco', 'team2' => 'Auxerre', 'tournament' => 'Ligue 1', 'result_status' => 'Prematch', 'timestamp' => time() + 3600]);

        $id = app(AlertService::class)->source($this->source("League: Ligue 2\nAS Monaco VS Auxerre\nTotal 1. (0.5) Over. 1st half 1.5"));

        $this->assertNull($id);
        $this->assertDatabaseCount('alerts', 0);
        Http::assertNothingSent();
    }

    #[TestWith(['Venezia', 'Serie A', 'Football. Italy. Serie A'])]
    #[TestWith(['Venezia', 'Italian Serie A', 'Football. Italy. Serie A'])]
    #[TestWith(['Unione Venezia', 'Serie A', 'Football. Italy. Serie A'])]
    #[TestWith(['Venezia', 'Serie A', 'Footba11. Italy. Serie A'])]
    public function test_source_juventus_venezia_prematch_card_delivers_without_live_data(string $away, string $league, string $header): void
    {
        $this->freezeTime();
        $this->fixture(['team1' => 'Juventus', 'team2' => $away, 'tournament' => $league, 'timestamp' => now()->addHour()->timestamp,
            'result_status' => 'Prematch', 'minutes_status' => 'Prematch']);
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 82]])]);
        $text = $header."\n22 Nov 2026 (13:30)\nJuventus VS Unione Venezia\nTotal 1. (0.5) Over. 1st half 1.5\nStatus: Accepted";

        $id = app(AlertService::class)->source($this->source($text));

        $this->assertNotNull($id);
        app(AlertService::class)->deliver($this->delivery($id, $customer));
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $customer->id, 'status' => 'sent']);
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertSame('team_total_goals', $payload['context']['signal']['family']);
        $this->assertSame(1, $payload['context']['signal']['team']);
        $this->assertNull($payload['minute']);
        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'Status: Prematch') && str_contains($request['text'], 'Team 1'));
        Http::assertSentCount(1);
    }

    #[TestWith(['v'])]
    #[TestWith(['vs'])]
    #[TestWith(['VS'])]
    public function test_source_alert_uses_the_same_club_normalization_as_pinnacle(string $separator): void
    {
        $this->fixture(['team1' => 'Liverpool FC', 'team2' => 'Manchester United', 'tournament' => 'English Premier League',
            'result_status' => 'Prematch', 'timestamp' => time() + 3600]);
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 83]])]);

        $id = app(AlertService::class)->source($this->source('Liverpool '.$separator." Man Utd\nLeague: Premier League\n1st Half Over 0.5"));

        $this->assertNotNull($id);
        app(AlertService::class)->deliver($this->delivery($id, $customer));
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $customer->id, 'status' => 'sent']);
        Http::assertSentCount(1);
    }

    #[TestWith(['Sassuolo', 'Genoa', 'Italian Serie A', "Footba11 Italy Serie A\nSassuolo Calcio a VS I Genoa\nTotal. Over (0.5). 1st half 1.52\nLIVE time elapsed: 04:47"])]
    #[TestWith(['Hull', 'Brighton', 'English Premier League', "Footba11 England Premier League\nHull City\n0:0\nBrighton &\nHove Albion\n0:0\nTotal. Over (0.5). 1st half 1.512\ntime elapsed: 13:36"])]
    #[TestWith(['Paris Saint Germain', 'Angers', 'French Ligue 1', "Footba11 France Ligue 1\nParis\n0:0\nAngers SCO\n0:0\nTotal. Over (0.5). 1st half 1.52\ntime elapsed: 17:13"])]
    public function test_source_recovers_fixture_names_from_noisy_or_multiline_card_text(string $home, string $away, string $league, string $text): void
    {
        $this->fixture(['team1' => $home, 'team2' => $away, 'tournament' => $league]);
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 84]])]);

        $id = app(AlertService::class)->source($this->source($text));

        $this->assertNotNull($id);
        app(AlertService::class)->deliver($this->delivery($id, $customer));
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $customer->id, 'status' => 'sent']);
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertSame($home.' vs '.$away, $payload['match']);
        Http::assertSentCount(1);
    }

    public function test_source_does_not_guess_between_paris_clubs_when_the_card_only_says_paris(): void
    {
        $this->fixture(['team1' => 'Paris Saint Germain', 'team2' => 'Angers', 'tournament' => 'Ligue 1']);
        $this->fixture(['unique_key' => 'paris-fc', 'sportsApiPro_match_id' => 777, 'team1' => 'Paris FC', 'team2' => 'Angers', 'tournament' => 'Ligue 1']);

        $id = app(AlertService::class)->source($this->source("Paris\n0:0\nAngers SCO\nTotal. Over (0.5). 1st half"));

        $this->assertNull($id);
        $this->assertDatabaseCount('alerts', 0);
        Http::assertNothingSent();
    }

    #[TestWith(['Stade Rennais', 'Lorient 0:0 Stade Rennais', 'Football. France. Ligue 1'])]
    #[TestWith(['Rennes', 'Lorient 0:0 Stade Rennais', 'Football. France. Ligue 1'])]
    #[TestWith(['Stade Rennais', 'Lorient @ 0:0 m Stade Rennais', 'Footba11 France Ligue 1'])]
    #[TestWith(['Rennes', 'Lorient @ 0:0 m Stade Rennais', 'Footba11 France Ligue 1'])]
    public function test_source_lorient_rennais_card_is_delivered_for_the_provider_club_name(string $away, string $teams, string $header): void
    {
        $this->fixture(['team1' => 'Lorient', 'team2' => $away, 'tournament' => 'French Ligue 1']);
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 85]])]);
        $text = $header."\n22 Nov 2026 (18:00)\n".$teams."\n0:0\nTotal. Over (0.5). 1st half 1.52\ntime elapsed: 04:09";

        $id = app(AlertService::class)->source($this->source($text));

        $this->assertNotNull($id);
        app(AlertService::class)->deliver($this->delivery($id, $customer));
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $customer->id, 'status' => 'sent']);
        Http::assertSentCount(1);
    }

    public function test_temporary_source_mode_delivers_placeholders_without_a_fixture_and_deduplicates_edits(): void
    {
        config(['services.telegram.allow_unresolved_source' => true]);
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 86]])]);
        $message = $this->source("???\nTotal. Over (0.5). 1st half 1.52");

        $id = app(AlertService::class)->source($message);

        $this->assertNotNull($id);
        app(AlertService::class)->deliver($this->delivery($id, $customer));
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertTrue($payload['context']['unresolved_fixture']);
        $this->assertSame('—', $payload['league']);
        $this->assertNull($payload['score']);
        $this->assertNull($payload['minute']);
        $this->assertSame('—', $payload['templates']['en']['status_label']);
        $this->assertDatabaseCount('football_matches', 0);
        $this->assertSame($id, app(AlertService::class)->source([...$message, 'text' => $message['text']."\nStatus: Accepted"]));
        app(AlertService::class)->deliver($this->delivery($id, $customer));
        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'League: —') && str_contains($request['text'], 'Status: —') && str_contains($request['text'], 'Betfair Back Odds: —'));
        $this->fixture();
        $this->assertSame($id, app(AlertService::class)->source([...$this->source(), 'message_id' => $message['message_id']]));
        $this->assertDatabaseCount('alerts', 1);
        Http::assertSentCount(1);
    }

    public function test_temporary_source_mode_sends_the_exact_lorient_ocr_failure_with_missing_fields_as_dashes(): void
    {
        config(['services.telegram.allow_unresolved_source' => true]);
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 88]])]);

        $id = app(AlertService::class)->source([...$this->source("Footba11 France Ligue 1\nLorient @ 0:0 m Stade Rennais\nTotal. Over (0.5). 1st half 1.52\ntime elapsed: 04:09"), 'message_id' => 4656]);

        $this->assertNotNull($id);
        app(AlertService::class)->deliver($this->delivery($id, $customer));
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertTrue($payload['context']['unresolved_fixture']);
        $this->assertSame('—', $payload['league']);
        $this->assertSame('0 - 0', $payload['score']);
        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'League: —') && str_contains($request['text'], 'Betfair Back Odds: —'));
        Http::assertSentCount(1);
    }

    public function test_temporary_source_mode_can_be_disabled_and_never_forwards_ads_or_stale_posts(): void
    {
        $message = $this->source("???\nTotal. Over (0.5). 1st half");
        config(['services.telegram.allow_unresolved_source' => false]);
        $this->assertNull(app(AlertService::class)->source($message));
        config(['services.telegram.allow_unresolved_source' => true]);
        $this->assertNull(app(AlertService::class)->source($this->source('Registration bonus deposit')));
        $this->assertNull(app(AlertService::class)->source([...$message, 'source' => 'untrusted']));
        $this->assertNull(app(AlertService::class)->source([...$message, 'sent_at' => time() - 3600]));
        $this->assertDatabaseCount('alerts', 0);
        Http::assertNothingSent();
    }

    public function test_temporary_source_mode_links_goal_replies_to_the_original_placeholder_alert(): void
    {
        config(['services.telegram.allow_unresolved_source' => true]);
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 87]])]);
        $service = app(AlertService::class);
        $parent = $service->source($this->source("???\nTotal. Over (0.5). 1st half"));
        $this->assertNotNull($parent);
        $service->deliver($this->delivery($parent, $customer));
        $this->travel(2)->seconds();
        $goal = $service->source([...$this->source("GOAL!\nScore: 1 - 0\nTime: 20'"), 'message_id' => 11, 'reply_message_id' => 10]);

        $this->assertNotNull($goal);
        $service->deliver($this->delivery($goal, $customer));
        $this->assertDatabaseHas('alerts', ['id' => $goal, 'parent_alert_id' => $parent]);
        Http::assertSentCount(2);
    }

    public function test_source_deduplicates_exact_market_period_and_selection_but_allows_other_markets_on_same_match(): void
    {
        $fixture = $this->fixture();
        $ids = [];
        foreach (['1st Half Over 0.5', 'Full Time Over 0.5', 'Over 1.5', 'BTTS Yes', 'BTTS No', '2nd Half Over 1.5', 'Market: Asian Handicap\nSelection: Home -0.5'] as $index => $market) {
            $market = str_replace('\\n', "\n", $market);
            $message = array_replace($this->source("Match: Torino vs Monza 1912\nLeague: Coppa Italia\n".$market), ['message_id' => $index + 1]);
            $id = app(AlertService::class)->source($message);
            $this->assertNotNull($id, $market);
            $this->assertNotContains($id, $ids);
            $ids[] = $id;
            $this->assertSame($id, app(AlertService::class)->source(array_replace($message, ['message_id' => 100 + $index])));
        }
        $this->assertDatabaseCount('alerts', 7);
        $payload = json_decode(DB::table('alerts')->find($ids[3])->payload, true);
        $this->assertStringContainsString('Market: BTTS Yes', app(AlertService::class)->text($payload));
        $this->assertStringNotContainsString('LAY THE DRAW', app(AlertService::class)->text($payload));
        $this->assertNotSame(AlertService::STRATEGY['ka'], $payload['templates']['ka']['strategy_label']);
    }

    public function test_source_status_results_follow_their_exact_market_parent_even_without_a_goal(): void
    {
        $this->fixture();
        $no = app(AlertService::class)->source($this->source("Match: Torino vs Monza 1912\nLeague: Coppa Italia\nBTTS No"));
        $over = app(AlertService::class)->source($this->source("Match: Torino vs Monza 1912\nLeague: Coppa Italia\nOver 1.5"));
        $customer = $this->customer(['popup_enabled' => false]);
        $parentDelivery = $this->delivery($no, $customer);
        DB::table('alert_deliveries')->where('id', $parentDelivery)->update(['status' => 'sent']);
        $message = $this->source("Match: Torino vs Monza 1912\nLeague: Coppa Italia\nBTTS No\nStatus: Won\nScore: 0 - 0\nTime: 90'");

        $won = app(AlertService::class)->source($message);
        $this->assertNotNull($won);
        $this->assertDatabaseHas('alerts', ['id' => $won, 'parent_alert_id' => $no]);
        $this->assertNotSame($over, DB::table('alerts')->find($won)->parent_alert_id);
        $this->assertSame($won, app(AlertService::class)->source(array_replace($message, ['message_id' => 99])));
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
        app(AlertService::class)->deliver($this->delivery($won, $customer));
        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'Market: BTTS No') && str_contains($request['text'], 'Status: WON'));
        $lost = app(AlertService::class)->source(array_replace($message, ['text' => str_replace('Status: Won', 'Status: Lost', $message['text'])]));
        $this->assertNotSame($won, $lost);
        $payload = json_decode(DB::table('alerts')->find($lost)->payload, true);
        $this->assertStringContainsString('Status: LOST', app(AlertService::class)->text($payload));
        $this->assertStringNotContainsString('SIGNAL WON', app(AlertService::class)->text($payload));
    }

    public function test_source_result_replies_to_a_duplicate_post_resolve_the_original_market_without_repeating_teams(): void
    {
        $this->fixture();
        $initial = $this->source("Match: Torino vs Monza 1912\nLeague: Coppa Italia\nBTTS No");
        $parent = app(AlertService::class)->source($initial);
        $this->assertSame($parent, app(AlertService::class)->source(array_replace($initial, ['message_id' => 99])));
        app(AlertService::class)->source(array_replace($initial, ['message_id' => 98, 'text' => str_replace('BTTS No', 'Over 1.5', $initial['text'])]));

        $result = app(AlertService::class)->source(array_replace($this->source('Status: Won'), ['message_id' => 100, 'reply_message_id' => 99]));

        $this->assertNotNull($result);
        $this->assertDatabaseHas('alerts', ['id' => $result, 'parent_alert_id' => $parent]);
        $this->assertDatabaseCount('alerts', 3);
    }

    public function test_ltd_parses_international_image_cards_without_country_and_does_not_read_kickoff_as_a_score(): void
    {
        $this->fixture(['team1' => 'Ukraine', 'team2' => 'Georgia', 'tournament' => 'UEFA Nations League']);
        $text = "Football. UEFA Nations League\n17 Nov 2026 (21:45)\nUkraine VS Georgia\nTotal. Over (0.5). 1st half\n1.49\nStatus: Accepted\n👉 A goal in the first half\nRegistration link on the 1Xbet website\nhttps://example.test\nCODE POMO: HNV3";

        $id = app(AlertService::class)->source($this->source($text));

        $this->assertNotNull($id);
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertSame('Ukraine vs Georgia', $payload['match']);
        $this->assertSame('0 - 0', $payload['score']);
        $this->assertStringNotContainsString('21 - 45', json_encode($payload));
        $this->assertStringNotContainsString('HNV3', json_encode($payload));
    }

    #[TestWith(['Arsenal L.F.C. (Women) 0:0 Lyon (Women)'])]
    #[TestWith(["Arsenal L.F.C.\n(Women) 0:0 Lyon (Women)"])]
    public function test_ltd_parses_wrapped_womens_teams_and_preserves_the_full_competition(string $teams): void
    {
        $this->fixture(['team1' => 'Arsenal L.F.C. (Women)', 'team2' => 'Lyon (Women)', 'tournament' => 'UEFA Champions League Women']);
        $text = "Football. UEFA Champions League. Women\n19 Nov 2026 (21:45)\n".$teams."\n0:0\nTotal. Over (0.5). 1st half 1.512\nLIVE\ntime elapsed: 21:07\nStatus: Accepted\n👉 A goal in the first half";

        $id = app(AlertService::class)->source($this->source($text));

        $this->assertNotNull($id);
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertSame('Arsenal L.F.C. (Women) vs Lyon (Women)', $payload['match']);
        $this->assertSame('UEFA Champions League Women', $payload['league']);
        $this->assertSame('0 - 0', $payload['score']);
        $this->assertTrue($payload['context']['signal']['is_ltd']);
    }

    public function test_source_womens_card_resolves_provider_aliases_and_its_goal_reply(): void
    {
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 80]])]);
        $sports = $this->fixture(['unique_key' => 'arsenal-lyonnes-sports', 'sportsApiPro_match_id' => 17018549,
            'team1' => 'Arsenal', 'team2' => 'OL Lyonnes', 'tournament' => 'UEFA Champions League, Women']);
        $betfair = FootballMatch::query()->create(['unique_key' => 'arsenal-lyon-betfair', 'betfair_match_id' => '36325159',
            'team1' => 'Arsenal (W)', 'team2' => 'Lyon (W)', 'tournament' => "UEFA Women's Champions League", 'result_status' => 'Live',
            'start_timestamp' => $sports->start_timestamp, 'payload' => ['team1' => 'Arsenal (W)', 'team2' => 'Lyon (W)', 'markets' => []]]);
        $text = "Football. UEFA Champions League. Women\n19 Nov 2026 (21:45)\nArsenal L.F.C.\n(Women) 0:0 Lyon (Women)\nTotal. Over (0.5). 1st half 1.512\ntime elapsed: 21:07\nA goal in the first half";

        $parent = app(AlertService::class)->source([...$this->source($text), 'message_id' => 4562]);

        $this->assertNotNull($parent);
        $parentPayload = json_decode(DB::table('alerts')->find($parent)->payload, true);
        $this->assertSame('17018549', DB::table('alerts')->find($parent)->match_id);
        $this->assertSame($betfair->betfair_match_id, $parentPayload['context']['betfair_match_id']);
        app(AlertService::class)->deliver($this->delivery($parent, $customer));
        $this->travel(2)->seconds();

        $goal = app(AlertService::class)->source([...$this->source("EUROPE: UEFA CHAMPIONS LEAGUE WOMEN\n19.11.2026 22:00\n0-1\n1st Half - 25:28\nArsenal W\nOL Lyonnes W\n🚀GOOOOOAAAAAALLLLLL ⚽"),
            'message_id' => 4563, 'reply_message_id' => 4562, 'reply_text' => $text]);

        $this->assertNotNull($goal);
        $payload = json_decode(DB::table('alerts')->find($goal)->payload, true);
        $this->assertSame($parent, DB::table('alerts')->find($goal)->parent_alert_id);
        $this->assertSame('0 - 1', $payload['score']);
        app(AlertService::class)->deliver($this->delivery($goal, $customer));
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $parent, 'customer_id' => $customer->id, 'channel' => 'telegram', 'status' => 'sent']);
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $goal, 'customer_id' => $customer->id, 'channel' => 'telegram', 'status' => 'sent']);
        Http::assertSentCount(2);
        $this->assertSame($goal, app(AlertService::class)->source([...$this->source("GOAL!\nScore: 0 - 1\nTime: 25'"),
            'message_id' => 4563, 'reply_message_id' => 4562]));
    }

    public function test_source_womens_signal_does_not_match_a_mens_fixture_with_the_same_team_names(): void
    {
        $this->fixture(['team1' => 'Arsenal', 'team2' => 'Lyon', 'tournament' => 'UEFA Champions League']);

        $id = app(AlertService::class)->source($this->source("Match: Arsenal (Women) vs Lyon (Women)\nLeague: UEFA Champions League Women\n1st Half Over 0.5"));

        $this->assertNull($id);
        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_ltd_parses_image_card_text_and_drops_the_promotional_footer(): void
    {
        $this->fixture(['team1' => 'Leeds', 'team2' => 'Tottenham', 'tournament' => 'English Premier League']);
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 45]])]);
        $text = "Football. England. Premier League\nLeeds United 0:0 Tottenham\nHotspur\n0:0\nTotal. Over (0.5). 1st half\n👉 Goal in the first half\nFull Time Match navigation\nRegistration link on the 1xbet website\nhttps://example.test\nCODE POMO: HNV3";

        $id = app(AlertService::class)->source($this->source($text));
        $this->assertNotNull($id);
        $alert = DB::table('alerts')->find($id);
        $this->assertSame('ltd', $alert->type);
        $this->assertStringNotContainsString('1xbet', $alert->payload);
        $this->assertStringNotContainsString('HNV3', $alert->payload);
        app(AlertService::class)->deliver($this->delivery($id, $customer));
        Http::assertSent(fn ($request): bool => $request['chat_id'] === $customer->telegram_chat_id
            && str_contains($request['text'], '1st Half Draw Odds: 2.25')
            && ! str_contains($request['text'], '1xbet'));
        $this->assertNotNull(app(AlertService::class)->source($this->source(str_replace('Over (0.5)', 'Over (1.5)', $text))));
    }

    #[TestWith(["Newcastle\nUnited 0:0 Arsenal"])]
    #[TestWith(["Newcastle 0:0 Arsenal\nUnited"])]
    public function test_source_accepts_home_team_names_wrapped_above_or_below_the_score(string $teams): void
    {
        $this->fixture(['team1' => 'Newcastle United', 'team2' => 'Arsenal', 'tournament' => 'Premier League']);
        $text = "Football. England. Premier League\n21 Nov 2026 (19:15)\n".$teams."\n0:0\nTotal. Over (0.5). 1st half 1.512\ntime elapsed: 13:58";

        $id = app(AlertService::class)->source($this->source($text));

        $this->assertNotNull($id);
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertSame('Newcastle United vs Arsenal', $payload['match']);
        $this->assertSame('0 - 0', $payload['score']);
    }

    #[TestWith(['Parma', 'Roma', 'Serie A', 'Parma 0:0 Roma', 'Italy. Serie A'])]
    #[TestWith(['Parma', 'AS Roma', 'Serie A', 'Parma 0:0 Roma', 'Italy. Serie A'])]
    #[TestWith(['Parma', 'Roma', 'Italian Serie A', 'Parma 0:0 Roma', 'Italy. Serie A'])]
    #[TestWith(['Newcastle', 'Arsenal', 'English Premier League', 'Newcastle United 0:0 Arsenal', 'England. Premier League'])]
    #[TestWith(['Schalke 04', 'Stuttgart', 'German Bundesliga', 'Schalke 04 0:0 VfB Stuttgart', 'Germany. Bundesliga'])]
    public function test_source_matches_provider_club_and_country_league_variants(string $home, string $away, string $league, string $teams, string $sourceLeague): void
    {
        $this->fixture(['team1' => $home, 'team2' => $away, 'tournament' => $league]);

        $id = app(AlertService::class)->source($this->source('Football. '.$sourceLeague."\n21 Nov 2026 (19:15)\n".$teams."\n0:0\nTotal. Over (0.5). 1st half 1.52\ntime elapsed: 19:16"));

        $this->assertNotNull($id);
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertSame($home.' vs '.$away, $payload['match']);
    }

    public function test_ambiguous_wrapped_names_do_not_select_a_different_match_at_the_same_kickoff(): void
    {
        $this->fixture(['team1' => 'Newcastle United', 'team2' => 'Arsenal', 'tournament' => 'Premier League']);
        $this->fixture(['unique_key' => 'other-wrapped-match', 'sportsApiPro_match_id' => 12346,
            'team1' => 'Newcastle', 'team2' => 'Arsenal United', 'tournament' => 'Premier League']);

        $id = app(AlertService::class)->source($this->source("Football. England. Premier League\nNewcastle 0:0 Arsenal\nUnited\n0:0\nTotal. Over (0.5). 1st half"));

        $this->assertNull($id);
        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_source_team_two_total_keeps_its_identity_separate_from_the_match_total(): void
    {
        $this->fixture(['team1' => 'Parma', 'team2' => 'AS Roma', 'tournament' => 'Serie A']);
        $base = "Football. Italy. Serie A\n21 Nov 2026 (18:45)\nParma VS Roma\n";

        $teamTotal = app(AlertService::class)->source([...$this->source($base."Total 2. (0.5) Over. 1st half\n1.49\ntime elapsed: 10:12"), 'message_id' => 101]);
        $matchTotal = app(AlertService::class)->source([...$this->source($base."Total. Over (0.5). 1st half\n1.512\ntime elapsed: 21:01"), 'message_id' => 102]);

        $this->assertNotNull($teamTotal);
        $this->assertNotNull($matchTotal);
        $this->assertNotSame($teamTotal, $matchTotal);
        $payload = json_decode(DB::table('alerts')->find($teamTotal)->payload, true);
        $this->assertSame('team_total_goals', $payload['context']['signal']['family']);
        $this->assertSame(2, $payload['context']['signal']['team']);
        $this->assertFalse($payload['context']['signal']['is_ltd']);
        $this->assertNull($payload['context']['betfair_back']);
        $this->assertSame($teamTotal, app(AlertService::class)->source([...$this->source($base.'Total 2. (0.5) Over. 1st half'), 'message_id' => 103]));
        $this->assertDatabaseCount('alerts', 2);
    }

    public function test_source_rejects_ads_and_unidentified_markets_and_accepts_missing_or_stale_odds(): void
    {
        $match = $this->fixture();
        foreach (['Asian Handicap', '1st Half Over 0.5 bonus deposit', '1st Half Over 0.5 register', 'Total 1.5 Over. 1st half', 'Total 2.5 Under. 1st half'] as $market) {
            $this->assertNull(app(AlertService::class)->source($this->source("Match: Torino vs Monza 1912\nLeague: Coppa Italia\n".$market)));
        }
        $this->assertNull(app(AlertService::class)->source($this->source('1st Half Over 0.5')));
        $this->assertNull(app(AlertService::class)->source([...$this->source(), 'source' => 'untrusted']));
        $this->assertNull(app(AlertService::class)->source([...$this->source(), 'sent_at' => time() - 3600]));
        FootballMarket::query()->update(['updated_at' => now()->subMinutes(4)]);
        $alertId = app(AlertService::class)->source($this->source());
        $this->assertNotNull($alertId);
        $payload = json_decode(DB::table('alerts')->find($alertId)->payload, true);
        $this->assertNull($payload['context']['draw_lay']);
        $this->assertStringContainsString('1st Half Draw Odds: —', app(AlertService::class)->text($payload));
        FootballMarket::query()->delete();
        $this->assertSame($alertId, app(AlertService::class)->source($this->source()));
        $this->assertDatabaseCount('alerts', 1);
    }

    #[TestWith(["🚀 GOOOOOAAAAAAALLLLL ⚽\nMatch: Torino vs Monza 1912\nLeague: Coppa Italia\nScore: 0 - 1\n1st Half - 33:38"])]
    #[TestWith(["🚀🥳 GOOOOOAAAAAAALLLLL!\nMatch: Torino vs Monza 1912\nLeague: Coppa Italia\nScore: 0 - 1\n1st Half - 33:38"])]
    #[TestWith(["Match: Torino vs Monza 1912\nLeague: Coppa Italia\nScore: 0 - 1\n1st Half - 33:38\n✅ Won"])]
    #[TestWith(["Match: Torino vs Monza 1912\nLeague: Coppa Italia\nScore: 0 - 1\nTime: 45+2'\nStatus: SIGNAL WON ✅️"])]
    #[TestWith(["Match: Torino vs Monza 1912\nLeague: Coppa Italia\nScore: 0 - 1\nTime: 33'\nPaid out ✅"])]
    #[TestWith(["🚨 GOAL ALERT! 🚨\nMatch: Torino vs Monza 1912\nLeague: Coppa Italia\nScore: 0 - 1\nTime: 33'"])]
    public function test_source_goal_formats_deliver_only_to_ltd_recipients(string $text): void
    {
        $this->fixture();
        $customer = $this->customer();
        $excluded = $this->customer(['tg_ltd' => false, 'tg_ht00' => true, 'popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 80]])]);
        $parent = app(AlertService::class)->source($this->source());
        app(AlertService::class)->deliver($this->delivery($parent, $customer));
        app(AlertService::class)->deliver($this->delivery($parent, $customer, 'platform'));
        $this->travel(2)->seconds();
        $goal = app(AlertService::class)->source($this->source($text));
        $this->assertNotNull($goal);
        app(AlertService::class)->deliver($this->delivery($goal, $customer));
        app(AlertService::class)->deliver($this->delivery($goal, $customer, 'platform'));
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $goal, 'customer_id' => $customer->id, 'status' => 'sent']);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $goal, 'customer_id' => $excluded->id]);
        $this->assertSame($goal, app(AlertService::class)->source($this->source($text)));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'GOAL DETECTED!') && ! str_contains($request['text'], '🥳'));
    }

    #[TestWith(["Time: 48'"])]
    #[TestWith(["2nd Half - 33:38\nTime: 33'"])]
    #[TestWith(["Full Time\nTime: 33'"])]
    public function test_source_rejects_goal_results_outside_first_half(string $period): void
    {
        $this->fixture();
        app(AlertService::class)->source($this->source());
        $this->assertNull(app(AlertService::class)->source($this->source("GOAL!\nMatch: Torino vs Monza 1912\nLeague: Coppa Italia\nScore: 0 - 1\n".$period)));
        $this->assertDatabaseCount('alerts', 1);
    }

    #[TestWith(['ltd', '🟢'])]
    #[TestWith(['ht00', '🔵'])]
    #[TestWith(['00_70', '🔴'])]
    #[TestWith(['fav_losing', '🟧'])]
    #[TestWith(['prematch_drops', '🟡'])]
    public function test_telegram_topic_headers_have_the_requested_color(string $type, string $indicator): void
    {
        $payload = ['alert_type' => $type, 'match' => 'Home vs Away', 'league' => 'League', 'minute' => 30, 'score' => '0 - 0',
            'context' => ['movement' => ['from' => 2.2, 'to' => 2, 'market' => 'moneyline', 'selection' => 'Home']]];
        $this->assertStringStartsWith($indicator.' BF MARKETS |', app(AlertService::class)->text($payload));
    }

    #[TestWith(['ht00', "🔵 BF MARKETS | ALERT DETECTED 🎯\n🔵 Filter: HT 0-0 (Half Time 0-0)"])]
    #[TestWith(['00_70', "🔴 BF MARKETS | ALERT DETECTED 🎯\n🔴 Filter: 0-0 @ 70' (Late Goal Radar)"])]
    #[TestWith(['fav_losing', "🟧 BF MARKETS | ALERT DETECTED 🎯\n🟧 Filter: Fav Losing (Favorite Losing)"])]
    #[TestWith(['prematch_drops', "🟡 BF MARKETS | PREMATCH DROP ALERT 📉\n🟡 Filter: Betfair > Pinnacle Drop"])]
    public function test_telegram_header_and_filter_lines_match_the_topic_colors(string $type, string $header): void
    {
        $payload = ['alert_type' => $type, 'match' => 'Home vs Away', 'league' => 'League', 'minute' => 30, 'score' => '0 - 0',
            'context' => ['movement' => ['from' => 2.2, 'to' => 2, 'market' => 'moneyline', 'selection' => 'Home']]];

        $text = app(AlertService::class)->text($payload);

        $this->assertStringStartsWith($header."\n⚽ Match: Home vs Away", $text);
    }

    public function test_source_signal_is_not_blocked_by_the_fixtures_page_backup_flag(): void
    {
        $this->fixture(['internal_backup' => ['since' => time() - 60]]);
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 81]])]);

        $id = app(AlertService::class)->source($this->source());

        $this->assertNotNull($id);
        app(AlertService::class)->deliver($this->delivery($id, $customer));
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $customer->id, 'channel' => 'telegram', 'status' => 'sent']);
        Http::assertSentCount(1);
    }

    public function test_goal_reaches_ready_recipients_without_waiting_for_another_users_initial_delivery(): void
    {
        $this->freezeTime();
        $this->fixture();
        $ready = $this->customer(['popup_enabled' => false]);
        $waiting = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 81]])]);
        $service = app(AlertService::class);
        $parent = $service->source($this->source());
        $readyParent = $this->delivery($parent, $ready);
        $waitingParent = $this->delivery($parent, $waiting);
        $service->deliver($readyParent);
        $this->travel(2)->seconds();
        $goal = $service->source([...$this->source("GOAL!\nMatch: Torino vs Monza 1912\nLeague: Coppa Italia\nScore: 0 - 1\nTime: 33'"), 'message_id' => 11]);

        $this->assertSame(5, $service->fanout($goal));
        $readyGoal = DB::table('alert_deliveries')->where(['alert_id' => $goal, 'customer_id' => $ready->id, 'channel' => 'telegram'])->value('id');
        $this->assertNotNull($readyGoal);
        $service->deliver($readyGoal);
        $this->assertDatabaseHas('alert_deliveries', ['id' => $readyGoal, 'status' => 'sent']);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $goal, 'customer_id' => $waiting->id]);
        $this->assertNull(DB::table('alerts')->find($goal)->fanned_out_at);
        $service->deliver($waitingParent);
        $this->travel(2)->seconds();
        $this->assertSame(0, $service->fanout($goal));
        $waitingGoal = DB::table('alert_deliveries')->where(['alert_id' => $goal, 'customer_id' => $waiting->id, 'channel' => 'telegram'])->value('id');
        $this->assertNotNull($waitingGoal);
        $service->deliver($waitingGoal);
        $service->deliver($readyGoal);
        $this->assertDatabaseHas('alert_deliveries', ['id' => $waitingGoal, 'status' => 'sent']);
        Http::assertSentCount(4);
    }

    public function test_goal_requires_valid_parent_and_only_reaches_parent_recipients(): void
    {
        $this->fixture();
        $goal = $this->source("GOOOOAAAALLLLL!\nMatch: Torino vs Monza 1912\nLeague: Coppa Italia\nScore: 0 - 1\nTime: 33'");
        $this->assertNull(app(AlertService::class)->source($goal));
        $recipient = $this->customer();
        $parent = app(AlertService::class)->source($this->source());
        $parentDelivery = $this->delivery($parent, $recipient);
        DB::table('alert_deliveries')->where('alert_id', $parent)->update(['status' => 'sent']);
        $newCustomer = $this->customer();
        $id = app(AlertService::class)->source($goal);
        $this->assertNotNull($id);
        app(AlertService::class)->fanout($id);
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $recipient->id, 'channel' => 'telegram']);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $id, 'customer_id' => $newCustomer->id]);
        $this->assertSame($id, app(AlertService::class)->source([...$goal, 'message_id' => 99]));
        $this->assertSame($parent, DB::table('alerts')->find($id)->parent_alert_id);
    }

    public function test_telegram_and_platform_targeting_are_independent(): void
    {
        $match = $this->fixture();
        $telegram = $this->customer(['popup_enabled' => false]);
        $platform = $this->customer(['telegram_chat_id' => null, 'telegram_available' => false, 'tg_ltd' => false]);
        $allUnchecked = $this->customer(['popup_enabled' => false, 'tg_ltd' => false]);
        $otherFilter = $this->customer(['popup_enabled' => false, 'tg_ltd' => false, 'tg_ht00' => true]);
        $expired = $this->customer(['trial_ends_at' => now()->subDay()]);
        $id = app(AlertService::class)->source($this->source());
        app(AlertService::class)->fanout($id);
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $telegram->id, 'channel' => 'telegram']);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $id, 'customer_id' => $telegram->id, 'channel' => 'platform']);
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $platform->id, 'channel' => 'platform']);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $id, 'customer_id' => $platform->id, 'channel' => 'telegram']);
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $allUnchecked->id, 'channel' => 'telegram']);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $id, 'customer_id' => $otherFilter->id]);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $id, 'customer_id' => $expired->id]);
        Bus::assertDispatched(ProcessAlert::class, static fn (ProcessAlert $job): bool => $job->task === 'deliver' && $job->queue === 'alerts-platform');
        Bus::assertDispatched(ProcessAlert::class, static fn (ProcessAlert $job): bool => $job->task === 'deliver' && $job->queue === 'alerts-ltd');
    }

    #[TestWith(['soccer'])]
    #[TestWith(['basketball'])]
    #[TestWith(['tennis'])]
    #[TestWith(['ice-hockey'])]
    #[TestWith(['american-football'])]
    #[TestWith(['baseball'])]
    #[TestWith(['cricket'])]
    #[TestWith(['esports'])]
    #[TestWith(['snooker'])]
    public function test_unmatched_pinnacle_drops_do_not_reach_telegram_even_with_legacy_preferences(string $sport): void
    {
        $customer = $this->customer(['tg_prematch_drops' => true, 'popup_enabled' => false]);
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 321]])]);
        $drops = (new ReflectionMethod(ListenPinnodds::class, 'normalizeDrops'))->invoke(app(ListenPinnodds::class), [
            'id' => 100, 'home' => 'Home Team', 'away' => 'Away Team', 'league' => 'Main League', 'sport' => $sport,
            'sect' => 'Moneyline', 'outcome' => 'Home', 'period' => 0, 'starts' => time() + 600,
            'from_price' => 3.20, 'to_price' => 2.75, 'alerted' => time(),
        ], 'prematch');
        $job = new ProcessAlert('pinnacle', ['alerts' => array_values($drops)]);

        $job->handle(app(AlertService::class));

        $this->assertDatabaseCount('football_matches', 0);
        if (! in_array(AlertService::pinnacleSport(['sport' => $sport]), AlertService::PINNACLE_SPORTS, true)) {
            $this->assertDatabaseCount('alerts', 0);
            $this->assertDatabaseCount('pinnacle_drop_archives', 0);
            Http::assertNothingSent();

            return;
        }
        $alert = DB::table('alerts')->sole();
        $this->assertSame('pinnacle:'.$sport.':100', $alert->match_id);
        $payload = json_decode($alert->payload, true);
        $this->assertSame($sport, $payload['context']['sport']);
        $this->assertSame('Home Team vs Away Team', $payload['match']);
        $this->assertSame(100, $payload['context']['pinnacle_event_id']);
        $this->assertSame(0, $this->delivery($alert->id, $customer));
        $job->handle(app(AlertService::class));

        $this->assertDatabaseCount('alerts', 1);
        Http::assertNothingSent();
    }

    #[TestWith(['2026-11-21T14:00:00+00:00', 'Asia/Tbilisi', '21 Nov 18:00'])]
    #[TestWith(['2026-11-21T14:00:00+00:00', 'Asia/Kolkata', '21 Nov 19:30'])]
    #[TestWith(['2026-07-15T12:00:00+00:00', 'America/New_York', '15 Jul 08:00'])]
    #[TestWith(['2026-11-21T14:00:00+00:00', 'America/New_York', '21 Nov 09:00'])]
    #[TestWith(['2026-11-21T23:30:00+00:00', 'Asia/Tbilisi', '22 Nov 03:30'])]
    #[TestWith(['2026-11-21T14:00:00+00:00', 'invalid/timezone', '21 Nov 14:00'])]
    public function test_telegram_kickoff_uses_the_recipient_timezone_without_a_timezone_label(string $kickoff, string $timezone, string $expected): void
    {
        $payload = ['alert_type' => 'prematch_drops', 'match' => 'Como vs Cagliari', 'league' => 'Italy - Serie A', 'kickoff' => $kickoff,
            'context' => ['movement' => ['market' => 'Moneyline', 'selection' => 'Away', 'from' => 10.75, 'to' => 10.0]]];

        $text = app(AlertService::class)->text($payload, 12.0, $timezone);

        $this->assertStringContainsString('Kickoff: '.$expected."\n", $text);
    }

    public function test_one_queued_alert_is_formatted_separately_for_each_recipient_timezone(): void
    {
        config(['services.sportsmeld.key' => null, 'services.football_fixture_index' => false]);
        Redis::shouldReceive('get')->with('Football:matches')->andReturn(null);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]])]);
        $georgia = $this->customer(['timezone' => 'Asia/Tbilisi', 'tg_prematch_drops' => true, 'popup_enabled' => false]);
        $india = $this->customer(['timezone' => 'Asia/Kolkata', 'tg_prematch_drops' => true, 'popup_enabled' => false]);
        foreach ([$georgia, $india] as $customer) {
            $this->actingAs($customer, 'customers')->patchJson('/notifications', ['pinnacle_filters' => ['higher' => true, 'leagues' => null]])->assertOk();
        }
        auth('customers')->logout();
        $day = Carbon::now('UTC')->addDay()->format('Y-m-d');
        $starts = Carbon::parse($day.'T14:00:00+00:00')->timestamp;
        $date = Carbon::parse($day)->format('d M');
        $fixture = $this->fixture(['result_status' => 'Prematch', 'timestamp' => $starts]);
        $fixture->forceFill(['betfair_match_id' => '900', 'data_sync_complete' => true])->save();
        FootballMarket::query()->create(['selection_key' => hash('sha256', 'timezone-pinnacle-quote'), 'football_match_unique_key' => $fixture->unique_key,
            'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'runner' => 'Torino',
            'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => 'Torino', 'currentBackOdds' => 2.1, 'market_status' => 'OPEN']]);
        $id = app(AlertService::class)->process('prematch_drops', [], ['pinnacle' => ['id' => 100, 'sport' => 'soccer', 'home' => 'Torino', 'away' => 'Monza 1912',
            'league' => 'Italy - Coppa Italia', 'sect' => 'Moneyline', 'outcome' => 'Home', 'starts' => $starts,
            'from_price' => 2.5, 'to_price' => 1.8, 'alerted' => time(), 'alert_key' => 'recipient-timezones']]);

        foreach ([$georgia, $india] as $customer) {
            app(AlertService::class)->deliver($this->delivery($id, $customer));
        }

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request['chat_id'] === $georgia->telegram_chat_id && str_contains($request['text'], 'Kickoff: '.$date." 18:00\n"));
        Http::assertSent(fn ($request): bool => $request['chat_id'] === $india->telegram_chat_id && str_contains($request['text'], 'Kickoff: '.$date." 19:30\n"));
        $this->assertSame($starts, Carbon::parse(json_decode(DB::table('alerts')->find($id)->payload, true)['kickoff'])->timestamp);
    }

    public function test_pinnacle_telegram_filters_use_selected_league_and_current_back_odds(): void
    {
        config(['services.sportsmeld.key' => null, 'services.football_fixture_index' => false]);
        Redis::shouldReceive('get')->with('Football:matches')->andReturn(null);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]])]);
        $customer = $this->customer(['tg_prematch_drops' => true, 'popup_enabled' => false]);
        $starts = time() + 3600;
        $fixture = $this->fixture(['result_status' => 'Prematch', 'timestamp' => $starts]);
        $fixture->forceFill(['betfair_match_id' => '900', 'data_sync_complete' => true])->save();
        $market = FootballMarket::query()->create([
            'selection_key' => hash('sha256', 'current-pinnacle-quote'), 'football_match_unique_key' => $fixture->unique_key,
            'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'runner' => 'Torino',
            'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => 'Torino', 'currentBackOdds' => 1.8, 'market_status' => 'OPEN'],
        ]);
        $drop = ['id' => 100, 'sport' => 'soccer', 'home' => 'Torino', 'away' => 'Monza 1912', 'league' => 'Italy - Coppa Italia',
            'sect' => 'Moneyline', 'outcome' => 'Home', 'starts' => $starts, 'from_price' => 2.5, 'to_price' => 1.8, 'alerted' => time()];
        $league = AlertService::pinnacleLeague($drop)[2];
        $this->actingAs($customer, 'customers')->patchJson('/notifications', [
            'pinnacle_filters' => ['higher' => true, 'leagues' => [$league]],
        ])->assertOk()->assertJsonPath('pinnacle_filters.leagues.0', $league);

        $alerts = app(AlertService::class);
        foreach (['equal' => [], 'different-league' => ['league' => 'England - Coppa Italia'], 'suspended' => [], 'higher' => []] as $key => $fields) {
            $market->forceFill(['payload' => array_replace($market->payload, [
                'currentBackOdds' => $key === 'equal' ? 1.8 : 2.1,
                'market_status' => $key === 'suspended' ? 'SUSPENDED' : 'OPEN',
            ])])->save();
            $id = $alerts->process('prematch_drops', [], ['pinnacle' => array_replace($drop, ['alert_key' => $key], $fields)]);
            $delivery = $this->delivery($id, $customer);
            $alerts->deliver($delivery);
            $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => $key === 'higher' ? 'sent' : 'skipped']);
        }

        Http::assertSentCount(1);
    }

    public function test_pinnacle_drops_respect_saved_preferences_access_and_disconnect_at_delivery(): void
    {
        $allowed = $this->customer(['tg_prematch_drops' => true, 'popup_enabled' => false]);
        $lifetime = $this->customer(['tg_prematch_drops' => true, 'popup_enabled' => false, 'trial_ends_at' => now()->subDay(), 'admin_free_days' => -1]);
        $excluded = [];
        foreach ([
            ['tg_prematch_drops' => false],
            ['tg_prematch_drops' => false, 'tg_market_shock' => true],
            ['trial_ends_at' => now()->subDay()],
            ['is_verified' => false],
            ['is_blocked' => true],
            ['is_suspended' => true],
            ['telegram_available' => false, 'telegram_chat_id' => null],
        ] as $attributes) {
            $excluded[] = $this->customer(['tg_prematch_drops' => true, 'popup_enabled' => false, ...$attributes]);
        }
        foreach ([$allowed, $lifetime, ...array_slice($excluded, 2)] as $customer) {
            $this->actingAs($customer, 'customers')->patchJson('/notifications', [
                'pinnacle_filters' => ['higher' => true, 'leagues' => null],
            ])->assertOk();
        }
        $id = app(AlertService::class)->process('prematch_drops', [], ['pinnacle' => [
            'id' => 100, 'home' => 'Sokol Prazsky', 'away' => 'Basketbal Polabi', 'league' => '1. Liga', 'sport' => 'basketball',
            'sect' => 'Moneyline', 'outcome' => 'Home', 'from_price' => 1.76, 'to_price' => 1.67,
            'alert_key' => 'basketball-main', 'alerted' => time(),
        ]]);

        app(AlertService::class)->fanout($id);

        foreach ($excluded as $customer) {
            $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $id, 'customer_id' => $customer->id]);
        }
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $lifetime->id, 'channel' => 'telegram']);
        $delivery = DB::table('alert_deliveries')->where('alert_id', $id)->where('customer_id', $allowed->id)->value('id');
        $this->actingAs($allowed, 'customers')->patchJson('/notifications', [
            'pinnacle_filters' => ['higher' => false, 'leagues' => null],
        ])->assertOk();
        app(AlertService::class)->deliver($delivery);
        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'skipped']);
        Http::assertNothingSent();
    }

    public function test_pinnacle_rejects_secondary_live_stale_and_invalid_drops_and_deduplicates_football_scan(): void
    {
        $drop = ['id' => 100, 'home' => 'Torino', 'away' => 'Monza 1912', 'league' => 'Coppa Italia', 'sport' => 'soccer',
            'sect' => 'Moneyline', 'outcome' => 'Home', 'from_price' => 4.0, 'to_price' => 3.5,
            'alert_key' => 'soccer-main', 'alerted' => time(), 'starts' => time() + 600];
        foreach ([
            ['sect' => 'Asian Handicap', 'point' => -1.5], ['sect' => 'Total', 'point' => 2.75], ['period' => 1],
            ['is_live' => true], ['feed' => 'live'], ['from_price' => 2.0, 'to_price' => 1.95],
            ['from_price' => 0], ['to_price' => 1], ['to_price' => 4.1], ['from_price' => INF], ['to_price' => NAN],
            ['alerted' => time() - 10801], ['alerted' => time() + 31], ['home' => ''], ['away' => ''], ['id' => null], ['alert_key' => ''],
        ] as $changes) {
            $this->assertNull(app(AlertService::class)->process('prematch_drops', [], ['pinnacle' => [...$drop, ...$changes]]));
        }
        $this->assertNull(app(AlertService::class)->process('market_shock', [], ['pinnacle' => $drop]));
        $this->assertDatabaseCount('alerts', 0);

        $id = app(AlertService::class)->process('prematch_drops', [], ['pinnacle' => $drop]);
        $match = $this->fixture(['result_status' => 'Prematch', 'timestamp' => time() + 600, 'pinnacle_drop_triggered' => true,
            'pinnacle_markets' => [['sect' => 'Moneyline', 'outcome' => 'Home', 'period' => 0, 'drop_triggered' => true,
                'from_price' => 4.0, 'current_odds' => 3.5, 'initialBackOddsTimestamp' => time(), 'alert_key' => 'soccer-main']]]);
        $this->assertSame($id, app(AlertService::class)->process('prematch_drops', $match->payload));
        $this->assertDatabaseCount('alerts', 1);

        $match->forceFill(['payload' => [...$match->payload, 'pinnacle_markets' => [[...$match->payload['pinnacle_markets'][0], 'alert_key' => 'soccer-second']]]])->save();
        $second = app(AlertService::class)->process('prematch_drops', $match->payload);
        $this->assertSame($second, app(AlertService::class)->process('prematch_drops', [], ['pinnacle' => [...$drop, 'alert_key' => 'soccer-second']]));
        $this->assertDatabaseCount('alerts', 2);
        $this->assertNotSame($id, app(AlertService::class)->process('prematch_drops', [], ['pinnacle' => [...$drop, 'sport' => 'basketball']]));
        $this->assertDatabaseCount('alerts', 3);

        $shockMatch = [...$match->payload, 'pinnacle_market_shock_detected_at' => time(),
            'pinnacle_markets' => [[...$match->payload['pinnacle_markets'][0], 'shock_detected_at' => time()]]];
        $shock = app(AlertService::class)->process('market_shock', $shockMatch);
        $this->assertNotNull($shock);
        $shockMatch['pinnacle_markets'][0]['alert_key'] = 'later-price-in-same-shock';
        $this->assertSame($shock, app(AlertService::class)->process('market_shock', $shockMatch));
        Http::assertNothingSent();
    }

    public function test_pinnacle_listener_only_admits_allowlisted_competitions_and_their_child_markets(): void
    {
        $command = app(ListenPinnodds::class);
        (new ReflectionMethod(ListenPinnodds::class, 'loadLeagueMappings'))->invoke($command);
        $filter = new ReflectionMethod(ListenPinnodds::class, 'filterCompetitions');
        $drops = $filter->invoke($command, [
            'child' => ['id' => 7, 'sport' => 'soccer', 'parent_id' => 1],
            'serie-a' => ['id' => 1, 'sport' => 'soccer', 'league' => 'Italy - Serie A'],
            'wrong-country' => ['id' => 2, 'sport' => 'soccer', 'league' => 'Congo - Serie A'],
            'contradictory-country' => ['id' => 3, 'sport' => 'soccer', 'country' => 'Congo', 'league' => 'Italy - Serie A'],
            'championship' => ['id' => 4, 'sport' => 'soccer', 'league' => 'England - Championship'],
            'unlisted' => ['id' => 5, 'sport' => 'soccer', 'league' => 'England - National League'],
            'tennis' => ['id' => 6, 'sport' => 'tennis', 'league' => 'ATP Paris - Doubles'],
            'other-sport' => ['id' => 8, 'sport' => 'basketball', 'league' => 'Italy - Serie A'],
            'unverified-itf' => ['id' => 9, 'sport' => 'tennis', 'league' => 'ITF Men Orlando - Final'],
            'verified-id' => ['id' => 10, 'sport' => 'soccer', 'league_id' => 2436],
            'contradictory-id' => ['id' => 11, 'sport' => 'soccer', 'league_id' => 2436, 'league' => 'England - Premier League'],
            'itf-with-level' => ['id' => 12, 'sport' => 'tennis', 'league' => 'ITF Women W35 Test City - Final'],
        ]);

        $this->assertSame(['child', 'serie-a', 'championship', 'tennis', 'verified-id', 'itf-with-level'], array_keys($drops));
        $this->assertSame(['Italy', 'Serie A'], $drops['child']['pinnacle_competition']);
        $this->assertSame(['England', 'EFL Championship'], $drops['championship']['pinnacle_competition']);
        $this->assertSame(['ATP Masters 1000', 'Paris Masters'], $drops['tennis']['pinnacle_competition']);
        $this->assertSame(['Italy', 'Serie A'], $drops['verified-id']['pinnacle_competition']);
        $this->assertSame(['Women’s ITF', 'W35'], $drops['itf-with-level']['pinnacle_competition']);
        $this->assertSame(77, DB::table('pinnacle_league_mappings')->where('sport', 'soccer')->distinct()->get(['country', 'league'])->count());
        $this->assertDatabaseHas('pinnacle_league_mappings', [
            'provider_league_id' => 2436, 'provider_league_name' => 'Italy - Serie A', 'country' => 'Italy', 'league' => 'Serie A',
        ]);
    }

    public function test_football_allowlist_requires_country_and_accepts_all_77_competitions(): void
    {
        $catalog = config('services.pinnodds.football_priority');
        $this->assertCount(77, $catalog);
        foreach ($catalog as [$country, $league]) {
            $this->assertTrue(FootballSyncController::allowsFootballCompetition([
                'tournament_country' => $country, 'tournament' => $league,
            ]), $country.' - '.$league);
        }
        foreach ([['Qatar', 'Stars League'], ['Armenia', 'First League'], ['Congo', 'Serie A'], ['', 'Premier League']] as [$country, $league]) {
            $this->assertFalse(FootballSyncController::allowsFootballCompetition([
                'tournament_country' => $country, 'tournament' => $league,
            ]));
        }
        $this->assertTrue(FootballSyncController::allowsFootballCompetition([
            'tournament_country' => 'Chile', 'tournament' => 'Primera Division',
        ]));
        $this->assertTrue(FootballSyncController::allowsFootballCompetition([
            'tournament_country' => 'Paraguay', 'tournament' => 'Primera División',
        ]));
        $this->assertFalse(FootballSyncController::allowsFootballCompetition([
            'tournament_country' => 'Armenia', 'tournament' => 'Primera División',
        ]));
        $this->assertSame(['Paraguay', 'Paraguayan Primera División'],
            FootballSyncController::pinnacleCompetition('Paraguay', 'Primera División'));

    }

    public function test_nations_league_stage_names_keep_the_verified_competition_mapping(): void
    {
        $mappingId = DB::table('pinnacle_league_mappings')->where('sport', 'soccer')
            ->where('country', 'UEFA')->where('league', 'UEFA Nations League')->value('id');
        DB::table('pinnacle_league_mappings')->where('id', $mappingId)
            ->update(['provider_league_id' => 99001, 'provider_league_name' => 'UEFA - Nations League A']);
        $command = app(ListenPinnodds::class);
        (new ReflectionMethod(ListenPinnodds::class, 'loadLeagueMappings'))->invoke($command);
        $drops = (new ReflectionMethod(ListenPinnodds::class, 'filterCompetitions'))->invoke($command, [
            'stage' => ['id' => 1, 'sport' => 'soccer', 'country' => 'Europe', 'league_id' => 99001, 'league' => 'Europe - UEFA Nations League - League A'],
            'alias' => ['id' => 2, 'sport' => 'soccer', 'country' => 'Europe', 'league_id' => 99001, 'league' => 'UEFA - Nations League A'],
            'group' => ['id' => 3, 'sport' => 'soccer', 'league' => 'Europe - Nations League - League A - Group 1'],
            'bare-stage' => ['id' => 5, 'sport' => 'soccer', 'country' => 'Europe', 'league_id' => 99001, 'league' => 'UEFA Nations League - League A'],
            'wrong' => ['id' => 4, 'sport' => 'soccer', 'league_id' => 99001, 'league' => 'England - Premier League'],
        ]);

        $this->assertSame(['stage', 'alias', 'group', 'bare-stage'], array_keys($drops));
        foreach ($drops as $drop) {
            $this->assertSame(['UEFA', 'UEFA Nations League'], $drop['pinnacle_competition']);
        }
    }

    public function test_pinnacle_monitor_preserves_priority_across_repeated_countries(): void
    {
        $options = collect(app(AlertService::class)->pinnacleMonitorLeagueOptions())->flatMap(
            fn (array $group): array => array_values($group['leagues']),
        )->all();

        $this->assertSame('FIFA World Cup', $options[0]);
        $this->assertSame('Premier League', $options[14]);
        $this->assertSame('Israeli Premier League', $options[36]);
        $this->assertSame('EFL Championship', $options[37]);
        $this->assertSame('Chilean Primera División', $options[74]);
        $this->assertSame('Paraguayan Primera División', $options[75]);
        $this->assertSame('Uruguayan Primera División', $options[76]);
        $this->assertNotContains('Qatar Stars League', $options);
        $this->assertSame('Australian Open', $options[77]);
        $this->assertLessThan(array_search('Rotterdam Open', $options, true), array_search('ATP Finals', $options, true));
    }

    public function test_pinnacle_listener_queues_the_existing_alert_job_after_persisting_drops(): void
    {
        config(['cache.stores.redis.driver' => 'array']);
        $command = app(ListenPinnodds::class);
        $drop = [
            'id' => 100, 'home' => 'Home', 'away' => 'Away', 'league' => 'Italy - Serie A', 'sport' => 'soccer',
            'sect' => 'Moneyline', 'outcome' => 'Away', 'from_price' => 2.0, 'to_price' => 1.8, 'alerted' => time(),
        ];
        $normalized = (new ReflectionMethod(ListenPinnodds::class, 'normalizeDrops'))->invoke($command, [
            $drop, array_replace($drop, ['id' => 101, 'league' => 'Congo - Serie A']),
        ], 'prematch');
        $pending = (new ReflectionMethod(ListenPinnodds::class, 'filterCompetitions'))->invoke($command, $normalized);
        $stored = null;
        Redis::shouldReceive('get')->with('Pinnacle:drops')->andReturnNull();
        Redis::shouldReceive('set')->times(3)->with('Pinnacle:drops', \Mockery::capture($stored))->andReturnTrue();
        Redis::shouldReceive('expire')->times(3)->with('Pinnacle:drops', 1800)->andReturnTrue();

        (new ReflectionMethod(ListenPinnodds::class, 'storeDrops'))->invoke($command, $pending, []);

        $this->assertSame([100], array_column(json_decode($stored, true)['alerts'], 'id'));
        Bus::assertDispatched(ProcessAlert::class, fn (ProcessAlert $job): bool => $job->task === 'pinnacle'
            && $job->queue === 'alerts' && isset($job->data['alerts'][0]['monitor_received_at']) && Arr::except($job->data['alerts'][0], 'monitor_received_at') === array_values($pending)[0]);
        Bus::fake([ProcessAlert::class]);
        config(['services.telegram.enabled' => false]);
        $pending = array_map(fn (array $drop): array => array_replace($drop, ['alert_key' => hash('sha256', 'next-drop'), 'to_price' => 1.7]), $pending);
        (new ReflectionMethod(ListenPinnodds::class, 'storeDrops'))->invoke($command, $pending, []);
        Bus::assertDispatched(ProcessAlert::class, fn (ProcessAlert $job): bool => $job->task === 'pinnacle');
        Bus::fake([ProcessAlert::class]);
        config(['services.telegram.enabled' => true]);
        (new ReflectionMethod(ListenPinnodds::class, 'storeDrops'))->invoke($command, [], []);
        Bus::assertNotDispatched(ProcessAlert::class);
        Http::assertNothingSent();
    }

    public function test_pinnacle_dashboard_expires_alerts_after_seven_minutes_without_pruning_internal_signals(): void
    {
        config(['cache.stores.redis.driver' => 'array', 'services.sportsmeld.key' => null]);
        $this->actingAs($this->customer(), 'customers');
        $command = app(ListenPinnodds::class);
        $now = time();
        $drops = [];
        foreach ([1 => 60, 2 => 420, 3 => 600] as $id => $age) {
            $drops[] = ['id' => $id, 'sport' => 'soccer', 'league' => 'Italy - Serie A', 'home' => 'Home', 'away' => 'Away', 'sect' => 'Moneyline',
                'from_price' => 2, 'to_price' => 1.8, 'alerted' => $now - $age];
        }
        $cached = json_encode(['alerts' => $drops, 'signals' => $drops]);
        Redis::shouldReceive('get')->with('Pinnacle:drops')->andReturnUsing(function () use (&$cached): string {
            return $cached;
        });
        Redis::shouldReceive('get')->with('Football:matches')->andReturnNull();
        Redis::shouldReceive('get')->with('Football:matches:fixtures')->andReturnNull();
        Redis::shouldReceive('getrange')->andReturn('');
        Redis::shouldReceive('set')->with('Pinnacle:drops', \Mockery::capture($stored))->times(3)->andReturnTrue();
        Redis::shouldReceive('expire')->with('Pinnacle:drops', 1800)->times(3)->andReturnTrue();

        (new ReflectionMethod(ListenPinnodds::class, 'storeDrops'))->invoke($command, [], []);

        $payload = json_decode($stored, true);
        $this->assertSame([1], array_column($payload['alerts'], 'id'));
        $this->assertSame([1, 2, 3], array_column($payload['signals'], 'id'));
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 1);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();

        $delayed = array_replace($drops[2], ['id' => 4, 'alerted' => $now - 14400]);
        $pending = (new ReflectionMethod(ListenPinnodds::class, 'normalizeDrops'))->invoke($command, [$delayed], 'prematch');
        $store = new ReflectionMethod(ListenPinnodds::class, 'storeDrops');
        $store->invoke($command, $pending, []);

        $payload = json_decode($stored, true);
        $this->assertSame([4, 1], array_column($payload['alerts'], 'id'));
        $this->assertSame([1, 2, 3, 4], array_column($payload['signals'], 'id'));
        $this->assertGreaterThanOrEqual($now, $payload['alerts'][0]['monitor_received_at']);
        $cached = $stored;
        $store->invoke($command, $pending, []);
        $this->assertSame($payload['alerts'][0]['monitor_received_at'], json_decode($stored, true)['alerts'][0]['monitor_received_at']);
    }

    public function test_pinnacle_check_reports_cached_sports_and_stale_listener_code_without_connecting(): void
    {
        Redis::shouldReceive('get')->with('Pinnacle:drops')->once()->andReturn(json_encode([
            'alerts' => [['sport' => 'soccer'], ['sport' => 'basketball']],
            'signals' => [['sport' => 'cricket']], 'feeds' => ['live' => ['status' => 'connected']],
            'listener' => ['code_sha256' => 'old-code'],
        ]));
        $this->assertSame(0, Artisan::call('pinnodds:listen', ['--check' => true]));
        $output = json_decode(Artisan::output(), true);
        $this->assertSame(['soccer' => 1, 'basketball' => 1], $output['sports']['alerts']);
        $this->assertSame(['cricket' => 1], $output['sports']['signals']);
        $this->assertFalse($output['code_matches_disk']);
        Http::assertNothingSent();
        Bus::assertNothingDispatched();
    }

    public function test_pinnacle_monitor_keeps_all_supported_sports_without_a_betfair_match(): void
    {
        config(['cache.stores.redis.driver' => 'array', 'services.sportsmeld.key' => null]);
        $this->actingAs($this->customer(), 'customers');
        $command = app(ListenPinnodds::class);
        $drops = [];
        foreach ([1, 2, 3, 4, 5, 6, 11, 13] as $sportId) {
            $drops[] = ['id' => 100 + $sportId, 'sport_id' => $sportId, 'home' => 'Home', 'away' => 'Away',
                'league' => 'Example League', 'sect' => 'Total', 'point' => 22.5, 'outcome' => 'Over', 'period' => 0,
                'from_price' => 2, 'to_price' => 1.9, 'alerted' => time(), 'starts' => time() + 3600];
        }
        $pending = (new ReflectionMethod(ListenPinnodds::class, 'normalizeDrops'))->invoke($command, $drops, 'prematch');
        $cached = null;
        Redis::shouldReceive('get')->andReturnNull()->byDefault();
        Redis::shouldReceive('get')->with('Pinnacle:drops')->andReturnUsing(function () use (&$cached): ?string {
            return $cached;
        });
        Redis::shouldReceive('set')->with('Pinnacle:drops', \Mockery::type('string'))->andReturnUsing(function (string $key, string $value) use (&$cached): bool {
            $cached = $value;

            return true;
        });
        Redis::shouldReceive('expire')->with('Pinnacle:drops', 1800)->andReturnTrue();
        (new ReflectionMethod(ListenPinnodds::class, 'storeDrops'))->invoke($command, $pending, []);
        $this->assertCount(7, $pending);
        foreach (AlertService::PINNACLE_SPORTS as $sport) {
            $response = $this->getJson(route('football.pinnacle-odds', ['sport' => $sport]))->assertOk()->assertJsonCount(1, 'data');
            $this->assertSame($sport, $response->json('data.0.sport'));
            $this->assertNull($response->json('data.0.bf_back'));
        }
        Http::assertNothingSent();
    }

    #[TestWith(['soccer', 2.5, 1])]
    #[TestWith(['soccer', 6.5, 4])]
    #[TestWith(['tennis', 22.5, 1])]
    #[TestWith(['basketball', 220.5, 1])]
    #[TestWith(['ice-hockey', 5.5, 1])]
    #[TestWith(['baseball', 8.5, 1])]
    #[TestWith(['cricket', 180.5, 1])]
    #[TestWith(['esports', 26.5, 1])]
    #[TestWith(['american-football', 47.5, 1])]
    public function test_pinnacle_archive_classifies_full_match_totals_for_each_sport(string $sport, float $line, int $group): void
    {
        config(['services.telegram.enabled' => false]);
        $key = hash('sha256', $sport.'|'.$line);
        $drop = ['id' => 100, 'alert_key' => $key, 'sport' => $sport, 'home' => 'Home', 'away' => 'Away',
            'sect' => 'Total', 'outcome' => 'Over', 'point' => $line, 'period' => 0,
            'from_price' => 2, 'to_price' => 1.9, 'alerted' => time(), 'starts' => time() + 3600];
        (new ProcessAlert('pinnacle', ['alerts' => [$drop]]))->handle(app(AlertService::class));
        if (in_array(AlertService::pinnacleSport(['sport' => $sport]), AlertService::PINNACLE_SPORTS, true)) {
            $this->assertDatabaseHas('pinnacle_drop_archives', ['alert_key' => $key, 'sport' => $sport, 'market_group' => $group]);
        } else {
            $this->assertDatabaseMissing('pinnacle_drop_archives', ['alert_key' => $key]);
        }
        $this->assertDatabaseCount('alerts', 0);
        Http::assertNothingSent();
    }

    public function test_pinnacle_archive_retains_all_markets_and_sports_once_without_enabling_notifications(): void
    {
        $this->freezeTime();
        config(['services.telegram.enabled' => false]);
        $base = ['id' => 100, 'sport' => 'esports', 'home' => 'Home', 'away' => 'Away', 'sect' => 'Moneyline',
            'from_price' => 2, 'to_price' => 1.9, 'alerted' => now()->timestamp, 'starts' => now()->addHour()->timestamp * 1000];
        $drops = [];
        foreach (['soccer', 'tennis', 'basketball', 'hockey', 'american-football', 'baseball', 'cricket', 'esports', 'snooker'] as $sport) {
            $drops[] = array_replace($base, ['alert_key' => hash('sha256', $sport), 'sport' => $sport]);
        }
        $drops[] = array_replace($base, ['alert_key' => hash('sha256', 'handicap'), 'sect' => 'Asian Handicap', 'point' => -4, 'is_live' => true]);
        $drops[] = array_replace($base, ['alert_key' => hash('sha256', 'total'), 'sect' => 'Total', 'point' => 2.5]);
        $drops[] = array_replace($base, ['alert_key' => hash('sha256', 'half'), 'sect' => 'Moneyline', 'period' => 1]);
        $drops[] = array_replace($base, ['alert_key' => hash('sha256', 'btts'), 'sport' => 'soccer', 'id' => 101,
            'parent_id' => 100, 'home' => 'Yes', 'away' => 'No', 'sect' => 'btts', 'outcome' => 'home']);
        $drops[] = array_replace($base, ['alert_key' => hash('sha256', 'small'), 'to_price' => 1.91]);
        $drops[] = array_replace($base, ['alert_key' => hash('sha256', 'old'), 'alerted' => now('Asia/Tbilisi')->subHours(6)->startOfDay()->addHours(6)->timestamp - 1]);
        $drops[] = array_replace($base, ['alert_key' => hash('sha256', 'invalid'), 'to_price' => 0]);
        $drops[] = array_replace($base, ['alert_key' => hash('sha256', 'future'), 'alerted' => now()->addMinute()->timestamp]);
        $parent = ['id' => 100, 'sport' => 'soccer', 'home' => 'Home Club', 'away' => 'Away Club', 'starts' => $base['starts']];
        $job = new ProcessAlert('pinnacle', ['alerts' => $drops, 'parents' => ['soccer' => [100 => $parent]]]);

        $job->handle(app(AlertService::class));
        $job->handle(app(AlertService::class));

        $this->assertDatabaseCount('pinnacle_drop_archives', 11);
        $this->assertDatabaseHas('pinnacle_drop_archives', ['sport' => 'ice-hockey', 'market_group' => 0, 'start_timestamp' => intdiv($base['starts'], 1000)]);
        $this->assertDatabaseMissing('pinnacle_drop_archives', ['alert_key' => hash('sha256', 'handicap')]);
        $this->assertDatabaseHas('pinnacle_drop_archives', ['alert_key' => hash('sha256', 'total'), 'market_group' => 1]);
        $this->assertDatabaseHas('pinnacle_drop_archives', ['alert_key' => hash('sha256', 'half'), 'market_group' => 4]);
        $stored = json_decode(DB::table('pinnacle_drop_archives')->where('alert_key', hash('sha256', 'btts'))->value('payload'), true);
        $this->assertSame($parent, $stored['archive_parent']);
        $this->assertSame('Yes', $stored['home']);
        $this->assertDatabaseCount('alerts', 0);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_pinnacle_listener_archives_binary_market_parent_metadata_from_existing_signals(): void
    {
        config(['services.telegram.enabled' => false, 'cache.stores.redis.driver' => 'array']);
        $command = app(ListenPinnodds::class);
        $pending = (new ReflectionMethod(ListenPinnodds::class, 'normalizeDrops'))->invoke($command, [
            ['id' => 100, 'home' => 'Home Club', 'away' => 'Away Club', 'league' => 'League', 'sport' => 'soccer',
                'sect' => 'Moneyline', 'from_price' => 2, 'to_price' => 1.96, 'alerted' => time(), 'starts' => time() + 3600],
            ['id' => 101, 'parent_id' => 100, 'home' => 'Yes', 'away' => 'No', 'sport' => 'soccer',
                'sect' => 'btts', 'outcome' => 'home', 'from_price' => 2, 'to_price' => 1.8, 'alerted' => time()],
        ], 'prematch');
        Redis::shouldReceive('get')->with('Pinnacle:drops')->once()->andReturnNull();
        Redis::shouldReceive('set')->with('Pinnacle:drops', \Mockery::type('string'))->once()->andReturnTrue();
        Redis::shouldReceive('expire')->with('Pinnacle:drops', 1800)->once()->andReturnTrue();

        (new ReflectionMethod(ListenPinnodds::class, 'storeDrops'))->invoke($command, $pending, []);

        Bus::assertDispatched(ProcessAlert::class, fn (ProcessAlert $job): bool => $job->data['parents']['soccer'][100]['home'] === 'Home Club');
        $job = Bus::dispatched(ProcessAlert::class)->first();
        $job->handle(app(AlertService::class));
        $this->assertDatabaseCount('pinnacle_drop_archives', 1);
        $stored = json_decode(DB::table('pinnacle_drop_archives')->value('payload'), true);
        $this->assertSame('Home Club', $stored['archive_parent']['home']);
        $this->assertSame('Yes', $stored['home']);
        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_pinnacle_archive_batches_large_messages_and_retries_without_losing_or_duplicating_drops(): void
    {
        $this->freezeTime();
        config(['services.telegram.enabled' => false]);
        $drops = [];
        for ($index = 0; $index < 501; $index++) {
            $drops[] = ['alert_key' => hash('sha256', (string) $index), 'id' => $index + 1, 'sport' => 'tennis',
                'home' => 'Home', 'away' => 'Away', 'sect' => 'Moneyline', 'from_price' => 2, 'to_price' => 1.8, 'alerted' => now()->timestamp];
        }
        $job = new ProcessAlert('pinnacle', ['alerts' => $drops]);
        $job->handle(app(AlertService::class));
        $job->handle(app(AlertService::class));

        $this->assertDatabaseCount('pinnacle_drop_archives', 501);
        $this->assertSame(501, DB::table('pinnacle_drop_archives')->distinct()->count('alert_key'));
        $this->assertDatabaseCount('alerts', 0);
        Http::assertNothingSent();
    }

    public function test_pinnacle_reads_a_current_betfair_fixture_before_it_has_been_persisted_to_the_database(): void
    {
        $this->freezeTime();
        config(['services.sportsmeld.key' => '']);
        $this->actingAs($this->customer(), 'customers');
        $key = 'liverpool|man_united';
        $entry = ['unique_key' => $key, 'team1' => 'Liverpool', 'team2' => 'Man Utd', 'tournament' => 'English Premier League',
            'timestamp' => now()->addHour()->timestamp, 'result_status' => 'Prematch', 'betfair_match_id' => '36225147', '_payload_bytes' => 2000];
        $index = ['ids' => [], 'matches' => [$key => $entry]];
        $current = $entry + ['markets' => ['main' => [['marketType' => 'MATCH_ODDS', 'runner' => 'The Draw', 'currentBackOdds' => 4.4]]]];
        $cache = \Mockery::mock(FootballMatchCache::class);
        $cache->shouldReceive('active')->andReturnTrue();
        $cache->shouldReceive('index')->once()->andReturn($index);
        $cache->shouldReceive('read')->once()->with(null, [$key], $index)->andReturn([$key => $current]);
        $this->app->instance(FootballMatchCache::class, $cache);
        $drop = ['id' => 100, 'alert_key' => hash('sha256', 'cache-only-drop'), 'sport' => 'soccer', 'home' => 'Liverpool FC', 'away' => 'Manchester United',
            'league' => 'Premier League', 'sect' => 'Moneyline', 'outcome' => 'Draw', 'period' => 0, 'from_price' => 4.34, 'to_price' => 4.28,
            'alerted' => now()->timestamp, 'starts' => $entry['timestamp']];
        Redis::shouldReceive('get')->byDefault()->andReturnUsing(static fn (string $cacheKey): ?string => $cacheKey === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);
        Redis::shouldReceive('get')->with('Football:matches')->never();

        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', 4.4);
        $this->assertDatabaseCount('football_matches', 0);
        Http::assertNothingSent();
    }

    public function test_pinnacle_retains_the_drop_when_an_incomplete_cache_entry_has_no_database_fixture(): void
    {
        config(['services.sportsmeld.key' => '']);
        $this->actingAs($this->customer(), 'customers');
        $key = 'incomplete-fixture';
        $entry = ['unique_key' => $key, 'timestamp' => time() + 3600, 'result_status' => 'Prematch', 'betfair_match_id' => '900'];
        $index = ['ids' => [], 'matches' => [$key => $entry]];
        $cache = \Mockery::mock(FootballMatchCache::class);
        $cache->shouldReceive('active')->andReturnTrue();
        $cache->shouldReceive('index')->once()->andReturn($index);
        $cache->shouldReceive('read')->never();
        $this->app->instance(FootballMatchCache::class, $cache);
        $drop = ['id' => 100, 'alert_key' => hash('sha256', 'incomplete-fixture-drop'), 'sport' => 'soccer', 'home' => 'Torino', 'away' => 'Monza 1912',
            'sect' => 'Moneyline', 'outcome' => 'Home', 'period' => 0, 'from_price' => 2.5, 'to_price' => 2.3, 'starts' => $entry['timestamp'], 'alerted' => time()];
        Redis::shouldReceive('get')->byDefault()->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);
        Redis::shouldReceive('get')->with('Football:matches')->never();

        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.home', 'Torino')->assertJsonPath('data.0.bf_back', null);
        Http::assertNothingSent();
    }

    #[TestWith([true, false])]
    #[TestWith([false, false])]
    #[TestWith([true, true])]
    #[TestWith([false, true])]
    public function test_pinnacle_reads_only_the_matched_fixture_from_the_compact_index_without_a_full_snapshot(bool $hasSportsApiIndex, bool $partialIdentity): void
    {
        config(['services.sportsmeld.key' => null]);
        $this->actingAs($this->customer(), 'customers');
        $start = time() + 3600;
        $fixture = $this->fixture(['result_status' => 'Prematch', 'timestamp' => $start]);
        $fixture->update(['betfair_match_id' => '900']);
        $drop = ['id' => 100, 'alert_key' => hash('sha256', 'indexed-quote'), 'sport' => 'soccer', 'home' => 'Torino', 'away' => 'Monza 1912',
            'sect' => 'Moneyline', 'outcome' => 'Home', 'from_price' => 2.5, 'to_price' => 2.3, 'starts' => $start, 'alerted' => time()];
        $index = ['ids' => $hasSportsApiIndex ? ['12345' => $fixture->unique_key] : [], 'matches' => [$fixture->unique_key => ['_payload_bytes' => 2000]]];
        if ($partialIdentity) {
            $index['matches'][$fixture->unique_key] += ['unique_key' => $fixture->unique_key, 'betfair_match_id' => '900',
                'timestamp' => $start, 'result_status' => 'Prematch', 'team2' => null];
        }
        $current = array_replace($fixture->payload, ['betfair_match_id' => '900', 'timeline' => ['unused' => str_repeat('x', 100000)],
            'markets' => ['main' => [['marketType' => 'MATCH_ODDS', 'runner' => 'Torino', 'currentBackOdds' => 2.35, 'layOdds' => 2.38,
                'backSize' => 100, 'laySize' => 200, 'backOddsHistory' => [['timestamp' => time(), 'odds' => 2.35]], 'liquidity_history' => [['unused' => true]]]]]]);
        $cache = \Mockery::mock(FootballMatchCache::class);
        $cache->shouldReceive('active')->andReturnTrue();
        $cache->shouldReceive('index')->once()->andReturn($index);
        $cache->shouldReceive('read')->once()->with(null, [$fixture->unique_key], $index)->andReturn([$fixture->unique_key => $current]);
        $this->app->instance(FootballMatchCache::class, $cache);
        Redis::shouldReceive('get')->byDefault()->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);
        Redis::shouldReceive('get')->with('Football:matches')->never();

        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', 2.35)
            ->assertJsonPath('data.0.bf_lay', 2.38)->assertJsonPath('data.0.bf_back_size', 100);
        Http::assertNothingSent();
    }

    public function test_pinnacle_bypasses_an_oversized_unindexed_cache_and_keeps_database_quotes_available(): void
    {
        config(['services.sportsmeld.key' => null, 'services.football_match_cache' => false, 'services.football_fixture_index' => false]);
        $this->actingAs($this->customer(), 'customers');
        $start = time() + 3600;
        $fixture = $this->fixture(['result_status' => 'Prematch', 'timestamp' => $start]);
        $fixture->update(['betfair_match_id' => '900']);
        FootballMarket::query()->create(['selection_key' => hash('sha256', 'database-quote'), 'football_match_unique_key' => $fixture->unique_key,
            'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'runner' => 'Torino',
            'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => 'Torino', 'currentBackOdds' => 2.35]]);
        $drop = ['id' => 100, 'alert_key' => hash('sha256', 'bounded-quote'), 'sport' => 'soccer', 'home' => 'Torino', 'away' => 'Monza 1912',
            'sect' => 'Moneyline', 'outcome' => 'Home', 'from_price' => 2.5, 'to_price' => 2.3, 'starts' => $start, 'alerted' => time()];
        Redis::shouldReceive('get')->byDefault()->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);
        Redis::shouldReceive('get')->with('Football:matches')->never();
        Redis::shouldReceive('getrange')->once()->with('Football:matches', 0, 2_097_152)->andReturn(str_repeat('x', 2_097_153));

        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', 2.35);
        Http::assertNothingSent();
    }

    public function test_pinnacle_batches_fixture_lookups_across_distinct_event_start_times(): void
    {
        config(['services.sportsmeld.key' => null, 'services.football_match_cache' => false, 'services.football_fixture_index' => false]);
        $customer = $this->customer();
        $drops = [];
        for ($index = 0; $index < 20; $index++) {
            $start = time() + 7200 * ($index + 1);
            $fixture = $this->fixture(['unique_key' => 'batch-fixture-'.$index, 'sportsApiPro_match_id' => 80000 + $index,
                'team1' => 'Home Club '.$index, 'team2' => 'Away Club '.$index, 'timestamp' => $start, 'result_status' => 'Prematch']);
            $fixture->update(['betfair_match_id' => (string) (90000 + $index)]);
            $drops[] = ['id' => 1000 + $index, 'alert_key' => hash('sha256', 'batch-drop-'.$index), 'sport' => 'soccer',
                'home' => 'Home Club '.$index, 'away' => 'Away Club '.$index, 'starts' => $start, 'is_live' => false,
                'sect' => 'Moneyline', 'outcome' => 'Home', 'from_price' => 2.1, 'to_price' => 2, 'alerted' => time()];
        }
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => $drops]) : null);
        $this->actingAs($customer, 'customers');
        DB::enableQueryLog();

        try {
            $response = $this->getJson(route('football.pinnacle-odds'));
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $response->assertOk()->assertJsonCount(20, 'data');
        foreach ($response->json('data') as $index => $drop) {
            $this->assertSame('Home Club '.$index, $drop['bf_home']);
        }
        $fixtureQueries = array_filter($queries, static fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select') && str_contains($query['query'], 'football_matches'));
        $this->assertCount(1, $fixtureQueries);
        Http::assertNothingSent();
    }

    public function test_pinnacle_archive_uses_bounded_keyset_pages_with_no_provider_or_redis_reads(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-11-07 12:00:00 UTC'));
        config(['services.telegram.enabled' => false, 'services.sportsmeld.key' => 'must-not-be-used']);
        $this->actingAs($this->customer(), 'customers');
        Redis::shouldReceive('get')->never();
        $drops = [];
        for ($index = 1; $index <= 105; $index++) {
            $drops[] = ['alert_key' => str_pad(dechex($index), 64, '0', STR_PAD_LEFT), 'id' => $index,
                'sport' => 'soccer', 'home' => 'Home '.$index, 'away' => 'Away '.$index, 'sect' => 'Moneyline',
                'from_price' => 2, 'to_price' => 1.8, 'alerted' => now()->timestamp - 600];
        }
        (new ProcessAlert('pinnacle', ['alerts' => $drops]))->handle(app(AlertService::class));
        DB::enableQueryLog();
        $first = $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()
            ->assertJsonCount(100, 'data')->assertJsonPath('archive', true)->assertJsonPath('has_more', true)
            ->assertJsonPath('data.0.id', 105)->assertJsonPath('data.99.id', 6)->assertJsonPath('data.0.bf_back', null);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'pinnacle_drop_archives')));
        $this->assertCount(1, array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'pinnacle_daily_statistics')));
        $this->assertCount(0, array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'football_matches') || str_contains($query['query'], 'pinnacle_betfair_events')));
        (new ProcessAlert('pinnacle', ['alerts' => [array_replace($drops[0], ['alert_key' => str_repeat('f', 64)])]]))->handle(app(AlertService::class));
        $cursor = $first->json('next_cursor');
        $next = $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'before_time' => $cursor['time'], 'before_key' => $cursor['key']]))
            ->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('has_more', false)->assertJsonPath('next_cursor', null);
        $this->assertSame([5, 4, 3, 2, 1], array_column($next->json('data'), 'id'));
        $this->get(route('football.pinnacle-odds', ['archive' => 1, 'market_filter' => 'handicap', 'mode' => 'live']))
            ->assertOk()->assertViewIs('users.sports.Football.marketActivity')->assertSee('Daily Archive')->assertSee('Load more history')->assertSee('Betfair Higher Odds Ratio')->assertSee('Daily statistics reset at 6:00 AM Georgia time (GET / UTC+4).')->assertSee('Incoming prematch alerts are counted in daily statistics and remain available for up to 30 minutes, or until the match starts, whichever comes first.');
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'before_key' => 'invalid']))->assertUnprocessable()
            ->assertJsonValidationErrors(['before_time', 'before_key']);
        Http::assertNothingSent();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_pinnacle_initial_page_selects_main_markets_in_controls_and_live_state(bool $archive): void
    {
        config(['services.telegram.enabled' => false]);
        $this->actingAs($this->customer(), 'customers');
        Redis::shouldReceive('get')->andReturn(null);

        $this->get(route('football.pinnacle-odds', $archive ? ['archive' => 1] : []))
            ->assertOk()->assertSee('data-market-filter="main" aria-pressed="true"', false)
            ->assertSee('data-market-filter="all" aria-pressed="false"', false)
            ->assertSee('marketFilter: "main"', false);
    }

    public function test_pinnacle_archive_defaults_to_main_and_keeps_explicit_handicap_and_all_choices(): void
    {
        config(['services.telegram.enabled' => false]);
        $this->actingAs($this->customer(), 'customers');
        $base = ['sport' => 'soccer', 'home' => 'Home', 'away' => 'Away', 'sect' => 'Moneyline', 'outcome' => 'Home',
            'from_price' => 2, 'to_price' => 1.8, 'alerted' => now()->timestamp - 600, 'starts' => now()->addHour()->timestamp];
        $main = $base + ['id' => 321, 'alert_key' => hash('sha256', 'default-main')];
        $handicap = array_replace($base, ['id' => 322, 'alert_key' => hash('sha256', 'default-handicap'), 'sect' => 'spread', 'point' => -1]);
        (new ProcessAlert('pinnacle', ['alerts' => [$main, $handicap]]))->handle(app(AlertService::class));

        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 321);
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'market_filter' => 'all']))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'market_filter' => 'handicap']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 322);
        $this->get(route('football.pinnacle-odds', ['archive' => 1, 'market_filter' => 'all']))->assertOk()
            ->assertSee('data-market-filter="all" aria-pressed="true"', false)->assertSee('marketFilter: "all"', false);
    }

    public function test_pinnacle_archive_filters_whole_history_and_restores_parent_names_without_live_cache(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-11-07 12:00:00 UTC'));
        config(['services.telegram.enabled' => false]);
        $this->actingAs($this->customer(['timezone' => 'UTC']), 'customers');
        Redis::shouldReceive('get')->never();
        $base = ['sport' => 'soccer', 'id' => 100, 'home' => 'Home', 'away' => 'Away', 'sect' => 'Moneyline',
            'from_price' => 2, 'to_price' => 1.8, 'alerted' => now()->timestamp - 600];
        $variants = [
            'hour' => ['starts' => now()->addHour()->timestamp],
            'three' => ['starts' => now()->addHours(3)->timestamp],
            'future' => ['starts' => now('UTC')->addDay()->startOfDay()->timestamp],
            'live' => ['is_live' => true, 'starts' => now()->subHour()->timestamp],
            'past' => ['starts' => now()->subHour()->timestamp],
            'missing' => [],
            'handicap' => ['sect' => 'spread', 'point' => -4, 'sport' => 'snooker'],
            'btts' => ['id' => 101, 'parent_id' => 100, 'home' => 'Yes', 'away' => 'No', 'sect' => 'btts', 'outcome' => 'home'],
        ];
        $drops = [];
        foreach ($variants as $key => $changes) {
            $drops[] = array_replace($base, ['alert_key' => hash('sha256', $key)], $changes);
        }
        $parent = ['id' => 100, 'sport' => 'soccer', 'home' => 'Home Club', 'away' => 'Away Club'];
        (new ProcessAlert('pinnacle', ['alerts' => $drops, 'parents' => ['soccer' => [100 => $parent]]]))->handle(app(AlertService::class));
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertJsonCount(5, 'data');
        foreach (['1h' => 1, '3h' => 2, 'future' => 1, 'today' => 2] as $filter => $count) {
            $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'time_filter' => $filter]))->assertOk()->assertJsonCount($count, 'data');
        }
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'mode' => 'live']))->assertOk()->assertJsonCount(5, 'data');
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'sport' => 'snooker', 'market_filter' => 'handicap']))
            ->assertOk()->assertJsonCount(0, 'data');
        $main = $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'market_filter' => 'main']))->assertOk()->assertJsonCount(5, 'data');
        $btts = collect($main->json('data'))->firstWhere('sect', 'btts');
        $this->assertSame(['Home Club', 'Away Club', 'yes'], [$btts['home'], $btts['away'], $btts['outcome']]);
        $this->travelTo(new \DateTimeImmutable('2026-11-08 02:00:00 UTC'));
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertJsonCount(0, 'data');
        $recent = array_replace($drops[0], ['alert_key' => hash('sha256', 'recent-arrival'), 'starts' => now()->addHour()->timestamp, 'alerted' => now()->timestamp, 'monitor_received_at' => now()->getTimestampMs() / 1000]);
        (new ProcessAlert('pinnacle', ['alerts' => [$recent]]))->handle(app(AlertService::class));
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertJsonCount(0, 'data');
        $this->travel(7)->minutes();
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertJsonCount(1, 'data');
        Http::assertNothingSent();
    }

    public function test_pinnacle_archive_preserves_existing_subscription_access_control(): void
    {
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertForbidden()->assertJsonPath('access_required', true);
        $this->actingAs($this->customer(['trial_ends_at' => now()->subDay()]), 'customers')
            ->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertForbidden()->assertJsonPath('access_required', true);
    }

    public function test_archive_day_switches_at_six_georgia_time_independently_of_customer_timezone(): void
    {
        config(['services.telegram.enabled' => false]);
        $this->travelTo(new \DateTimeImmutable('2026-11-07 01:50:00 UTC'));
        $this->actingAs($this->customer(['timezone' => 'America/New_York']), 'customers');
        $drop = ['id' => 100, 'sport' => 'snooker', 'home' => 'Home', 'away' => 'Away', 'sect' => 'Moneyline',
            'from_price' => 2, 'to_price' => 1.8, 'alerted' => now()->timestamp, 'alert_key' => str_repeat('a', 64)];
        (new ProcessAlert('pinnacle', ['alerts' => [$drop]]))->handle(app(AlertService::class));
        $this->travelTo(new \DateTimeImmutable('2026-11-07 01:59:59 UTC'));
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('summary.total', 1);
        $this->travelTo(new \DateTimeImmutable('2026-11-07 02:00:00 UTC'));
        (new ProcessAlert('pinnacle', ['alerts' => [$drop, array_replace($drop, ['alert_key' => str_repeat('b', 64), 'alerted' => now()->timestamp])]]))->handle(app(AlertService::class));
        $this->travel(8)->minutes();
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.alert_key', str_repeat('b', 64))->assertJsonPath('summary.total', 1);
        $this->artisan('pinnacle:archive-maintain')->assertSuccessful();
        $this->assertDatabaseCount('pinnacle_drop_archives', 2);
        $this->travelTo(new \DateTimeImmutable('2026-11-07 02:20:00 UTC'));
        $this->artisan('pinnacle:archive-maintain')->assertSuccessful();
        $this->assertDatabaseCount('pinnacle_drop_archives', 1);
        $this->assertDatabaseCount('pinnacle_daily_statistics', 2);
    }

    public function test_archive_analytics_use_original_betfair_quotes_and_count_unavailable_comparisons_separately(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-11-07 12:00:00 UTC'));
        config(['services.telegram.enabled' => false, 'services.sportsmeld.key' => 'must-not-call-provider']);
        Redis::shouldReceive('get')->never();
        $this->actingAs($this->customer(), 'customers');
        $at = now()->timestamp - 600;
        $fixture = $this->fixture(['result_status' => 'Prematch', 'timestamp' => $at + 3600]);
        $fixture->forceFill(['betfair_match_id' => '900', 'data_sync_complete' => true])->save();
        $market = FootballMarket::query()->create(['selection_key' => hash('sha256', 'archive-market'), 'football_match_unique_key' => $fixture->unique_key,
            'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'runner' => 'Torino', 'payload' => [
                'marketType' => 'MATCH_ODDS', 'runner' => 'Torino', 'currentBackOdds' => 5, 'market_status' => 'OPEN',
                'monitoring_baseline_version' => 1, 'opening_odds_source' => 'first_observed', 'initialBackOdds' => 2.4,
                'initialBackOddsTimestamp' => $at - 30, 'odds_history_processed_at' => $at + 30,
                'backOddsHistory' => [['timestamp' => $at - 30, 'odds' => 2.4], ['timestamp' => $at + 30, 'odds' => 5]],
            ]]);
        $base = ['id' => 100, 'sport' => 'soccer', 'home' => 'Torino', 'away' => 'Monza 1912', 'sect' => 'Moneyline', 'outcome' => 'Home',
            'starts' => $at + 3600, 'from_price' => 3, 'to_price' => 1.8, 'alerted' => $at];
        $drops = [];
        foreach (['higher' => [], 'lower' => ['to_price' => 2.5], 'equal' => ['to_price' => 2.4],
            'missing' => ['sect' => 'btts', 'outcome' => 'Yes'], 'live' => ['is_live' => true], 'snooker' => ['sport' => 'snooker']] as $key => $fields) {
            $drops[] = array_replace($base, ['alert_key' => hash('sha256', $key)], $fields);
        }
        $job = new ProcessAlert('pinnacle', ['alerts' => $drops]);
        $job->handle(app(AlertService::class));
        $this->assertDatabaseHas('pinnacle_drop_archives', ['alert_key' => hash('sha256', 'higher'), 'betfair_higher' => true]);
        $this->assertDatabaseHas('pinnacle_drop_archives', ['alert_key' => hash('sha256', 'equal'), 'betfair_higher' => false]);
        $this->assertDatabaseHas('pinnacle_drop_archives', ['alert_key' => hash('sha256', 'missing'), 'betfair_higher' => null]);
        $missingQuote = json_decode(DB::table('pinnacle_drop_archives')->where('alert_key', hash('sha256', 'missing'))->value('payload'), true);
        $this->assertSame('market_unavailable', $missingQuote['bf_quote_reason']);
        $unsupportedQuote = json_decode(DB::table('pinnacle_drop_archives')->where('alert_key', hash('sha256', 'snooker'))->value('payload'), true);
        $this->assertSame('unsupported_sport', $unsupportedQuote['bf_quote_reason']);
        $payload = $market->payload;
        $payload['backOddsHistory'] = [['timestamp' => $at - 30, 'odds' => 1.2]];
        $market->update(['payload' => $payload]);
        $job->handle(app(AlertService::class));
        $response = $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()
            ->assertJsonPath('summary.total', 6)->assertJsonPath('summary.higher', 1)->assertJsonPath('summary.compared', 3);
        $this->assertSame(['snooker' => 1, 'soccer' => 5], collect($response->json('summary.sports'))->pluck('total', 'sport')->all());
        $higher = collect($response->json('data'))->firstWhere('alert_key', hash('sha256', 'higher'));
        $this->assertSame(2.4, $higher['bf_back']);
        $this->assertSame($at - 30, $higher['bf_quote_at']);
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'sport' => 'snooker']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('summary.total', 6);
        $this->get(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertSee('16.7%')->assertSee('Compared: 3')->assertSee('Drop-time quote unavailable: 3');
        Http::assertNothingSent();
    }

    public function test_live_monitor_recovers_missing_cached_alerts_without_restarting_their_seven_minutes(): void
    {
        config(['services.sportsmeld.key' => null, 'services.football_fixture_index' => false]);
        $this->actingAs($this->customer(), 'customers');
        $now = microtime(true);
        $base = ['sport' => 'soccer', 'away' => 'Italy', 'sect' => 'Moneyline', 'outcome' => 'Home', 'period' => 0,
            'from_price' => 2.0, 'to_price' => 1.9, 'starts' => (int) $now + 3600, 'alerted' => (int) $now - 60, 'monitor_received_at' => $now - 60];
        $drops = [
            $base + ['id' => 1, 'home' => 'France', 'alert_key' => hash('sha256', 'cached')],
            $base + ['id' => 2, 'home' => 'Belgium', 'alert_key' => hash('sha256', 'missing')],
            array_replace($base, ['id' => 3, 'home' => 'Spain', 'alert_key' => hash('sha256', 'expired'), 'monitor_received_at' => $now - 421]),
            array_replace($base, ['id' => 4, 'home' => 'Croatia', 'alert_key' => hash('sha256', 'delayed'), 'alerted' => (int) $now - 3600]),
        ];
        foreach ($drops as $drop) {
            DB::table('pinnacle_drop_archives')->insert([
                'alert_key' => $drop['alert_key'], 'alerted_at' => $drop['alerted'], 'sport' => 'soccer', 'is_live' => false,
                'market_group' => 0, 'payload' => json_encode($drop),
            ]);
        }
        $cached = [$drops[0]];
        Redis::shouldReceive('get')->andReturnUsing(static function (string $key) use (&$cached): ?string {
            return $key === 'Pinnacle:drops' ? json_encode(['alerts' => $cached]) : null;
        });

        $response = $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonCount(3, 'data');
        $this->assertEqualsCanonicalizing([1, 2, 4], array_column($response->json('data'), 'id'));
        $receivedAt = collect($response->json('data'))->firstWhere('id', 2)['monitor_received_at'];
        $this->assertSame($now - 60, $receivedAt);
        $cached = [];
        $response = $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame($receivedAt, collect($response->json('data'))->firstWhere('id', 2)['monitor_received_at']);
        Http::assertNothingSent();
    }

    #[TestWith(['Arthur Fils', 'Fils', true])]
    #[TestWith(['Arthur Fils', 'A. Fils', true])]
    #[TestWith(['Rafael Jodar', 'Jodar R.', true])]
    #[TestWith(['Rafael Jodar', 'Jodar, Rafael', true])]
    #[TestWith(['Arthur Fils (Games)', 'A Fils', true])]
    #[TestWith(['Rafael Jódar (Sets)', 'Rafael Jodar', true])]
    #[TestWith(['Arthur Fils', 'B. Fils', false])]
    #[TestWith(['Arthur Fils', 'Alex Fils', false])]
    #[TestWith(['Rafael Jodar', 'Rafael Nadal', false])]
    #[TestWith(['Arthur Fils', '', false])]
    #[TestWith(['Arthur Fils', 'A Fils / R Jodar', false])]
    #[TestWith(['Arthur Fils / Rafael Jodar', 'R Jodar / A Fils', true])]
    public function test_pinnacle_tennis_player_matching_supports_short_names_without_conflicting_initials(string $first, string $second, bool $matches): void
    {
        $this->assertSame($matches, app(TennisSyncController::class)->playerNamesMatch($first, $second));
    }

    #[TestWith(['Fils', 'Jodar'])]
    #[TestWith(['A. Fils', 'R. Jodar'])]
    #[TestWith(['Arthur Fils', 'Rafael Jodar'])]
    public function test_pinnacle_tennis_matches_local_players_and_runners_and_saves_the_event_mapping(string $home, string $away): void
    {
        config(['services.sportsmeld.key' => 'must-not-call']);
        $this->actingAs($this->customer(), 'customers');
        $start = time() + 60;
        $fixture = TennisMatch::query()->create([
            'unique_key' => 'fils|jodar|'.$start, 'betfair_match_id' => 'tennis-event',
            'team1' => $home, 'team2' => $away, 'tournament' => 'ATP World Tour Finals',
            'result_status' => 'Prematch', 'start_timestamp' => $start, 'data_sync_complete' => false,
            'payload' => ['team1' => $home, 'team2' => $away],
        ]);
        TennisMarket::query()->create([
            'selection_key' => hash('sha256', 'jodar'), 'tennis_match_unique_key' => $fixture->unique_key,
            'market_id' => 'match-odds', 'market_type' => 'MATCH_ODDS', 'runner' => 'Rafael Jodar',
            'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => 'Rafael Jodar', 'currentBackOdds' => 1.98],
        ]);
        $drop = ['id' => 101, 'alert_key' => hash('sha256', 'fils-jodar'), 'sport' => 'tennis',
            'home' => 'Arthur Fils', 'away' => 'Rafael Jodar', 'league' => 'ATP Finals',
            'sect' => 'Moneyline', 'outcome' => 'Away', 'period' => 0,
            'from_price' => 1.94, 'to_price' => 1.90, 'alerted' => time(), 'starts' => $start];
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);

        $this->getJson(route('football.pinnacle-odds', ['sport' => 'tennis']))->assertOk()->assertJsonPath('data.0.bf_back', 1.98)->assertJsonPath('data.0.bf_away', $away);
        $this->getJson(route('football.pinnacle-odds', ['sport' => 'tennis']))->assertOk()->assertJsonPath('data.0.bf_back', 1.98);

        $this->assertDatabaseHas('pinnacle_betfair_events', ['sport' => 'tennis', 'pinnacle_event_id' => '101', 'betfair_event_id' => 'tennis-event']);
        $this->assertDatabaseHas('pinnacle_betfair_team_aliases', ['sport' => 'tennis', 'source_name' => 'Rafael Jodar', 'betfair_name' => $away]);
        $this->assertFalse($fixture->fresh()->data_sync_complete);
        Http::assertNothingSent();
    }

    #[TestWith(['Draw', 4.4])]
    #[TestWith(['Away', 3.1])]
    #[TestWith(['Home', 2.2])]
    public function test_pinnacle_links_full_team_names_to_betfair_abbreviations_and_each_current_selection(string $selection, float $price): void
    {
        $this->freezeTime();
        config(['services.sportsmeld.key' => '', 'services.football_fixture_index' => false]);
        $this->actingAs($this->customer(), 'customers');
        $start = now()->addHour()->timestamp;
        $betfair = $this->fixture(['unique_key' => 'liverpool|man_united', 'team1' => 'Liverpool', 'team2' => 'Man Utd',
            'tournament' => 'English Premier League', 'result_status' => 'Prematch', 'timestamp' => $start]);
        $betfair->forceFill(['betfair_match_id' => '36225147', 'sports_api_pro_match_id' => null, 'data_sync_complete' => false])->save();
        $this->fixture(['unique_key' => 'liverpool_fc|manchester_united', 'sportsApiPro_match_id' => 16363660,
            'team1' => 'Liverpool FC', 'team2' => 'Manchester United', 'tournament' => 'Premier League',
            'result_status' => 'Prematch', 'timestamp' => $start]);
        foreach (['Liverpool' => 2.2, 'Man Utd' => 3.1, 'The Draw' => 4.4] as $runner => $quote) {
            FootballMarket::query()->create(['selection_key' => hash('sha256', 'liverpool-'.$runner), 'football_match_unique_key' => $betfair->unique_key,
                'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'runner' => $runner,
                'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => $runner, 'currentBackOdds' => $quote]]);
        }
        $drop = ['id' => 100, 'alert_key' => hash('sha256', 'liverpool-'.$selection), 'sport' => 'soccer', 'home' => 'Liverpool FC',
            'away' => 'Manchester United', 'country' => 'England', 'league' => 'Premier League', 'sect' => 'Moneyline', 'outcome' => $selection,
            'period' => 0, 'from_price' => 4.34, 'to_price' => 4.28, 'alerted' => now()->timestamp, 'starts' => $start];
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);

        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', $price)
            ->assertJsonPath('data.0.bf_home', 'Liverpool')->assertJsonPath('data.0.bf_away', 'Man Utd');
        $this->assertDatabaseHas('pinnacle_betfair_events', ['sport' => 'soccer', 'pinnacle_event_id' => '100', 'betfair_event_id' => '36225147']);
        Http::assertNothingSent();
    }

    #[TestWith(['Borussia Mönchengladbach', 'Borussia Dortmund', 'Mgladbach', 'Dortmund', 'Bundesliga'])]
    #[TestWith(['Borussia Moenchengladbach', 'Borussia Dortmund', 'B. Monchengladbach', 'Dortmund', 'Bundesliga'])]
    #[TestWith(['Malmö FF', 'Elfsborg', 'Malmo', 'Elfsborg', 'Allsvenskan'])]
    #[TestWith(['FC Copenhagen', 'Brondby IF', 'Copenhagen', 'Brondby IF', 'Superliga'])]
    public function test_pinnacle_displays_current_betfair_quotes_for_club_name_variants(string $home, string $away, string $betfairHome, string $betfairAway, string $league): void
    {
        $this->freezeTime();
        config(['services.sportsmeld.key' => '', 'services.football_fixture_index' => false]);
        $this->actingAs($this->customer(), 'customers');
        $start = now()->addHour()->timestamp;
        $fixture = $this->fixture(['team1' => $betfairHome, 'team2' => $betfairAway, 'tournament' => $league, 'result_status' => 'Prematch', 'timestamp' => $start]);
        $fixture->forceFill(['betfair_match_id' => '900', 'data_sync_complete' => false])->save();
        FootballMarket::query()->create(['selection_key' => hash('sha256', 'club-quote'), 'football_match_unique_key' => $fixture->unique_key,
            'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'runner' => $betfairHome,
            'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => $betfairHome, 'currentBackOdds' => 2.42, 'layOdds' => 2.46]]);
        $this->fixture(['unique_key' => 'unrelated-league-fixture', 'sportsApiPro_match_id' => 100,
            'team1' => 'Unrelated Home', 'team2' => 'Unrelated Away', 'tournament' => $league,
            'result_status' => 'Prematch', 'timestamp' => $start])->forceFill(['betfair_match_id' => '901'])->save();
        $fixture->forceFill(['tournament' => 'Provider '.$league])->save();
        $drop = ['id' => 100, 'alert_key' => hash('sha256', 'club-drop'), 'sport' => 'soccer', 'home' => $home, 'away' => $away,
            'league' => $league, 'sect' => 'Moneyline', 'outcome' => 'Home', 'period' => 0,
            'from_price' => 2.6, 'to_price' => 2.3, 'alerted' => now()->timestamp, 'starts' => $start];
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);

        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', 2.42)->assertJsonPath('data.0.bf_lay', 2.46)
            ->assertJsonPath('data.0.bf_home', $betfairHome);
        $this->assertDatabaseHas('pinnacle_betfair_events', ['sport' => 'soccer', 'pinnacle_event_id' => '100', 'betfair_event_id' => '900']);
        Http::assertNothingSent();
    }

    public function test_pinnacle_resolves_abbreviated_runner_names_and_refreshes_the_current_quote(): void
    {
        $this->freezeTime();
        config(['services.sportsmeld.key' => '', 'services.football_fixture_index' => false]);
        $this->actingAs($this->customer(), 'customers');
        $start = now()->addHour()->timestamp;
        $fixture = $this->fixture(['team1' => 'Borussia Mönchengladbach', 'team2' => 'Borussia Dortmund',
            'tournament' => 'Bundesliga', 'result_status' => 'Prematch', 'timestamp' => $start]);
        $fixture->forceFill(['betfair_match_id' => '900'])->save();
        $market = FootballMarket::query()->create(['selection_key' => hash('sha256', 'short-runner'), 'football_match_unique_key' => $fixture->unique_key,
            'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'runner' => 'Mgladbach',
            'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => 'Mgladbach', 'currentBackOdds' => 2.42]]);
        $drop = ['id' => 100, 'alert_key' => hash('sha256', 'short-runner-drop'), 'sport' => 'soccer',
            'home' => 'Borussia Mönchengladbach', 'away' => 'Borussia Dortmund', 'league' => 'Bundesliga',
            'sect' => 'Moneyline', 'outcome' => 'Home', 'period' => 0, 'from_price' => 2.6, 'to_price' => 2.3,
            'alerted' => now()->timestamp, 'starts' => $start];
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);

        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', 2.42);
        $market->update(['payload' => ['marketType' => 'MATCH_ODDS', 'runner' => 'Mgladbach', 'currentBackOdds' => 2.48]]);
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', 2.48);
        Http::assertNothingSent();
    }

    public function test_pinnacle_uses_quotes_from_duplicate_records_of_the_same_betfair_event(): void
    {
        $this->freezeTime();
        config(['services.sportsmeld.key' => '', 'services.football_fixture_index' => false]);
        $this->actingAs($this->customer(), 'customers');
        $start = now()->addHour()->timestamp;
        foreach (['provider-one', 'provider-two'] as $index => $key) {
            $fixture = $this->fixture(['unique_key' => $key, 'sportsApiPro_match_id' => $index + 100,
                'team1' => 'Schalke 04', 'team2' => 'Stuttgart', 'tournament' => 'Bundesliga', 'result_status' => 'Prematch', 'timestamp' => $start]);
            $fixture->forceFill(['betfair_match_id' => '900'])->save();
        }
        FootballMarket::query()->create(['selection_key' => hash('sha256', 'older-duplicate-event-quote'), 'football_match_unique_key' => 'provider-one',
            'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'runner' => 'Stuttgart',
            'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => 'Stuttgart', 'currentBackOdds' => 2.12],
            'updated_at' => now()->subMinute()]);
        FootballMarket::query()->create(['selection_key' => hash('sha256', 'duplicate-event-quote'), 'football_match_unique_key' => 'provider-two',
            'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'runner' => 'Stuttgart',
            'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => 'Stuttgart', 'currentBackOdds' => 1.91]]);
        $drop = ['id' => 100, 'alert_key' => hash('sha256', 'duplicate-event-drop'), 'sport' => 'soccer', 'home' => 'Schalke 04', 'away' => 'Stuttgart',
            'league' => 'Bundesliga', 'sect' => 'Moneyline', 'outcome' => 'Away', 'period' => 0,
            'from_price' => 2, 'to_price' => 1.8, 'alerted' => now()->timestamp, 'starts' => $start];
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);

        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', 1.91);
        $this->assertDatabaseHas('pinnacle_betfair_events', ['sport' => 'soccer', 'pinnacle_event_id' => '100', 'betfair_event_id' => '900']);
        Http::assertNothingSent();
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_pinnacle_reserve_team_alias_matches_celta_vigo_ii_to_b_locally_and_persists(bool $complete): void
    {
        config(['services.sportsmeld.key' => 'must-not-call', 'services.football_fixture_index' => false]);
        $this->actingAs($this->customer(), 'customers');
        $start = time() + 3600;
        $fixture = $this->fixture(['team1' => 'Albacete', 'team2' => 'Celta Vigo B', 'tournament' => 'LaLiga 2', 'result_status' => 'Prematch', 'timestamp' => $start]);
        $fixture->forceFill(['betfair_match_id' => '900', 'sports_api_pro_match_id' => $complete ? '12345' : null, 'data_sync_complete' => $complete])->save();
        FootballMarket::query()->create(['selection_key' => hash('sha256', 'celta-away'), 'football_match_unique_key' => $fixture->unique_key,
            'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'runner' => 'Celta Vigo B',
            'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => 'Celta Vigo B', 'currentBackOdds' => 5.5]]);
        $drop = ['id' => 100, 'alert_key' => hash('sha256', 'celta'), 'sport' => 'soccer', 'home' => 'Albacete', 'away' => 'Celta Vigo II',
            'league' => 'LaLiga 2', 'sect' => 'Moneyline', 'outcome' => 'Away', 'period' => 0,
            'from_price' => 5.6, 'to_price' => 5.3, 'alerted' => time(), 'starts' => $start];
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', 5.5)->assertJsonPath('data.0.bf_away', 'Celta Vigo B');
        $this->assertDatabaseHas('pinnacle_betfair_events', ['sport' => 'soccer', 'pinnacle_event_id' => '100', 'betfair_event_id' => '900']);
        $this->assertDatabaseHas('pinnacle_betfair_team_aliases', ['sport' => 'soccer', 'source_name' => 'Celta Vigo II', 'betfair_name' => 'Celta Vigo B']);
        $this->assertSame($complete, $fixture->refresh()->data_sync_complete);
        if (! $complete) {
            config(['cache.stores.redis.driver' => 'array']);
            Redis::shouldReceive('zrangebyscore')->with('Football:matches:cleaned', \Mockery::any(), '+inf')->andReturn([]);
            Redis::shouldReceive('set')->with('Football:matches', \Mockery::any())->never();
            $runner = ['marketId' => 'main', 'marketType' => 'MATCH_ODDS', 'runnerId' => '22', 'runner' => 'Celta Vigo B',
                'backOdds' => 5.2, 'backSize' => 100, 'layOdds' => 5.3, 'laySize' => 100, 'market_status' => 'OPEN'];
            $update = new ReflectionMethod(ListenBetfairOdds::class, 'updateFootballCache');
            $this->assertTrue($update->invoke(app(ListenBetfairOdds::class), ['900' => ['markets' => [$runner]]], false));
            $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', 5.2);
            $this->assertFalse($fixture->refresh()->data_sync_complete);
            $runner['market_status'] = 'SUSPENDED';
            $update->invoke(app(ListenBetfairOdds::class), ['900' => ['markets' => [$runner]]], false);
            $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', null);
        }
        Http::assertNothingSent();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_sportsmeld_only_learns_confirmed_aliases_and_reuses_them_offline(bool $ambiguous): void
    {
        config(['services.sportsmeld.key' => 'test-key', 'services.football_fixture_index' => false]);
        $this->actingAs($this->customer(), 'customers');
        $start = time() + 3600;
        $fixture = $this->fixture(['team1' => 'Albacete Balompie', 'team2' => 'Celta B', 'tournament' => 'LaLiga 2', 'result_status' => 'Prematch', 'timestamp' => $start]);
        $fixture->forceFill(['betfair_match_id' => '900', 'data_sync_complete' => true])->save();
        $drop = ['id' => 100, 'alert_key' => hash('sha256', 'learned-celta'), 'sport' => 'soccer', 'home' => 'Albacete', 'away' => 'Celta Vigo II',
            'league' => 'LaLiga 2', 'sect' => 'Moneyline', 'outcome' => 'Home', 'from_price' => 2, 'to_price' => 1.9, 'starts' => $start, 'alerted' => time()];
        Redis::shouldReceive('get')->andReturnUsing(static function (string $key) use (&$drop): ?string {
            return $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null;
        });
        Http::fake(['https://sportsmeld.arbitragex.pro/api/match' => static fn ($request) => Http::response(['details' => [['name' => $request['reference'] === 'Albacete' ? 'Albacete Balompie' : 'Celta B', 'match' => true, 'ambiguous' => $ambiguous && $request['reference'] !== 'Albacete']]])]);
        $response = $this->getJson(route('football.pinnacle-odds'))->assertOk();
        if ($ambiguous) {
            $response->assertJsonMissingPath('data.0.bf_home');
            $this->assertDatabaseCount('pinnacle_betfair_team_aliases', 0);
            $this->assertDatabaseCount('pinnacle_betfair_events', 0);
            Http::assertSentCount(2);

            return;
        }
        $response->assertJsonPath('data.0.bf_home', 'Albacete Balompie');
        Http::assertSentCount(2);
        $this->assertDatabaseHas('pinnacle_betfair_team_aliases', ['source_name' => 'Albacete', 'betfair_name' => 'Albacete Balompie']);
        $nextStart = $start + 3 * 86400;
        $next = $this->fixture(['unique_key' => 'next-albacete', 'sportsApiPro_match_id' => 54321, 'team1' => 'Albacete Balompie', 'team2' => 'Next Club',
            'tournament' => 'LaLiga 2', 'result_status' => 'Prematch', 'timestamp' => $nextStart]);
        $next->forceFill(['betfair_match_id' => '901', 'data_sync_complete' => true])->save();
        $drop = array_replace($drop, ['id' => 101, 'alert_key' => hash('sha256', 'next-albacete'), 'away' => 'Next Club', 'starts' => $nextStart]);
        config(['services.sportsmeld.key' => null]);
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_home', 'Albacete Balompie')->assertJsonPath('data.0.bf_away', 'Next Club');
        $this->assertDatabaseHas('pinnacle_betfair_events', ['pinnacle_event_id' => '101', 'betfair_event_id' => '901']);
        Http::assertSentCount(2);
    }

    #[TestWith(['Celta Vigo', 0, false])]
    #[TestWith(['Celta Vigo U21', 0, false])]
    #[TestWith(['Celta Vigo B Women', 0, false])]
    #[TestWith(['Celta Vigo III', 0, false])]
    #[TestWith(['Celta Vigo B', 7200, false])]
    #[TestWith(['Celta Vigo B', 0, true])]
    public function test_pinnacle_reserve_matching_rejects_first_teams_youth_wrong_time_and_ambiguous_fixtures(string $away, int $offset, bool $duplicate): void
    {
        config(['services.sportsmeld.key' => 'must-not-call', 'services.football_fixture_index' => false]);
        $this->actingAs($this->customer(), 'customers');
        $start = time() + 3600;
        foreach ($duplicate ? [900, 901] : [900] as $id) {
            $fixture = $this->fixture(['unique_key' => 'candidate-'.$id, 'sportsApiPro_match_id' => $id, 'team1' => 'Albacete', 'team2' => $away,
                'tournament' => 'LaLiga 2', 'result_status' => 'Prematch', 'timestamp' => $start + $offset]);
            $fixture->forceFill(['betfair_match_id' => (string) $id, 'data_sync_complete' => true])->save();
        }
        $drop = ['id' => 100, 'alert_key' => hash('sha256', 'ambiguous-celta'), 'sport' => 'soccer', 'home' => 'Albacete', 'away' => 'Celta Vigo II',
            'league' => 'LaLiga 2', 'sect' => 'Moneyline', 'outcome' => 'Away', 'from_price' => 2, 'to_price' => 1.9, 'starts' => $start, 'alerted' => time()];
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonPath('data.0.bf_back', null)->assertJsonMissingPath('data.0.bf_home');
        $this->assertDatabaseCount('pinnacle_betfair_events', 0);
        $this->assertDatabaseCount('pinnacle_betfair_team_aliases', 0);
        Http::assertNothingSent();
    }

    public function test_live_monitor_includes_saved_drop_time_quote_without_overwriting_current_odds(): void
    {
        config(['services.sportsmeld.key' => null, 'services.football_fixture_index' => false]);
        $this->actingAs($this->customer(), 'customers');
        $at = time() - 5;
        $fixture = $this->fixture(['result_status' => 'Prematch', 'timestamp' => $at + 3600]);
        $fixture->forceFill(['betfair_match_id' => '900', 'data_sync_complete' => true])->save();
        $market = FootballMarket::query()->create([
            'selection_key' => hash('sha256', 'live-quote'), 'football_match_unique_key' => $fixture->unique_key,
            'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'runner' => 'Torino',
            'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => 'Torino', 'currentBackOdds' => 2.5],
        ]);
        $drop = ['alert_key' => hash('sha256', 'live-drop'), 'id' => 100, 'sport' => 'soccer', 'home' => 'Torino', 'away' => 'Monza 1912',
            'sect' => 'Moneyline', 'outcome' => 'Home', 'starts' => $at + 3600, 'from_price' => 3.2, 'to_price' => 2.8,
            'alerted' => $at, 'monitor_received_at' => $at];
        DB::table('pinnacle_drop_archives')->insert([
            'alert_key' => $drop['alert_key'], 'alerted_at' => $at, 'sport' => 'soccer', 'is_live' => false, 'market_group' => 0,
            'betfair_higher' => true, 'payload' => json_encode($drop + ['bf_at_drop' => 3.1, 'bf_quote_at' => $at - 10]),
        ]);
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Pinnacle:drops' ? json_encode(['alerts' => [$drop]]) : null);

        $this->getJson(route('football.pinnacle-odds'))->assertOk()
            ->assertJsonPath('data.0.bf_back', 2.5)->assertJsonPath('data.0.bf_at_drop', 3.1)->assertJsonPath('data.0.bf_quote_at', $at - 10);
        $market->delete();
        $this->getJson(route('football.pinnacle-odds'))->assertOk()
            ->assertJsonPath('data.0.bf_back', null)->assertJsonPath('data.0.bf_at_drop', 3.1);
        $this->assertDatabaseHas('pinnacle_drop_archives', ['alert_key' => $drop['alert_key'], 'betfair_higher' => true]);
        Http::assertNothingSent();
    }

    public function test_sports_api_turin_group_survives_flattened_cache_and_links_zverev_shelton(): void
    {
        $tournament = ['name' => 'Tour Finals Turin, Italy, Group A', 'category' => ['name' => 'ATP'],
            'uniqueTournament' => ['name' => 'ATP Finals', 'category' => ['name' => 'ATP']]];
        $competition = ['Year-End Championships', 'ATP Finals'];
        $this->assertSame($competition, TennisSyncController::tennisCompetition(['tournament' => $tournament]));
        $this->assertSame($competition, TennisSyncController::tennisCompetition([
            'tournament' => $tournament['name'], 'tournament_tour' => 'ATP', 'tennis_competition' => $competition,
        ]));
        $this->assertNull(TennisSyncController::tennisCompetition(['tournament' => $tournament['name'], 'tournament_tour' => 'WTA']));
        $sync = (new \ReflectionClass(TennisSyncController::class))->newInstanceWithoutConstructor();
        $match = ['team1' => 'A Zverev', 'team2' => 'Be Shelton', 'timestamp' => 1794943800, 'result_status' => 'Live'];

        $key = (new ReflectionMethod($sync, 'checkIfRecordExistsOnAnotherApi'))->invoke(
            $sync, 'alexander_zverev|ben_shelton|1794944400', 'Alexander Zverev', 'Ben Shelton', 1794944400,
            ['betfair-zverev-shelton' => $match],
            ['home' => ['Alexander Zverev', 'A. Zverev'], 'away' => ['Ben Shelton', 'B. Shelton']],
        );

        $this->assertSame('betfair-zverev-shelton', $key);
    }

    public function test_pinnacle_tennis_finals_labels_accept_stages_and_keep_atp_and_wta_separate(): void
    {
        foreach (['ATP Finals Turin 2026', 'ATP Finals Turin 2026 - Round Robin', 'ATP Finals - Turin (Italy) - Semi-finals', 'ATP - Singles: Finals - Torino (World) - Play Offs, hard', 'ATP Finals - Turin (World) - Round Robin', 'ATP Finals - Turin - Group A'] as $label) {
            $this->assertSame(['Year-End Championships', 'ATP Finals'], TennisSyncController::pinnacleCompetition($label));
        }
        $this->assertSame(['Year-End Championships', 'WTA Finals'], TennisSyncController::pinnacleCompetition('WTA - Singles: Finals - Indian Wells (World) - Play Offs, hard'));
        $this->assertNull(TennisSyncController::pinnacleCompetition('WTA Finals - Turin - Play Offs'));
        $this->assertSame(['Year-End Championships', 'ATP Finals'], TennisSyncController::tennisCompetition(['tournament' => 'ATP Finals Turin 2026', 'sport' => 'tennis']));
        $this->assertNull(TennisSyncController::tennisCompetition(['tournament' => 'ATP Finals Turin 2026', 'tournament_tour' => 'WTA']));
    }

    #[TestWith(['ATP - SINGLES: Finals - Turin (World), hard (indoor)'])]
    #[TestWith(['ATP - SINGLES: Finals - Turin (World), hard (indoor) (Tennis)'])]
    #[TestWith(['Finals - Turin (World)'])]
    #[TestWith(['Finals - Turin'])]
    #[TestWith(['Finals - Torino (World)'])]
    #[TestWith(['Finals - Turin (World) - Round Robin'])]
    public function test_turin_finals_provider_labels_admit_live_and_prematch_singles_without_admitting_wta(string $label): void
    {
        $this->assertSame(['Year-End Championships', 'ATP Finals'], TennisSyncController::pinnacleCompetition($label));
        foreach (['Live', 'Prematch'] as $status) {
            $this->assertSame(['Year-End Championships', 'ATP Finals'], TennisSyncController::tennisCompetition([
                'tournament' => $label, 'sport' => 'tennis', 'result_status' => $status, 'tournament_tour' => 'ATP - Singles',
            ]));
        }
        $this->assertNull(TennisSyncController::tennisCompetition(['tournament' => $label, 'tournament_tour' => 'WTA - Singles']));
    }

    public function test_betfair_only_tennis_fixture_is_queued_and_persisted_from_individual_cache_without_a_sports_api_id(): void
    {
        Bus::fake();
        config(['cache.stores.redis.driver' => 'array']);
        $start = time() + 60;
        $key = 'fils|jodar|'.$start;
        $runner = ['marketId' => 'tennis-main', 'marketType' => 'MATCH_ODDS', 'runnerId' => '2', 'runner' => 'Jodar', 'currentBackOdds' => 1.98];
        $match = ['unique_key' => $key, 'betfair_match_id' => '36313130', 'team1' => 'Fils', 'team2' => 'Jodar',
            'tournament' => 'ATP Finals Turin 2026', 'timestamp' => $start, 'result_status' => 'Prematch',
            'data_sync_complete' => false, 'markets' => ['tennis-main' => [$runner]]];
        $cache = \Mockery::mock(TennisMatchCache::class);
        $cache->shouldReceive('active')->andReturnTrue();
        $cache->shouldReceive('read')->with(null, [$key], null)->times(3)->andReturn([$key => $match]);
        $this->app->instance(TennisMatchCache::class, $cache);
        Redis::shouldReceive('zrangebyscore')->with('Tennis:matches:cleaned', \Mockery::any(), '+inf')->andReturn([]);
        $method = new ReflectionMethod(TennisSyncController::class, 'persistUpdatedMatches');

        $method->invoke(app(TennisSyncController::class), [$key => $match]);
        $method->invoke(app(TennisSyncController::class), [$key => $match]);
        Bus::assertDispatched(CallQueuedClosure::class, 1);
        $job = Bus::dispatched(CallQueuedClosure::class)->sole();
        $this->assertSame('realtime-tennis', $job->queue);
        ($job->closure->getClosure())($job);

        $this->assertDatabaseHas('tennis_matches', ['unique_key' => $key, 'betfair_match_id' => '36313130', 'sports_api_pro_match_id' => null, 'data_sync_complete' => false]);
        $this->assertDatabaseHas('tennis_markets', ['tennis_match_unique_key' => $key, 'runner' => 'Jodar', 'current_back_odds' => 1.98]);
        Http::assertNothingSent();
    }

    #[TestWith(['soccer', 'football'])]
    #[TestWith(['tennis', 'tennis'])]
    #[TestWith(['basketball', 'basketball'])]
    #[TestWith(['ice-hockey', 'ice_hockey'])]
    #[TestWith(['american-football', 'american_football'])]
    #[TestWith(['baseball', 'baseball'])]
    #[TestWith(['cricket', 'cricket'])]
    public function test_archive_compares_existing_local_betfair_quotes_across_supported_sports(string $sport, string $table): void
    {
        $this->freezeTime();
        config(['services.telegram.enabled' => false]);
        Redis::shouldReceive('get')->never();
        if ($sport === 'tennis') {
            foreach (range(1, 27) as $index) {
                DB::table('tennis_matches')->insert(['unique_key' => 'other-'.$index, 'betfair_match_id' => (string) (1000 + $index),
                    'team1' => 'Unrelated Player '.$index, 'team2' => 'Other Player '.$index, 'result_status' => 'Prematch',
                    'start_timestamp' => now()->addHour()->timestamp, 'data_sync_complete' => true, 'payload' => '{}']);
            }
        }
        DB::table($table.'_matches')->insert(['unique_key' => 'archive-fixture', 'betfair_match_id' => '900', 'sports_api_pro_match_id' => '100',
            'team1' => 'Home Club', 'team2' => 'Away Club', 'tournament' => 'League', 'result_status' => 'Prematch',
            'start_timestamp' => now()->addHour()->timestamp, 'data_sync_complete' => true, 'payload' => '{}']);
        DB::table($table.'_markets')->insert(['selection_key' => hash('sha256', 'quote'), $table.'_match_unique_key' => 'archive-fixture',
            'market_id' => 'main', 'market_type' => 'MATCH_ODDS', 'payload' => json_encode(['marketType' => 'MATCH_ODDS',
                'runner' => 'Home Club', 'currentBackOdds' => 2.4, 'odds_history_processed_at' => now()->timestamp - 1])]);
        $drop = ['id' => 100, 'sport' => $sport, 'home' => 'Home Club', 'away' => 'Away Club', 'sect' => 'Moneyline', 'outcome' => 'Home',
            'from_price' => 2, 'to_price' => 1.8, 'alerted' => now()->timestamp, 'starts' => now()->addHour()->timestamp, 'alert_key' => str_repeat('a', 64)];

        (new ProcessAlert('pinnacle', ['alerts' => [$drop]]))->handle(app(AlertService::class));

        $this->assertDatabaseHas('pinnacle_drop_archives', ['sport' => $sport, 'betfair_higher' => true]);
        Http::assertNothingSent();
    }

    #[TestWith([null, 'OPEN', false])]
    #[TestWith([10, 'OPEN', false])]
    #[TestWith([-10, 'SUSPENDED', false])]
    #[TestWith([-10, 'OPEN', true])]
    public function test_archive_does_not_invent_comparisons_for_unverified_future_suspended_or_ambiguous_quotes(?int $offset, string $status, bool $duplicate): void
    {
        $this->freezeTime();
        config(['services.telegram.enabled' => false]);
        $fixture = $this->fixture(['result_status' => 'Prematch', 'timestamp' => now()->addHour()->timestamp]);
        $fixture->forceFill(['betfair_match_id' => '900', 'data_sync_complete' => true])->save();
        foreach ($duplicate ? ['one', 'two'] : ['one'] as $key) {
            FootballMarket::query()->create(['selection_key' => hash('sha256', $key), 'football_match_unique_key' => $fixture->unique_key,
                'market_id' => $key, 'market_type' => 'MATCH_ODDS', 'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => 'Torino',
                    'currentBackOdds' => 5, 'market_status' => $status, 'odds_history_processed_at' => $offset === null ? null : now()->timestamp + $offset]]);
        }
        $drop = ['id' => 100, 'sport' => 'soccer', 'home' => 'Torino', 'away' => 'Monza 1912', 'sect' => 'Moneyline', 'outcome' => 'Home',
            'from_price' => 2, 'to_price' => 1.8, 'alerted' => now()->timestamp, 'starts' => now()->addHour()->timestamp, 'alert_key' => str_repeat('a', 64)];

        (new ProcessAlert('pinnacle', ['alerts' => [$drop]]))->handle(app(AlertService::class));

        $this->assertDatabaseHas('pinnacle_drop_archives', ['betfair_higher' => null]);
        Http::assertNothingSent();
    }

    public function test_pinnacle_archive_cleanup_uses_retention_instead_of_deleting_on_day_rollover(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-11-08 02:00:00 UTC'));
        $base = ['sport' => 'soccer', 'is_live' => false, 'market_group' => 0, 'payload' => '{}'];
        DB::table('pinnacle_drop_archives')->insert([
            $base + ['alert_key' => str_repeat('a', 64), 'alerted_at' => now()->timestamp - 1],
            $base + ['alert_key' => str_repeat('b', 64), 'alerted_at' => now()->timestamp],
        ]);
        $this->artisan('pinnacle:archive-maintain')->assertSuccessful();
        $this->assertDatabaseCount('pinnacle_drop_archives', 2);
        $this->travel(1799)->seconds();
        $this->artisan('pinnacle:archive-maintain')->assertSuccessful();
        $this->assertDatabaseCount('pinnacle_drop_archives', 1);
        $this->assertDatabaseHas('pinnacle_drop_archives', ['alert_key' => str_repeat('b', 64)]);
        $this->travel(1)->seconds();
        $this->artisan('pinnacle:archive-maintain')->assertSuccessful();
        $this->assertDatabaseCount('pinnacle_drop_archives', 0);
        $this->assertDatabaseCount('pinnacle_daily_statistics', 2);
    }

    public function test_unmatched_soccer_total_drop_preserves_its_exact_market_line_and_kickoff(): void
    {
        $starts = time() + 3600;
        $id = app(AlertService::class)->process('prematch_drops', [], ['pinnacle' => [
            'id' => 101, 'home' => 'Brentford', 'away' => 'Wolverhampton', 'league' => 'Premier League 2 U21', 'sport' => 'soccer',
            'sect' => 'Total', 'outcome' => 'Over', 'point' => 4.5, 'from_price' => 2.72, 'to_price' => 2.57,
            'alert_key' => 'soccer-total-4.5', 'alerted' => time(), 'starts' => $starts * 1000,
        ]]);

        $this->assertNotNull($id);
        $payload = json_decode(DB::table('alerts')->find($id)->payload, true);
        $this->assertSame(date('c', $starts), $payload['kickoff']);
        $this->assertSame(4.5, $payload['context']['movement']['point']);
        $text = app(AlertService::class)->text($payload);
        $this->assertStringContainsString('Market: Total 4.5 (Over)', $text);
        $this->assertStringContainsString('2.72 📉 2.57', $text);
        $this->assertDatabaseCount('football_matches', 0);
        Http::assertNothingSent();
    }

    public function test_delivery_rechecks_expiry_disconnect_and_preferences_before_sending(): void
    {
        $match = $this->fixture();
        foreach (['expired', 'disconnected', 'other_filter_selected'] as $reason) {
            $customer = $this->customer();
            $id = app(AlertService::class)->process('ltd', $match->payload, ['draw_lay' => 2.25]);
            $delivery = $this->delivery($id, $customer);
            if (! $delivery) {
                DB::table('alerts')->where('id', $id)->update(['fanned_out_at' => null]);
                $delivery = $this->delivery($id, $customer);
            }
            $customer->forceFill(match ($reason) {
                'expired' => ['trial_ends_at' => now()->subDay()], 'disconnected' => ['telegram_available' => false, 'telegram_chat_id' => null], default => ['tg_ltd' => false, 'tg_ht00' => true]
            })->save();
            app(AlertService::class)->deliver($delivery);
            $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'skipped']);
        }
        Http::assertNothingSent();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_betfair_updates_cached_partial_football_fixtures_without_requiring_a_pinnacle_mapping(bool $hasPinnacleMapping): void
    {
        config(['services.football_fixture_index' => false, 'services.football_match_cache' => false, 'cache.stores.redis.driver' => 'array']);
        $fixture = $this->fixture(['tournament' => 'Premier League', 'tournament_country' => 'England',
            'result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'timestamp' => time() + 3600]);
        $fixture->forceFill(['betfair_match_id' => '900', 'sports_api_pro_match_id' => null, 'data_sync_complete' => false])->save();
        if ($hasPinnacleMapping) {
            DB::table('pinnacle_betfair_events')->insert(['sport' => 'soccer', 'pinnacle_event_id' => '100', 'betfair_event_id' => '900']);
        }
        $match = array_replace($fixture->payload, ['betfair_match_id' => '900', 'sportsApiPro_match_id' => null,
            'data_sync_complete' => false, 'markets' => []]);
        $cache = [$fixture->unique_key => $match];
        Redis::shouldReceive('get')->andReturnUsing(static fn (string $key): ?string => $key === 'Football:matches' ? json_encode($cache) : null);
        Redis::shouldReceive('set')->with('Football:matches', \Mockery::type('string'))->once()->andReturnUsing(function (string $key, string $value) use (&$cache): bool {
            $cache = json_decode($value, true);

            return true;
        });
        Redis::shouldReceive('expire')->andReturnTrue();
        Redis::shouldReceive('zrangebyscore')->andReturn([]);
        $session = microtime(true);
        Cache::store('redis')->put('Betfair:racing:connection', ['state' => 'connected', 'at' => time(), 'session' => $session]);
        Cache::store('redis')->put('Betfair:racing:feed:prematch', ['state' => 'live', 'at' => time()]);
        $runner = ['marketId' => 'main', 'marketType' => 'MATCH_ODDS', 'runnerId' => '1', 'runner' => 'Torino',
            'backOdds' => 2.28, 'backSize' => 85.16, 'layOdds' => 2.3, 'laySize' => 321.2,
            'market_status' => 'OPEN', 'liquidity_session' => $session, 'liquidity_feed' => 'prematch'];
        $firstHalf = array_replace($runner, ['marketId' => 'fh-goals', 'marketType' => 'FIRST_HALF_GOALS_05',
            'runnerId' => '2', 'runner' => 'Over 0.5 Goals', 'backOdds' => 1.49]);

        $updated = (new ReflectionMethod(ListenBetfairOdds::class, 'updateFootballCache'))->invoke(
            app(ListenBetfairOdds::class), ['900' => ['markets' => [$runner, $firstHalf]]], false,
        );

        $this->assertTrue($updated);
        $stored = array_values($cache[$fixture->unique_key]['markets']['main_MATCH_ODDS'])[0];
        $this->assertSame(2.28, $stored['backOdds']);
        $this->assertSame(85.16, $stored['backSize']);
        $this->assertSame($session, $stored['liquidity_session']);
        $this->assertSame('prematch', $stored['liquidity_feed']);
        $halfQuote = array_values($cache[$fixture->unique_key]['markets']['fh-goals_FIRST_HALF_GOALS_05'])[0];
        $this->assertSame(1.49, $halfQuote['backOdds']);
        $this->assertSame($session, $halfQuote['liquidity_session']);
        $this->assertFalse($cache[$fixture->unique_key]['data_sync_complete']);
        $indicator = prepareMatchOddsMonitoring($cache[$fixture->unique_key], true)['market_indicators']['main_MATCH_ODDS'];
        $this->assertFalse($indicator['stale']);
        $this->assertSame(79.0, round($indicator['favorite_back_percent'], 1));
    }

    /** @return list<FootballMarket> */
    private function betfairMatchOddsBook(FootballMatch $fixture): array
    {
        $fixture->forceFill(['betfair_match_id' => 'bf-12345'])->save();
        $rows = [];
        foreach (['Torino' => 2.4, 'The Draw' => 3.2, 'Monza 1912' => 4.0] as $name => $price) {
            $rows[] = FootballMarket::query()->create([
                'selection_key' => hash('sha256', $fixture->unique_key.$name), 'football_match_unique_key' => $fixture->unique_key,
                'market_id' => 'bf-full-time', 'market_type' => 'MATCH_ODDS', 'runner' => $name,
                'payload' => ['marketType' => 'MATCH_ODDS', 'runner' => $name, 'backOdds' => $price, 'backSize' => 50,
                    'currentBackOdds' => 1.1, 'lastTradedPrice' => 1.14, 'liquidity_feed' => 'live',
                    'liquidity_updated_at' => microtime(true), 'market_status' => 'OPEN'],
            ]);
        }

        return $rows;
    }

    #[TestWith(['ht00'])]
    #[TestWith(['00_70'])]
    public function test_telegram_match_odds_use_latest_betfair_best_back_and_ignore_trigger_snapshot_and_other_markets(string $type): void
    {
        $fixture = $this->fixture(['minutes_status' => 'Half Time']);
        $rows = $this->betfairMatchOddsBook($fixture);
        $customer = $this->customer(['tg_'.$type => true]);
        $payload = $fixture->payload;
        if ($type === '00_70') {
            $payload['minutes_status'] = '2nd half';
            $payload['current_period_start_timestamp'] = time() - 25 * 60;
        }
        $payload['markets']['MATCH_ODDS'] = [['runner' => 'Torino', 'currentBackOdds' => 1.1]];
        $id = app(AlertService::class)->process($type, $payload);
        $this->assertNotNull($id);
        $delivery = $this->delivery($id, $customer);
        $rows[0]->update(['payload' => array_replace($rows[0]->payload, ['backOdds' => 2.5])]);
        FootballMarket::query()->create(['selection_key' => hash('sha256', 'over-under-noise'),
            'football_match_unique_key' => $fixture->unique_key, 'market_id' => 'totals', 'market_type' => 'OVER_UNDER_25',
            'payload' => ['runner' => 'Torino', 'backOdds' => 1.1]]);
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);

        $this->assertSame(0, app(AlertService::class)->deliver($delivery));
        $this->assertSame(0, app(AlertService::class)->deliver($delivery));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => str_contains($request['text'], "• Home: 2.50\n• Draw: 3.20\n• Away: 4.00")
            && ! str_contains($request['text'], '1.10') && ! str_contains($request['text'], '1.14'));
        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'sent']);
    }

    #[TestWith(['all_low'])]
    #[TestWith(['missing'])]
    #[TestWith(['stale'])]
    #[TestWith(['suspended'])]
    #[TestWith(['empty_size'])]
    #[TestWith(['wrong_feed'])]
    #[TestWith(['wrong_market'])]
    #[TestWith(['unknown_runner'])]
    #[TestWith(['future'])]
    #[TestWith(['split_book'])]
    #[TestWith(['unlinked'])]
    #[TestWith(['score_changed'])]
    public function test_telegram_match_odds_wait_for_a_valid_betfair_tick_and_then_send_once(string $reason): void
    {
        $fixture = $this->fixture(['minutes_status' => 'Half Time']);
        $rows = $this->betfairMatchOddsBook($fixture);
        $customer = $this->customer(['tg_ht00' => true]);
        $id = app(AlertService::class)->process('ht00', $fixture->payload);
        $delivery = $this->delivery($id, $customer);
        $original = $rows[0]->payload;
        if ($reason === 'all_low') {
            foreach ($rows as $row) {
                $row->update(['payload' => array_replace($row->payload, ['backOdds' => 1.1])]);
            }
        } elseif ($reason === 'split_book') {
            $rows[0]->update(['market_id' => 'other-book']);
        } elseif ($reason === 'unlinked') {
            $fixture->update(['betfair_match_id' => null]);
        } elseif ($reason === 'score_changed') {
            $fixture->update(['payload' => array_replace($fixture->payload, ['home_score' => 1])]);
        } else {
            $changes = match ($reason) {
                'missing' => ['backOdds' => null], 'stale' => ['liquidity_updated_at' => microtime(true) - 76],
                'suspended' => ['market_status' => 'SUSPENDED'], 'empty_size' => ['backSize' => 0],
                'wrong_feed' => ['liquidity_feed' => 'prematch'], 'wrong_market' => ['marketType' => 'HALF_TIME'],
                'unknown_runner' => ['runner' => 'Over 2.5 Goals'], 'future' => ['liquidity_updated_at' => microtime(true) + 60],
            };
            $rows[0]->update(['payload' => array_replace($original, $changes)]);
        }

        $this->assertSame(5, app(AlertService::class)->deliver($delivery));
        Http::assertNothingSent();
        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'retry', 'error' => 'Waiting for fresh valid Betfair Match Odds (1X2).']);

        $fixture->update(['betfair_match_id' => 'bf-12345', 'payload' => array_replace($fixture->payload, ['home_score' => 0])]);
        foreach ($rows as $index => $row) {
            $row->update(['market_id' => 'bf-full-time', 'payload' => array_replace($index === 0 ? $original : $row->payload,
                ['backOdds' => [2.4, 3.2, 4.0][$index], 'liquidity_updated_at' => microtime(true)])]);
        }
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
        $this->assertSame(0, app(AlertService::class)->deliver($delivery));
        $this->assertSame(0, app(AlertService::class)->deliver($delivery));
        Http::assertSentCount(1);
        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'sent']);
    }

    public function test_telegram_match_odds_do_not_send_an_old_alert_when_a_valid_quote_never_arrives(): void
    {
        $fixture = $this->fixture(['minutes_status' => 'Half Time']);
        $customer = $this->customer(['tg_ht00' => true]);
        $id = app(AlertService::class)->process('ht00', $fixture->payload);
        $delivery = $this->delivery($id, $customer);
        DB::table('alerts')->where('id', $id)->update(['created_at' => now()->subSeconds(121)]);

        $this->assertSame(0, app(AlertService::class)->deliver($delivery));

        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'skipped', 'error' => 'Fresh valid Betfair Match Odds (1X2) unavailable.']);
        Http::assertNothingSent();
    }

    public function test_telegram_sends_english_protected_content_once_and_handles_blocked_bots(): void
    {
        $match = $this->fixture();
        $customer = $this->customer();
        $id = app(AlertService::class)->source($this->source());
        $delivery = $this->delivery($id, $customer);
        $responses = Http::sequence()->push(['ok' => true, 'result' => ['message_id' => 42]])->push(['ok' => false, 'error_code' => 403], 403);
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => $responses]);
        app(AlertService::class)->deliver($delivery);
        app(AlertService::class)->deliver($delivery);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['protect_content'] === true && str_contains($request['text'], AlertService::STRATEGY['en']) && ! str_contains($request['text'], AlertService::STRATEGY['ka']));
        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'sent', 'remote_id' => '42']);
        RateLimiter::clear('alerts:telegram:global');
        RateLimiter::clear('alerts:telegram:chat:'.$customer->id);
        $test = app(AlertService::class)->process('test', [], ['request_id' => (string) Str::uuid()], $customer->id);
        $testDelivery = $this->delivery($test, $customer);
        app(AlertService::class)->deliver($testDelivery);
        $this->assertFalse($customer->fresh()->telegram_available);
        $this->assertTrue($customer->fresh()->tg_ltd);

        foreach (['prematch_drops', 'market_shock'] as $type) {
            $recipient = $this->customer(['tg_'.$type => true]);
            $payload = ['alert_type' => $type, 'context' => ['movement' => ['market' => 'moneyline']]];
            $queued = DB::table('alerts')->insertGetId(['event_key' => (string) Str::uuid(), 'type' => $type,
                'match_id' => '12345', 'payload' => json_encode($payload), 'created_at' => now()]);
            $this->assertSame(0, $this->delivery($queued, $recipient));
            $pending = DB::table('alert_deliveries')->insertGetId([
                'alert_id' => $queued, 'customer_id' => $recipient->id, 'channel' => 'telegram',
                'destination' => $recipient->telegram_chat_id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);

            app(AlertService::class)->deliver($pending);
            $this->assertDatabaseHas('alert_deliveries', ['id' => $pending, 'status' => 'skipped']);
        }
        Http::assertSentCount(2);
    }

    public function test_rate_limit_retries_and_unknown_outcomes_do_not_duplicate_notifications(): void
    {
        $match = $this->fixture();
        $customer = $this->customer();
        $id = app(AlertService::class)->source($this->source());
        $delivery = $this->delivery($id, $customer);
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::response(['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 17]], 429)]);
        $this->assertSame(17, app(AlertService::class)->deliver($delivery));
        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'retry']);
        RateLimiter::clear('alerts:telegram:global');
        RateLimiter::clear('alerts:telegram:chat:'.$customer->id);
        Cache::forget('alerts:telegram:paused_until');
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::failedConnection()]);
        app(AlertService::class)->deliver($delivery);
        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'unknown']);
        $this->assertSame(0, app(AlertService::class)->deliver($delivery));
    }

    public function test_paid_alerts_continue_through_georgian_expiry_day_before_expiration_notice(): void
    {
        config(['services.subscriptions.expiry_timezone' => 'Asia/Tbilisi']);
        $this->travelTo(Carbon::parse('2026-11-18T19:59:59Z'));
        $this->fixture();
        $customer = $this->customer(['trial_ends_at' => now()->subDay(), 'subscription_starts_at' => now()->subDay(),
            'subscription_ends_at' => '2026-11-18 10:00:00']);
        $service = app(AlertService::class);
        $id = $service->source($this->source());
        $this->assertNotNull($id);

        $service->fanout($id);
        $service->scan();

        $this->assertTrue($service->eligible($customer));
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $customer->id, 'channel' => 'telegram']);
        $this->assertDatabaseMissing('alerts', ['type' => 'expired']);

        $this->travel(1)->seconds();
        $service->scan();

        $this->assertFalse($service->eligible($customer->fresh()));
        $this->assertDatabaseHas('alerts', ['type' => 'expired']);
    }

    public function test_expiration_message_is_once_per_access_period_and_lifetime_access_is_preserved(): void
    {
        $expired = $this->customer(['trial_ends_at' => now()->subDay()]);
        $lifetime = $this->customer(['trial_ends_at' => now()->subDay(), 'admin_free_days' => -1]);
        app(AlertService::class)->scan();
        app(AlertService::class)->scan();
        $this->assertDatabaseCount('alerts', 1);
        $this->assertSame('expired', DB::table('alerts')->first()->type);
        $this->assertTrue(app(AlertService::class)->eligible($lifetime));
        $expired->forceFill(['subscription_starts_at' => now()->subMinute(), 'subscription_ends_at' => now()->addMonth()])->save();
        $this->assertTrue(app(AlertService::class)->eligible($expired));
        $id = (int) DB::table('alerts')->value('id');
        $delivery = $this->delivery($id, $expired);
        app(AlertService::class)->deliver($delivery);
        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'skipped']);
        Http::assertNothingSent();
    }

    public function test_test_notification_requires_connection_and_access_and_goes_through_queue(): void
    {
        $customer = $this->customer(['telegram_available' => false]);
        $this->actingAs($customer, 'customers')->postJson('/notifications/telegram/test', ['request_id' => (string) Str::uuid()])->assertUnprocessable();
        $customer->forceFill(['telegram_available' => true])->save();
        $this->actingAs($customer->fresh(), 'customers')->postJson('/notifications/telegram/test', ['request_id' => (string) Str::uuid()])->assertAccepted();
        Bus::assertDispatched(ProcessAlert::class, fn ($job) => $job->task === 'fanout');
        Http::assertNothingSent();
    }

    public function test_private_broadcast_channel_rejects_other_customers_and_guests(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        require base_path('routes/channels.php');
        $customer = $this->customer();
        $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-customers.'.$customer->id.'.alerts'])->assertForbidden();
        $this->actingAs($customer, 'customers')->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-customers.999.alerts'])->assertForbidden();
        $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-customers.'.$customer->id.'.alerts'])->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_existing_five_filter_predicates_create_only_qualifying_alerts(): void
    {
        $base = $this->fixture()->payload;
        $alerts = app(AlertService::class);
        $this->assertNotNull($alerts->process('ht00', [...$base, 'minutes_status' => 'Half Time']));
        $this->assertNull($alerts->process('ht00', [...$base, 'minutes_status' => 'Half Time', 'home_score' => 1]));
        $this->assertNotNull($alerts->process('00_70', [...$base, 'minutes_status' => '2nd half', 'current_period_start_timestamp' => time() - 25 * 60]));
        $this->assertNull($alerts->process('00_70', $base));
        $losing = [...$base, 'home_score' => 0, 'away_score' => 1, 'bet365_prematch' => ['favorite_side' => 'home', 'favorite_odds' => 1.35]];
        config(['cache.stores.redis.driver' => 'array']);
        $snapshot = ['provider_id' => 1, 'score' => '0 - 1', 'updated_at' => microtime(true), 'markets' => [[
            'market_id' => 1, 'period' => 'Full-time', 'suspended' => false,
            'choices' => [['name' => '1', 'decimal_odds' => 17.5, 'quote_score' => '0 - 2']],
        ]]];
        Cache::store('redis')->put('Football:bet365:live:12345', $snapshot);
        $this->assertNull($alerts->process('fav_losing', $losing));
        $snapshot['markets'][0]['choices'][0] = ['name' => '1', 'decimal_odds' => 6.8, 'quote_score' => '0 - 1'];
        $snapshot['markets'][0]['suspended'] = true;
        Cache::store('redis')->put('Football:bet365:live:12345', $snapshot);
        $this->assertNull($alerts->process('fav_losing', $losing));
        $snapshot['markets'][0]['suspended'] = false;
        Cache::store('redis')->put('Football:bet365:live:12345', $snapshot);
        $alertId = $alerts->process('fav_losing', $losing);
        $this->assertNotNull($alertId);
        $payload = json_decode(DB::table('alerts')->find($alertId)->payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Current Live Odds: 6.8', $alerts->text($payload));
        $customer = $this->customer(['tg_fav_losing' => true]);
        $alerts->fanout($alertId);
        $delivery = $this->delivery($alertId, $customer);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]])]);
        $snapshot['markets'][0]['suspended'] = true;
        Cache::store('redis')->put('Football:bet365:live:12345', $snapshot);
        $this->assertSame(5, $alerts->deliver($delivery));
        Http::assertNothingSent();
        $snapshot['markets'][0]['suspended'] = false;
        $snapshot['markets'][0]['choices'][0]['decimal_odds'] = 7.2;
        Cache::store('redis')->put('Football:bet365:live:12345', $snapshot);
        $this->assertSame(0, $alerts->deliver($delivery));
        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'Current Live Odds: 7.2'));
        $this->assertNull($alerts->process('fav_losing', [...$losing, 'home_score' => 1]));
        $runner = ['runner' => 'Torino', 'marketType' => 'MATCH_ODDS', 'monitoring_baseline_version' => 1, 'initialBackOdds' => 4.0,
            'initialBackOddsTimestamp' => time() - 60, 'opening_odds_source' => 'first_observed', 'currentBackOdds' => 3.5,
            'prematch_drop_baseline_odds' => 4.0, 'prematch_drop_triggered' => true, 'prematch_drop_detected_at' => time(), 'market_shock_detected_at' => time()];
        $prematch = [...$base, 'result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'timestamp' => time() + 600, 'markets' => ['MATCH_ODDS' => [$runner]]];
        $drop = $alerts->process('prematch_drops', $prematch);
        $shock = $alerts->process('market_shock', $prematch);
        $this->assertNotNull($drop);
        $this->assertNotNull($shock);
        $text = $alerts->text(json_decode(DB::table('alerts')->find($shock)->payload, true));
        $this->assertStringContainsString('4.00 📉 3.50 (-12.5% Drop)', $text);
        $this->assertStringContainsString('Volume Surge: No data available', $text);
        $this->assertSame($drop, $alerts->process('prematch_drops', $prematch));
        $this->assertSame($shock, $alerts->process('market_shock', $prematch));
        $this->assertDatabaseCount('alerts', 5);

        $pinnacle = [...$prematch, 'markets' => [], 'pinnacle_drop_triggered' => true, 'pinnacle_market_shock_detected_at' => time(),
            'pinnacle_markets' => [
                ['sect' => 'Total', 'outcome' => 'Over', 'point' => 2.5, 'period' => 0, 'drop_triggered' => true, 'shock_detected_at' => time(), 'initialBackOddsTimestamp' => time(), 'from_price' => 4, 'current_odds' => 3.5],
                ['sect' => 'Asian Handicap', 'outcome' => 'Home', 'point' => -1.5, 'period' => 0, 'drop_triggered' => true, 'shock_detected_at' => time(), 'initialBackOddsTimestamp' => time(), 'from_price' => 4, 'current_odds' => 3.5],
                ['sect' => 'Moneyline', 'outcome' => 'Home', 'period' => 1, 'drop_triggered' => true, 'shock_detected_at' => time(), 'initialBackOddsTimestamp' => time(), 'from_price' => 4, 'current_odds' => 3.5],
            ]];
        foreach (['prematch_drops', 'market_shock'] as $type) {
            $id = $alerts->process($type, $pinnacle);
            $this->assertNotNull($id);
            $movement = json_decode(DB::table('alerts')->find($id)->payload, true)['context']['movement'];
            $this->assertSame('Total', $movement['market']);
            $this->assertSame(2.5, $movement['point']);
            $this->assertSame(0, $movement['period']);
        }
        $this->assertDatabaseCount('alerts', 7);

        foreach (['prematch_drops', 'market_shock'] as $type) {
            foreach ([
                ['moneyline', null, 0, true],
                ['Money_Line', null, 0, true],
                ['MATCH_ODDS', null, 0, true],
                ['1X2', null, 0, true],
                ['BTTS', null, 0, true],
                ['BOTH_TEAMS_TO_SCORE', null, 0, true],
                ['Total', 0.5, 0, true],
                ['Total', 1.5, 0, true],
                ['Total', 2.5, 0, true],
                ['Total', 3.5, 0, true],
                ['Total', 4.5, 0, true],
                ['OVER_UNDER_25', null, 0, true],
                ['Total', 2.75, 0, false],
                ['Total', 5.5, 0, false],
                ['Total', null, 0, false],
                ['Asian Handicap', -1.5, 0, false],
                ['CORRECT_SCORE', null, 0, false],
                ['HALF_TIME', null, 0, false],
                ['Moneyline', null, 1, false],
                ['Total', 2.5, 1, false],
                ['BTTS', null, 1, false],
            ] as $index => [$market, $point, $period, $expected]) {
                $movement = ['market' => $market, 'selection' => 'Selection '.$index, 'point' => $point, 'period' => $period, 'from' => 4, 'to' => 3.5, 'at' => time() + $index];
                $id = $alerts->process($type, $prematch, ['movement' => $movement]);
                $this->assertSame($expected, $id !== null, $type.' '.$market.' period '.$period);
            }
        }
    }

    public function test_popup_polling_recovers_ltd_and_goal_without_websocket_or_telegram_connection(): void
    {
        $this->fixture();
        $customer = $this->customer(['telegram_chat_id' => null, 'telegram_available' => false, 'tg_ltd' => false]);
        $parent = app(AlertService::class)->source($this->source());
        $goal = app(AlertService::class)->source($this->source("GOAL! Torino 0-1 Monza 1912\n1st Half - 33:38"));
        $this->assertNotNull($goal);
        config(['services.telegram.enabled' => false]);
        $this->actingAs($customer, 'customers');
        $url = '/notifications/popups?after=0&since='.now()->subSeconds(30)->timestamp;
        $response = $this->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(2, 'alerts')->assertJsonPath('alerts.0.alert_id', (string) $parent)
            ->assertJsonPath('alerts.1.alert_type', 'goal')->assertJsonPath('cursor', $goal)
            ->assertJsonPath('alerts.0.templates.ka.strategy_label', AlertService::STRATEGY['ka'])
            ->assertJsonPath('alerts.0.templates.en.strategy_label', AlertService::STRATEGY['en'])
            ->assertJsonPath('alerts.0.url', '/football/market-activity?match=12345');
        $this->assertArrayNotHasKey('context', $response->json('alerts.0'));
        $this->getJson('/notifications/popups?after='.$goal.'&since=0')->assertOk()->assertJsonCount(0, 'alerts');
        Http::assertNothingSent();
    }

    public function test_popup_polling_excludes_old_alerts_and_respects_mute_and_access(): void
    {
        $this->fixture();
        $customer = $this->customer();
        $id = app(AlertService::class)->source($this->source());
        $this->actingAs($customer, 'customers');
        $url = '/notifications/popups?after=0&since=0';
        DB::table('alerts')->where('id', $id)->update(['created_at' => now()->subSeconds(91)]);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'alerts');
        DB::table('alerts')->where('id', $id)->update(['created_at' => now()]);
        $customer->forceFill(['popup_enabled' => false])->save();
        $this->getJson($url)->assertOk()->assertJsonPath('popup_enabled', false)->assertJsonPath('cursor', $id)->assertJsonCount(0, 'alerts');
        $customer->forceFill(['popup_enabled' => true, 'is_blocked' => true])->save();
        $this->getJson($url)->assertForbidden();
    }

    public function test_source_webhook_authenticates_and_queues_a_signal_for_the_ltd_worker(): void
    {
        $this->fixture();
        $this->postJson('/notifications/telegram/source', $this->source())->assertForbidden();
        Bus::assertNotDispatched(ProcessAlert::class);
        $this->postJson('/notifications/telegram/source', $this->source(), ['X-BF-Source-Secret' => 'test-source'])->assertAccepted();
        Bus::assertDispatched(ProcessAlert::class, function (ProcessAlert $job): bool {
            if ($job->task !== 'source' || $job->queue !== 'alerts-ltd') {
                return false;
            }
            $job->handle(app(AlertService::class));

            return true;
        });
        $this->assertDatabaseHas('alerts', ['type' => 'ltd', 'match_id' => '12345']);
        $this->assertDatabaseCount('alerts', 1);
    }

    public function test_source_check_rejects_login_or_missing_source_option(): void
    {
        $this->artisan('telegram:listen', ['--check' => true])->assertExitCode(1);
        $this->artisan('telegram:listen', ['--source' => true, '--check' => true, '--login' => true])->assertExitCode(1);
    }

    public function test_popup_polling_requires_login_and_valid_cursor(): void
    {
        $this->getJson('/notifications/popups?after=0&since=0')->assertUnauthorized();
        $this->actingAs($this->customer(), 'customers');
        $this->getJson('/notifications/popups?after=-1&since=invalid')->assertUnprocessable()->assertJsonValidationErrors(['after', 'since']);
    }

    public function test_platform_broadcast_contains_both_languages_and_no_telegram_credentials(): void
    {
        $this->fixture();
        $customer = $this->customer(['tg_ltd' => false, 'telegram_chat_id' => null]);
        $id = app(AlertService::class)->source($this->source());
        $delivery = $this->delivery($id, $customer, 'platform');
        app(AlertService::class)->deliver($delivery);
        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'sent']);
        Event::assertDispatched(AnonymousEvent::class, function ($event) use ($customer): bool {
            $payload = $event->broadcastWith();

            return $event->broadcastAs() === 'alert.received'
                && $event->broadcastOn()[0]->name === 'private-customers.'.$customer->id.'.alerts'
                && $payload['templates']['ka']['strategy_label'] === AlertService::STRATEGY['ka']
                && $payload['templates']['en']['strategy_label'] === AlertService::STRATEGY['en']
                && $payload['url'] === '/football/market-activity?match=12345'
                && ! isset($payload['telegram_chat_id'], $payload['bot_token']);
        });
        Http::assertNothingSent();
    }

    public function test_reconnect_restores_saved_filters_and_plain_start_recovers_blocked_delivery(): void
    {
        $customer = $this->customer(['telegram_chat_id' => '555', 'telegram_available' => false, 'tg_ht00' => true]);
        $message = $this->start('');
        $message['message']['text'] = '/start';
        app(AlertService::class)->bind($message);
        $this->assertTrue($customer->fresh()->telegram_available);
        $this->assertTrue($customer->fresh()->tg_ht00);
        $this->assertTrue($customer->fresh()->tg_ltd);
    }

    public function test_compact_goal_and_reply_context_resolve_only_a_recorded_parent_match(): void
    {
        $this->fixture();
        $customer = $this->customer();
        $parent = app(AlertService::class)->source($this->source());
        $this->delivery($parent, $customer);
        DB::table('alert_deliveries')->where('alert_id', $parent)->update(['status' => 'sent']);
        $goal = $this->source("GOAL! Torino 0-1 Monza 1912\n1st Half - 33:38");
        $this->assertNotNull(app(AlertService::class)->source($goal));
        $this->assertNotNull(app(AlertService::class)->source([...$goal, 'text' => "GOAL! Score: 0 - 2 Time: 45+4'", 'reply_text' => $this->source()['text']]));
        $this->assertNull(app(AlertService::class)->source([...$goal, 'text' => "GOAL! Other 0-1 Teams @ 48'"]));
    }

    public function test_delivery_waits_on_customer_lock_and_does_not_lose_queued_notification(): void
    {
        $this->fixture();
        $customer = $this->customer();
        $id = app(AlertService::class)->source($this->source());
        $delivery = $this->delivery($id, $customer);
        $lock = Cache::lock('alerts:customer:'.$customer->id, 30);
        $lock->get();
        $this->assertSame(2, app(AlertService::class)->deliver($delivery));
        $lock->release();
        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'pending']);
        Http::assertNothingSent();
    }

    public function test_platform_targets_occupied_reverb_channels_without_affecting_telegram(): void
    {
        $this->fixture();
        $online = $this->customer();
        $offline = $this->customer();
        $this->onlineIds = [$online->id];
        $id = app(AlertService::class)->source($this->source());
        app(AlertService::class)->fanout($id);
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $online->id, 'channel' => 'platform']);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $id, 'customer_id' => $offline->id, 'channel' => 'platform']);
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $offline->id, 'channel' => 'telegram']);
    }
}
