<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Repositories\CustomerRepository;
use App\Services\CustomerAuthService;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BetfairChartTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aY1sAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $auth = \Mockery::mock(CustomerAuthService::class, [app(CustomerRepository::class)])->makePartial();
        $auth->shouldReceive('handle')->andReturnUsing(fn ($request, $next) => $next($request));
        $this->app->instance(CustomerAuthService::class, $auth);
        Http::preventStrayRequests();
    }

    public function test_chart_requires_login(): void
    {
        $this->get('/open-chart?marketId=123&selectionId=456')->assertForbidden();
        Http::assertNothingSent();
    }

    #[TestWith([['trial_ends_at' => null]])]
    #[TestWith([['is_verified' => false]])]
    #[TestWith([['is_blocked' => true]])]
    #[TestWith([['is_suspended' => true]])]
    public function test_chart_enforces_subscription_and_account_access(array $attributes): void
    {
        $customer = CustomerFactory::new()->create(array_replace(['trial_ends_at' => now()->addDay()], $attributes));
        $this->actingAs($customer, 'customers')->get('/open-chart?marketId=123&selectionId=456')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_chart_returns_provider_body_and_content_type_without_server_caching(): void
    {
        $this->subscriber();
        $image = base64_decode(self::PNG);
        Http::fake(['xtsd.betfair.com/*' => Http::response($image, 200, ['Content-Type' => 'image/png'])]);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = $this->get('/open-chart?marketId=1.123&selectionId=456')->assertOk()
                ->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-cache, private');
            $this->assertSame($image, $response->getContent());
        }
        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['marketId'] === '1.123' && $query['selectionId'] === '456' && isset($query['timestamp']);
        });
    }

    public function test_chart_rechecks_access_on_each_request(): void
    {
        $customer = $this->subscriber();
        Http::fake(['xtsd.betfair.com/*' => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/png'])]);
        $this->get('/open-chart?marketId=123&selectionId=456')->assertOk();
        $customer->update(['trial_ends_at' => now()->subDay()]);
        $this->get('/open-chart?marketId=123&selectionId=456')->assertForbidden();
        Http::assertSentCount(1);
    }

    #[TestWith(['https://attacker.example', '456'])]
    #[TestWith(['123', '-1'])]
    #[TestWith(['123', '456&url=other'])]
    public function test_invalid_chart_identifiers_never_reach_provider(string $market, string $selection): void
    {
        $this->subscriber();
        $this->getJson('/open-chart?'.http_build_query(['marketId' => $market, 'selectionId' => $selection]))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_provider_failure_returns_an_empty_error_without_fallback_requests(): void
    {
        $this->subscriber();
        Http::fake(['xtsd.betfair.com/*' => Http::response('unavailable', 503)]);
        $this->get('/open-chart?marketId=123&selectionId=456')->assertStatus(502)->assertContent('');
        Http::assertSentCount(1);
    }

    public function test_network_failure_returns_an_empty_error_without_fallback_requests(): void
    {
        $this->subscriber();
        Http::fake(['xtsd.betfair.com/*' => Http::failedConnection()]);
        $this->get('/open-chart?marketId=123&selectionId=456')->assertStatus(502)->assertContent('');
        Http::assertSentCount(1);
    }

    public function test_component_defers_loading_and_uses_only_the_laravel_endpoint(): void
    {
        $this->blade('<x-market-chart market-id="1.123" selection-id="456" />')
            ->assertSee('data-market-chart', false)->assertSee('/open-chart?marketId=1.123', false)
            ->assertSee('src="data:image/svg+xml', false)->assertDontSee('xtsd.betfair.com');
    }

    private function subscriber(): Customer
    {
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');

        return $customer;
    }
}
