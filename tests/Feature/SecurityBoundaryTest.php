<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSubscriptions;
use App\Models\Favorite;
use App\Models\User;
use Database\Factories\CustomerFactory;
use Database\Factories\SubscriptionsFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_customer_write_rejects_missing_csrf_token_with_the_real_middleware_enabled(): void
    {
        $this->app->instance('env', 'local');
        $this->postJson('/favorites/toggle', ['sport' => 'football', 'event_key' => 'audit-only'])->assertStatus(419);
        $this->postJson('/login', ['identity' => 'audit@example.test', 'password' => 'Secret@123'])->assertStatus(419);
        $this->postJson('/admin/login', ['email' => 'audit@example.test', 'password' => 'Secret@123'])->assertStatus(419);
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_admin_guard_cannot_authenticate_a_customer_profile_request(): void
    {
        $this->actingAs(User::factory()->create(), 'web')->getJson('/my-profile')->assertUnauthorized();
    }

    public function test_profile_updates_cannot_grant_lifetime_access_or_change_authentication_attributes(): void
    {
        $customer = CustomerFactory::new()->create(['password' => 'Secret@123']);
        $password = $customer->password;
        $this->post('/login', ['identity' => $customer->email, 'password' => 'Secret@123'])->assertRedirect();
        $customer->refresh();
        $this->actingAs($customer, 'customers')->withCookie(config('session.cookie'), $customer->active_session_id)->withCredentials();
        $this->put('/my-profile', ['name' => 'Updated', 'surname' => $customer->surname, 'country_code' => 'IN', 'timezone' => 'Asia/Kolkata', 'admin_free_days' => -1, 'is_verified' => false, 'subscription_ends_at' => now()->addYears(10)->toIso8601String()])->assertSessionHasNoErrors()->assertRedirect();
        $customer->refresh();
        $this->assertSame('Updated', $customer->name);
        $this->assertNotSame(-1, $customer->admin_free_days);
        $this->assertNull($customer->subscription_ends_at);
        $this->assertTrue($customer->is_verified);
        $this->assertSame($password, $customer->password);
    }

    public function test_authenticated_customer_cannot_cancel_another_customers_subscription(): void
    {
        $customer = CustomerFactory::new()->create(['password' => 'Secret@123']);
        $other = CustomerFactory::new()->create();
        $plan = SubscriptionsFactory::new()->create();
        $subscription = CustomerSubscriptions::query()->create(['customer_id' => $other->id, 'subscription_id' => $plan->id, 'gateway' => 'admin', 'status' => 'active', 'amount' => 10, 'currency' => 'EUR']);
        $this->post('/login', ['identity' => $customer->email, 'password' => 'Secret@123'])->assertRedirect();
        $customer->refresh();
        $this->actingAs($customer, 'customers')->withCookie(config('session.cookie'), $customer->active_session_id)->withCredentials();
        $this->postJson('/subscriptions/'.$subscription->id.'/cancel')->assertForbidden();
        $this->assertSame('active', $subscription->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_expired_customer_cannot_read_paid_market_payloads_through_json_requests(): void
    {
        $customer = CustomerFactory::new()->create(['password' => 'Secret@123', 'trial_ends_at' => now()->subDay(), 'subscription_ends_at' => now()->subDay()]);
        $this->post('/login', ['identity' => $customer->email, 'password' => 'Secret@123'])->assertRedirect();
        $customer->refresh();
        $this->actingAs($customer, 'customers')->withCookie(config('session.cookie'), $customer->active_session_id)->withCredentials();
        Redis::shouldReceive('get')->never();
        $this->getJson('/football/market-activity')->assertForbidden()->assertJsonPath('access_required', true);
    }

    public function test_admin_search_treats_sql_injection_payload_as_search_text(): void
    {
        $customer = CustomerFactory::new()->create(['email' => 'private-audit@example.test']);
        $this->actingAs(User::factory()->create(), 'web')
            ->get('/admin/users?search='.rawurlencode("zzzz' OR 1=1 --"))
            ->assertOk()->assertDontSee($customer->email);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_customer_names_are_escaped_in_the_admin_listing(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        CustomerFactory::new()->create(['name' => $payload]);
        $this->actingAs(User::factory()->create(), 'web')->get('/admin/users')
            ->assertOk()->assertSee($payload)->assertDontSee($payload, false);
    }

    public function test_guest_cannot_delete_cache_through_the_public_debug_route(): void
    {
        $deleted = false;
        Redis::shouldReceive('del')->with('audit-only-sentinel')->zeroOrMoreTimes()->andReturnUsing(function () use (&$deleted): int {
            $deleted = true;

            return 1;
        });
        $response = $this->getJson('/delete-cache-key/audit-only-sentinel');
        $response->assertNotFound();
        $this->assertFalse($deleted);
    }

    public function test_password_login_limiter_blocks_further_attempts_without_authenticating(): void
    {
        $customer = CustomerFactory::new()->create(['password' => 'Secret@123']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['identity' => $customer->email, 'password' => 'Wrong@123'])
                ->assertUnprocessable()->assertJsonValidationErrors('identity');
        }
        $this->postJson('/login', ['identity' => $customer->email, 'password' => 'Secret@123'])
            ->assertUnprocessable()->assertSee('Too many login attempts');
        $this->assertGuest('customers');
        $this->assertDatabaseCount('customers_login_sessions', 0);
    }

    public function test_registration_ignores_privileged_fields_and_never_creates_an_admin(): void
    {
        $this->freezeTime();
        $this->post('/register', ['name' => 'Audit', 'surname' => 'Customer', 'username' => 'auditcustomer', 'email' => 'audit@example.test', 'password' => 'Secret@123', 'country_code' => 'GE', 'mobile' => '555123456', 'terms' => 1, 'is_verified' => true, 'admin_free_days' => -1, 'subscription_ends_at' => now()->addYears(10)->toIso8601String(), 'is_admin' => true, 'role' => 'admin'])
            ->assertSessionHasNoErrors()->assertRedirect(route('customers.verification'));
        $customer = Customer::query()->sole();
        $this->assertFalse($customer->is_verified);
        $this->assertNotSame(-1, $customer->admin_free_days);
        $this->assertSame(now()->addDays(7)->toDateTimeString(), $customer->subscription_ends_at->toDateTimeString());
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest('web');
        $this->assertGuest('customers');
    }

    public function test_favorite_toggle_uses_authenticated_owner_instead_of_supplied_customer_id(): void
    {
        $customer = CustomerFactory::new()->create(['password' => 'Secret@123']);
        $other = CustomerFactory::new()->create();
        Favorite::query()->create(['favorite_key' => hash('sha256', $other->id.'|football|audit-fixture'), 'customer_id' => $other->id, 'sport' => 'football', 'event_key' => 'audit-fixture']);
        $this->post('/login', ['identity' => $customer->email, 'password' => 'Secret@123'])->assertRedirect();
        $customer->refresh();
        $this->actingAs($customer, 'customers')->withCookie(config('session.cookie'), $customer->active_session_id)->withCredentials();
        $this->postJson('/favorites/toggle', ['sport' => 'football', 'event_key' => 'audit-fixture', 'customer_id' => $other->id])
            ->assertOk()->assertJsonPath('is_favorite', true);
        $this->assertDatabaseHas('favorites', ['customer_id' => $customer->id, 'event_key' => 'audit-fixture']);
        $this->assertDatabaseHas('favorites', ['customer_id' => $other->id, 'event_key' => 'audit-fixture']);
        $this->assertDatabaseCount('favorites', 2);
    }

    public function test_guest_cannot_obtain_a_sensitive_cache_canary_from_the_debug_dump_route(): void
    {
        $code = 'require '.var_export(base_path('vendor/autoload.php'), true).'; $app = require '.var_export(base_path('bootstrap/app.php'), true).'; $kernel = $app->make(\\Illuminate\\Contracts\\Http\\Kernel::class); $kernel->bootstrap();';
        $code .= <<<'PHP'
            $match = ['data_sync_complete' => true, 'unique_key' => 'audit', 'sportsApiPro_match_id' => 'audit', 'betfair_match_id' => 'audit', 'team1' => 'SYNTHETIC-SECURITY-CANARY', 'team2' => 'Away', 'tournament' => 'Audit League', 'timestamp' => time() + 60, 'result_status' => 'Prematch', 'minutes_status' => 'Prematch', 'markets' => ['m' => [['marketId' => 'm', 'marketType' => 'MATCH_ODDS', 'runnerId' => '1', 'runner' => 'Home', 'backOdds' => 2.0, 'initialBackOdds' => 2.0, 'initialBackOddsTimestamp' => time() - 60, 'opening_odds_source' => 'first_observed', 'monitoring_baseline_version' => 1]]]];
            \Illuminate\Support\Facades\Redis::shouldReceive('get')->andReturnUsing(static fn ($key) => $key === 'Football:matches' ? json_encode(['audit' => $match]) : '{"token":"SYNTHETIC-SECURITY-CANARY"}');
            $request = \Illuminate\Http\Request::create($argv[1], 'GET');
            $response = $kernel->handle($request);
            $response->send();
            PHP;
        foreach (['/get-sport-data/audit-only-sentinel', '/temp-data-merge'] as $path) {
            $process = new Process([PHP_BINARY, '-r', $code, $path], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BROADCAST_CONNECTION' => 'null', 'MAIL_MAILER' => 'array', 'REDIS_URL' => '', 'REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => '1', 'HTTP_PROXY' => 'http://127.0.0.1:1', 'HTTPS_PROXY' => 'http://127.0.0.1:1', 'ALL_PROXY' => 'http://127.0.0.1:1', 'NO_PROXY' => '', 'no_proxy' => '']);
            $process->setTimeout(5);
            $process->run();
            $this->assertStringNotContainsString('SYNTHETIC-SECURITY-CANARY', $process->getOutput(), $path);
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('404', $process->getOutput());
        }
    }

    public function test_sensitive_project_files_are_not_served_by_application_routes(): void
    {
        foreach (['/.env', '/.git/HEAD', '/composer.json', '/phpunit.xml', '/storage/logs/laravel.log'] as $path) {
            $response = $this->get($path);
            $this->assertContains($response->status(), [403, 404], $path);
        }
    }
}
