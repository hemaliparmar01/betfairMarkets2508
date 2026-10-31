<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('language selection persists and retains the current page query without changing other session data', function () {
    $this->withSession(['filters' => ['type' => 'upcoming']])
        ->post(route('locale.update'), ['locale' => 'ka', 'return_to' => '/login?type=upcoming&page=2'])
        ->assertRedirect('/login?type=upcoming&page=2')
        ->assertSessionHas('locale', 'ka')
        ->assertSessionHas('filters.type', 'upcoming');
    $this->get('/login')->assertSee('lang="ka"', false)->assertSee('შესვლა');
    $this->get('/contact-us')->assertSee('დაგვიკავშირდით')->assertSee('data-lang="ka" aria-pressed="true"', false);
    $this->post(route('locale.update'), ['locale' => 'en', 'return_to' => '/contact-us'])->assertRedirect('/contact-us');
    $this->get('/contact-us')->assertSee('lang="en"', false)->assertSee('Contact Us');
});

test('unsupported locales and external return destinations cannot change the locale or redirect off site', function () {
    $this->withSession(['locale' => 'en'])->postJson(route('locale.update'), ['locale' => '../../private'])->assertUnprocessable()->assertSessionHas('locale', 'en');
    foreach (['https://example.com', '//example.com', '/\\example.com'] as $returnTo) {
        $this->post(route('locale.update'), ['locale' => 'ka', 'return_to' => $returnTo])->assertRedirect('/');
    }
    $this->withSession(['locale' => ['ka']])->get('/login')->assertSee('lang="en"', false);
});

test('json catalogues localize dynamic strings and validation while preserving provider names and numeric data', function () {
    $this->withSession(['locale' => 'ka'])->get('/login');
    $this->assertSame('მატჩები', __('Fixtures'));
    $this->assertSame('Unknown untranslated English label', __('Unknown untranslated English label'));
    $this->assertSame('გრაფიკი: Team <One>', __('Chart for :selection', ['selection' => 'Team <One>']));
    $this->postJson('/contact-us', [])->assertUnprocessable()->assertJsonPath('errors.name.0', __('validation.required', ['attribute' => 'სახელი']));
    $html = view('users.sports.football.partials.statisticsValues', ['section' => 'h2h', 'event' => ['team1' => 'Football', 'team2' => 'Tennis', 'prematch_statistics' => ['h2h' => ['teamDuel' => ['homeWins' => 4, 'draws' => 3, 'awayWins' => 2]]]]])->render();
    $this->assertStringContainsString('Football', $html);
    $this->assertStringContainsString('Tennis', $html);
    $this->assertStringContainsString('ფრეები', $html);
    $en = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
    $ka = json_decode(file_get_contents(lang_path('ka.json')), true, flags: JSON_THROW_ON_ERROR);
    $this->assertSame(array_keys($en), array_keys($ka));
    $this->assertSame('მატჩები იტვირთება…', $ka['Loading matches…']);
    $this->withSession(['locale' => 'ka'])->get('/admin/login')->assertSee('lang="ka"', false)->assertSee('ადმინისტრირების პანელი');
});

test('Georgian sports and shared information pages render through the existing routes', function () {
    Http::preventStrayRequests();
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('mget')->andReturn([]);
    foreach (['football', 'cricket', 'icehockey', 'americanfootball', 'baseball', 'basketball', 'tennis', 'horseracing', 'greyhounds'] as $sport) {
        $this->withSession(['locale' => 'ka'])->get(route($sport.'.home', ['type' => 'upcoming']))
            ->assertOk()->assertSee('lang="ka"', false)->assertSee('მატჩები');
    }
    foreach (['contact-us', 'faq', 'filter-guide', 'privacy-policy', 'terms-of-service', 'refund-policy', 'manifesto', 'disclaimer', 'feature-request', 'market-activity-guide', 'pinnacle-odds-drops-guide'] as $page) {
        $this->get('/'.$page)->assertOk()->assertSee('lang="ka"', false);
    }
});
