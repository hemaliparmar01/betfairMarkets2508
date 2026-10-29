<?php

namespace Tests\Feature;

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
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

    public function test_notifications_require_auth_and_new_preferences_have_document_defaults(): void
    {
        $this->get('/notifications')->assertRedirect(route('login'));
        $customer = CustomerFactory::new()->create();
        $this->actingAs($customer, 'customers')->get('/notifications')->assertOk()->assertSee('Platform Live Pop-up Alerts')->assertSee('Open Telegram Web');
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

    public function test_save_and_popup_mute_are_independent_and_reject_unknown_preferences(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customers')->patchJson('/notifications', ['preferences' => array_fill_keys(AlertService::FILTERS, true)])->assertOk();
        $this->patchJson('/notifications', ['popup_enabled' => false])->assertOk()->assertJsonPath('preferences.ht00', true)->assertJsonPath('popup_enabled', false);
        $this->patchJson('/notifications', ['preferences' => ['admin' => true]])->assertUnprocessable();
        $this->assertTrue($customer->fresh()->tg_market_shock);
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

    public function test_source_rejects_ads_other_markets_ambiguous_matches_and_missing_or_stale_odds(): void
    {
        $match = $this->fixture();
        foreach (['Over 1.5', 'Over 2.5', 'Full Time Over 0.5', 'Asian Handicap', '1st Half Over 0.5 bonus deposit', '1st Half Over 0.5 register'] as $market) {
            $this->assertNull(app(AlertService::class)->source($this->source("Match: Torino vs Monza 1912\nLeague: Coppa Italia\n".$market)));
        }
        $this->assertNull(app(AlertService::class)->source($this->source('1st Half Over 0.5')));
        $this->assertNull(app(AlertService::class)->source([...$this->source(), 'source' => 'untrusted']));
        $this->assertNull(app(AlertService::class)->source([...$this->source(), 'sent_at' => time() - 3600]));
        FootballMarket::query()->update(['updated_at' => now()->subMinutes(4)]);
        $this->assertNull(app(AlertService::class)->source($this->source()));
        FootballMarket::query()->delete();
        $this->assertNull(app(AlertService::class)->source($this->source()));
        $this->assertDatabaseCount('alerts', 0);
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
        $neither = $this->customer(['popup_enabled' => false, 'tg_ltd' => false]);
        $expired = $this->customer(['trial_ends_at' => now()->subDay()]);
        $id = app(AlertService::class)->source($this->source());
        app(AlertService::class)->fanout($id);
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $telegram->id, 'channel' => 'telegram']);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $id, 'customer_id' => $telegram->id, 'channel' => 'platform']);
        $this->assertDatabaseHas('alert_deliveries', ['alert_id' => $id, 'customer_id' => $platform->id, 'channel' => 'platform']);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $id, 'customer_id' => $platform->id, 'channel' => 'telegram']);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $id, 'customer_id' => $neither->id]);
        $this->assertDatabaseMissing('alert_deliveries', ['alert_id' => $id, 'customer_id' => $expired->id]);
    }

    public function test_delivery_rechecks_expiry_disconnect_and_preferences_before_sending(): void
    {
        $match = $this->fixture();
        foreach (['expired', 'disconnected', 'unchecked'] as $reason) {
            $customer = $this->customer();
            $id = app(AlertService::class)->process('ltd', $match->payload, ['draw_lay' => 2.25]);
            $delivery = $this->delivery($id, $customer);
            if (! $delivery) {
                DB::table('alerts')->where('id', $id)->update(['fanned_out_at' => null]);
                $delivery = $this->delivery($id, $customer);
            }
            $customer->forceFill(match ($reason) {
                'expired' => ['trial_ends_at' => now()->subDay()], 'disconnected' => ['telegram_available' => false, 'telegram_chat_id' => null], default => ['tg_ltd' => false]
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
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::sequence()->push(['ok' => true, 'result' => ['message_id' => 42]])->push(['ok' => false, 'error_code' => 403], 403)]);
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
        $goal = $this->source("GOAL! Torino 0-1 Monza 1912 @ 48'");
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
