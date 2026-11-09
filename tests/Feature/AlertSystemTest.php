<?php

namespace Tests\Feature;

use App\Console\Commands\ListenPinnodds;
use App\Http\Controllers\Users\Football\FootballSyncController;
use App\Jobs\ProcessAlert;
use App\Models\Customer;
use App\Models\FootballMarket;
use App\Models\FootballMatch;
use App\Repositories\CustomerRepository;
use App\Services\AlertService;
use App\Services\CustomerAuthService;
use Database\Factories\CustomerFactory;
use Illuminate\Broadcasting\AnonymousEvent;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schedule;
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
        $this->assertStringContainsString('10% of bankroll', app(AlertService::class)->text($payload));
        $this->assertSame($id, app(AlertService::class)->source($this->source()));
        $this->assertDatabaseCount('alerts', 1);
        Bus::assertDispatched(ProcessAlert::class, fn ($job) => $job->task === 'fanout');
    }

    public function test_ltd_parses_image_card_text_and_drops_the_promotional_footer(): void
    {
        $this->fixture(['team1' => 'Leeds', 'team2' => 'Tottenham', 'tournament' => 'English Premier League']);
        $customer = $this->customer(['popup_enabled' => false]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 45]])]);
        $text = "Football. England. Premier League\nLeeds United 0:0 Tottenham\nHotspur\n0:0\nTotal. Over (0.5). 1st half\n👉 Goal in the first half\nRegistration link on the 1xbet website\nhttps://example.test\nCODE POMO: HNV3";

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
        $this->assertNull(app(AlertService::class)->source($this->source(str_replace('Over (0.5)', 'Over (1.5)', $text))));
    }

    public function test_source_rejects_ads_and_other_markets_and_accepts_missing_or_stale_odds(): void
    {
        $match = $this->fixture();
        foreach (['Over 1.5', 'Over 2.5', 'Full Time Over 0.5', 'Asian Handicap', '1st Half Over 0.5 bonus deposit', '1st Half Over 0.5 register'] as $market) {
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

    public function test_goal_requires_valid_parent_and_only_reaches_parent_recipients(): void
    {
        $this->fixture();
        $goal = $this->source("GOOOOAAAALLLLL!\nMatch: Torino vs Monza 1912\nLeague: Coppa Italia\nScore: 0 - 1\nTime: 48'");
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
        Bus::assertDispatched(ProcessAlert::class, static fn (ProcessAlert $job): bool => $job->task === 'deliver' && $job->queue === 'alerts-telegram');
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
        Redis::shouldReceive('expire')->times(3)->with('Pinnacle:drops', 10800)->andReturnTrue();

        (new ReflectionMethod(ListenPinnodds::class, 'storeDrops'))->invoke($command, $pending, []);

        $this->assertSame([100], array_column(json_decode($stored, true)['alerts'], 'id'));
        Bus::assertDispatched(ProcessAlert::class, fn (ProcessAlert $job): bool => $job->task === 'pinnacle'
            && $job->queue === 'alerts' && $job->data['alerts'] === array_values($pending));
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
        Redis::shouldReceive('get')->with('Pinnacle:drops')->andReturn($cached);
        Redis::shouldReceive('get')->with('Football:matches')->andReturnNull();
        Redis::shouldReceive('set')->with('Pinnacle:drops', \Mockery::capture($stored))->once()->andReturnTrue();
        Redis::shouldReceive('expire')->with('Pinnacle:drops', 10800)->once()->andReturnTrue();

        (new ReflectionMethod(ListenPinnodds::class, 'storeDrops'))->invoke($command, [], []);

        $payload = json_decode($stored, true);
        $this->assertSame([1], array_column($payload['alerts'], 'id'));
        $this->assertSame([1, 2, 3], array_column($payload['signals'], 'id'));
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 1);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_pinnacle_archive_retains_all_markets_and_sports_once_without_enabling_notifications(): void
    {
        $this->freezeTime();
        config(['services.telegram.enabled' => false]);
        $base = ['id' => 100, 'sport' => 'snooker', 'home' => 'Home', 'away' => 'Away', 'sect' => 'Moneyline',
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

        $this->assertDatabaseCount('pinnacle_drop_archives', 13);
        $this->assertDatabaseHas('pinnacle_drop_archives', ['sport' => 'ice-hockey', 'market_group' => 0, 'start_timestamp' => intdiv($base['starts'], 1000)]);
        $this->assertDatabaseHas('pinnacle_drop_archives', ['alert_key' => hash('sha256', 'handicap'), 'market_group' => 3, 'is_live' => true]);
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
        Redis::shouldReceive('expire')->with('Pinnacle:drops', 10800)->once()->andReturnTrue();

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
        $this->assertCount(2, array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'pinnacle_drop_archives')));
        $this->assertCount(0, array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'football_matches') || str_contains($query['query'], 'pinnacle_betfair_events')));
        (new ProcessAlert('pinnacle', ['alerts' => [array_replace($drops[0], ['alert_key' => str_repeat('f', 64)])]]))->handle(app(AlertService::class));
        $cursor = $first->json('next_cursor');
        $next = $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'before_time' => $cursor['time'], 'before_key' => $cursor['key']]))
            ->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('has_more', false)->assertJsonPath('next_cursor', null);
        $this->assertSame([5, 4, 3, 2, 1], array_column($next->json('data'), 'id'));
        $this->get(route('football.pinnacle-odds', ['archive' => 1, 'market_filter' => 'handicap', 'mode' => 'live']))
            ->assertOk()->assertViewIs('users.sports.Football.marketActivity')->assertSee('Daily Archive')->assertSee('Load more history')->assertSee('Betfair Higher Odds Ratio')->assertSee('Archive resets daily at 6:00 AM Georgia time (GET / UTC+4).');
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'before_key' => 'invalid']))->assertUnprocessable()
            ->assertJsonValidationErrors(['before_time', 'before_key']);
        Http::assertNothingSent();
    }

    public function test_pinnacle_archive_filters_whole_history_and_restores_parent_names_without_live_cache(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-11-07 12:00:00 UTC'));
        config(['services.telegram.enabled' => false]);
        $this->actingAs($this->customer(['timezone' => 'UTC']), 'customers');
        Redis::shouldReceive('get')->never();
        $base = ['sport' => 'soccer', 'id' => 100, 'home' => 'Home', 'away' => 'Away', 'sect' => 'Moneyline',
            'from_price' => 2, 'to_price' => 1.8, 'alerted' => now()->timestamp];
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
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertJsonCount(7, 'data');
        foreach (['1h' => 1, '3h' => 2, 'future' => 1, 'today' => 3] as $filter => $count) {
            $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'time_filter' => $filter]))->assertOk()->assertJsonCount($count, 'data');
        }
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'mode' => 'live']))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'sport' => 'snooker', 'market_filter' => 'handicap']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.sect', 'spread');
        $main = $this->getJson(route('football.pinnacle-odds', ['archive' => 1, 'market_filter' => 'main']))->assertOk()->assertJsonCount(6, 'data');
        $btts = collect($main->json('data'))->firstWhere('sect', 'btts');
        $this->assertSame(['Home Club', 'Away Club', 'yes'], [$btts['home'], $btts['away'], $btts['outcome']]);
        $this->travelTo(new \DateTimeImmutable('2026-11-08 02:00:00 UTC'));
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertJsonCount(0, 'data');
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
        $this->travelTo(new \DateTimeImmutable('2026-11-07 01:59:59 UTC'));
        $this->actingAs($this->customer(['timezone' => 'America/New_York']), 'customers');
        $drop = ['id' => 100, 'sport' => 'snooker', 'home' => 'Home', 'away' => 'Away', 'sect' => 'Moneyline',
            'from_price' => 2, 'to_price' => 1.8, 'alerted' => now()->timestamp, 'alert_key' => str_repeat('a', 64)];
        (new ProcessAlert('pinnacle', ['alerts' => [$drop]]))->handle(app(AlertService::class));
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertJsonCount(1, 'data');
        $cleanup = collect(Schedule::events())->firstWhere('description', 'pinnacle-archive-cleanup');
        $this->assertFalse($cleanup->isDue($this->app));
        $this->travelTo(new \DateTimeImmutable('2026-11-07 02:00:00 UTC'));
        (new ProcessAlert('pinnacle', ['alerts' => [$drop, array_replace($drop, ['alert_key' => str_repeat('b', 64), 'alerted' => now()->timestamp])]]))->handle(app(AlertService::class));
        $this->getJson(route('football.pinnacle-odds', ['archive' => 1]))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.alert_key', str_repeat('b', 64))->assertJsonPath('summary.total', 1);
        $this->assertTrue($cleanup->isDue($this->app));
        $cleanup->run($this->app);
        $this->assertDatabaseCount('pinnacle_drop_archives', 1);
    }

    public function test_archive_analytics_use_original_betfair_quotes_and_count_unavailable_comparisons_separately(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-11-07 12:00:00 UTC'));
        config(['services.telegram.enabled' => false, 'services.sportsmeld.key' => 'must-not-call-provider']);
        Redis::shouldReceive('get')->never();
        $this->actingAs($this->customer(), 'customers');
        $at = now()->timestamp - 300;
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

    public function test_pinnacle_archive_cleanup_at_six_georgia_time_preserves_new_window_and_discards_previous_day(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-11-08 02:00:00 UTC'));
        $base = ['sport' => 'soccer', 'is_live' => false, 'market_group' => 0, 'payload' => '{}'];
        DB::table('pinnacle_drop_archives')->insert([
            $base + ['alert_key' => str_repeat('a', 64), 'alerted_at' => now()->timestamp - 1],
            $base + ['alert_key' => str_repeat('b', 64), 'alerted_at' => now()->timestamp],
        ]);
        $cleanup = collect(Schedule::events())->firstWhere('description', 'pinnacle-archive-cleanup');
        $this->assertNotNull($cleanup);
        $this->assertTrue($cleanup->isDue($this->app));

        $cleanup->run($this->app);

        $this->assertDatabaseCount('pinnacle_drop_archives', 1);
        $this->assertDatabaseHas('pinnacle_drop_archives', ['alert_key' => str_repeat('b', 64)]);
        $cleanup->run($this->app);
        $this->assertDatabaseCount('pinnacle_drop_archives', 1);
        $this->travel(1)->minutes();
        $this->assertFalse($cleanup->isDue($this->app));
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
        $this->assertNotNull($alerts->process('fav_losing', $losing));
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
