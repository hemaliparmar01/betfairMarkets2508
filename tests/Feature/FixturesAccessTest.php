<?php

namespace Tests\Feature;

use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FixturesAccessTest extends TestCase
{
    use RefreshDatabase;

    private const SPORTS = ['football', 'cricket', 'icehockey', 'americanfootball', 'baseball', 'basketball', 'tennis', 'greyhounds', 'horseracing'];

    #[TestWith(['en', false])]
    #[TestWith(['ka', false])]
    #[TestWith(['en', true])]
    #[TestWith(['ka', true])]
    public function test_guests_and_expired_accounts_cannot_read_fixtures_or_their_data_endpoints(string $locale, bool $signedIn): void
    {
        $this->withoutVite();
        Http::preventStrayRequests();
        Redis::shouldReceive('get')->never();
        Redis::shouldReceive('mget')->never();
        $this->withSession(['locale' => $locale]);
        if ($signedIn) {
            $customer = CustomerFactory::new()->create([
                'active_session_id' => session()->getId(), 'trial_ends_at' => now()->subDay(),
                'subscription_ends_at' => now()->subDay(),
            ]);
            $this->actingAs($customer, 'customers')->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        }

        foreach (self::SPORTS as $sport) {
            $url = route($sport.'.home');
            $this->get($url)->assertOk()->assertViewIs('users.fixtures-locked')
                ->assertSee('id="premiumAccess"', false)->assertSee('data-premium-required', false)
                ->assertDontSee('id="subscriptionModal"', false)->assertDontSee('data-football-lazy-loader', false);
            $this->getJson($url)->assertForbidden()->assertJsonPath('access_required', true)->assertJsonPath('data', []);
            $path = $sport === 'horseracing' ? '/horse-racing' : '/'.$sport;
            foreach (['filter-', 'websocket-'] as $action) {
                $this->postJson($path.'/'.$action.$sport)->assertForbidden()
                    ->assertJsonPath('access_required', true)->assertJsonPath('data', []);
            }
        }
        $this->getJson('/tennis/get-cache-store-data')->assertForbidden()->assertJsonPath('data', []);
        Http::assertNothingSent();
    }

    #[TestWith(['trial'])]
    #[TestWith(['subscription'])]
    public function test_active_accounts_can_enter_all_existing_sport_fixtures_without_the_intro_overlay(string $access): void
    {
        $this->withoutVite();
        Http::preventStrayRequests();
        Redis::shouldReceive('get')->andReturn(null);
        Redis::shouldReceive('mget')->andReturn([]);
        $this->withSession(['locale' => 'en']);
        $customer = CustomerFactory::new()->create([
            'active_session_id' => session()->getId(),
            'trial_ends_at' => $access === 'trial' ? now()->addDay() : now()->subDay(),
            'subscription_starts_at' => $access === 'subscription' ? now()->subDay() : null,
            'subscription_ends_at' => $access === 'subscription' ? now()->addMonth() : null,
        ]);
        $this->actingAs($customer, 'customers')->withCookie(config('session.cookie'), session()->getId())->withCredentials();

        foreach (self::SPORTS as $sport) {
            $this->get(route($sport.'.home'))->assertOk()->assertViewIs('users.sports.'.$sport.'.'.$sport)
                ->assertDontSee('id="subscriptionModal"', false)->assertDontSee('id="premiumAccess"', false);
        }
        Http::assertNothingSent();
    }
}
