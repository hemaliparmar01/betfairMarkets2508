<?php

namespace Tests\Feature;

use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['en', false])]
    #[TestWith(['ka', false])]
    #[TestWith(['en', true])]
    #[TestWith(['ka', true])]
    public function test_homepage_uses_supplied_localized_landing_with_shared_navigation(string $locale, bool $signedIn): void
    {
        $this->withoutVite();
        Http::preventStrayRequests();
        Redis::shouldReceive('get')->never();
        $this->withSession(['locale' => $locale]);
        if ($signedIn) {
            $this->actingAs(CustomerFactory::new()->create(['active_session_id' => session()->getId()]), 'customers');
            $this->withCookie(config('session.cookie'), session()->getId());
        }

        $response = $this->withSession(['locale' => $locale])->get(route('home'))->assertOk()->assertViewIs('users.home')
            ->assertSee('data-homepage', false)->assertSee('lang="'.$locale.'"', false)
            ->assertSee($locale === 'ka' ? 'დაიჭირე ბაზარზე უპირატესობა' : 'Catch the market edge with')
            ->assertDontSee($locale === 'ka' ? 'Catch the market edge with' : 'დაიჭირე ბაზარზე უპირატესობა')
            ->assertSee('content="index, follow"', false)->assertDontSee('id="sportSelector"', false)
            ->assertDontSee('data-football-lazy-loader', false);
        if ($signedIn) {
            $response->assertDontSee('id="subscriptionModal"', false);
        } else {
            $response->assertSee('data-homepage-intro', false)->assertSee('data-close-subscription', false);
        }
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame($signedIn ? 0 : 1, $xpath->query('//*[@id="subscriptionModal"][@data-homepage-intro]')->length);
        $this->assertSame(route('home'), $xpath->evaluate('string(//a[img[@data-branding="logo"]]/@href)'));
        $this->assertSame(route('football.pinnacle-odds'), $xpath->evaluate('string(//*[@data-homepage]//a/@href)'));
        $this->assertSame(1, $xpath->query('//header')->length);
        $this->assertSame(1, $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " footer ")]')->length);
        $this->assertGreaterThan(0, $xpath->query('//*[@id="navLinks"]/a[@href="'.route('football.home').'"]')->length);
        $this->assertSame(['Organization', 'WebSite', 'SoftwareApplication'], array_column(
            json_decode($xpath->evaluate('string(//script[@type="application/ld+json"])'), true)['@graph'], '@type'));
        Http::assertNothingSent();
    }

    #[TestWith(['en'])]
    #[TestWith(['ka'])]
    public function test_fixtures_keep_the_existing_livescore_page_and_sport_navigation(string $locale): void
    {
        $this->withoutVite();
        Http::preventStrayRequests();
        Redis::shouldReceive('get')->andReturn(null);
        Redis::shouldReceive('mget')->andReturn([]);

        $this->withSession(['locale' => $locale]);
        $customer = CustomerFactory::new()->create(['active_session_id' => session()->getId(), 'trial_ends_at' => now()->addDays(2)]);
        $this->actingAs($customer, 'customers')->withCookie(config('session.cookie'), session()->getId());

        $this->assertSame('/football', route('football.home', [], false));
        $this->withSession(['locale' => $locale])->get(route('football.home', ['type' => 'upcoming']))->assertOk()
            ->assertViewIs('users.sports.football.football')->assertSee('id="sportSelector"', false)
            ->assertSee('data-football-lazy-loader', false)->assertDontSee('<section class="market-edge-landing"', false)
            ->assertSee(route('horseracing.home'), false)->assertSee(route('greyhounds.home'), false);
        Http::assertNothingSent();
    }
}
