<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('mget')->andReturn([]);
});

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee('Active Sports Exchange Markets')
        ->assertSee('Real-time Pinnacle odds drops and comparison with Betfair odds');
});

test('language selection persists and retains the current page query without changing other session data', function () {
    $this->withSession(['filters' => ['type' => 'upcoming']])
        ->post(route('locale.update'), ['locale' => 'ka', 'return_to' => '/login?type=upcoming&page=2'])
        ->assertRedirect('/login?type=upcoming&page=2')
        ->assertSessionHas('locale', 'ka')
        ->assertSessionHas('filters.type', 'upcoming');
    $this->get('/login')->assertSee('lang="ka"', false)->assertSee('შესვლა');
    $this->get('/contact-us')->assertSee('დაგვიკავშირდით')->assertSee('data-lang="ka" aria-pressed="true"', false);
    $this->get('/')
        ->assertSee('სპორტული ბირჟის აქტიური ბაზრები')
        ->assertSee('პინაკლეს კოეფიციენტების ვარდნების სწრაფი ალერტები და მათი შედარება ბეთფერის კოეფიციენტებთან')
        ->assertDontSee('Active Sports Exchange Markets');
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
    foreach (['football', 'cricket', 'icehockey', 'americanfootball', 'baseball', 'basketball', 'tennis', 'horseracing', 'greyhounds'] as $sport) {
        $this->withSession(['locale' => 'ka'])->get(route($sport.'.home', ['type' => 'upcoming']))
            ->assertOk()->assertSee('lang="ka"', false)->assertSee('მატჩები');
    }
    foreach (['contact-us', 'faq', 'filter-guide', 'privacy-policy', 'terms-of-service', 'refund-policy', 'manifesto', 'disclaimer', 'feature-request', 'market-activity-guide', 'pinnacle-odds-drops-guide'] as $page) {
        $this->get('/'.$page)->assertOk()->assertSee('lang="ka"', false);
    }
});

test('existing sports pages render document metadata without adding pages or changing the footer', function () {
    $expected = [
        '/' => ['BF Markets Analytics | Sports Exchange Market Data & Analytics', 'Real-time sports exchange market data and analytics. Monitor odds movements, liquidity, market activity, momentum, volatility and automated alerts with BF Markets Analytics.', 'index, follow'],
        '/football/market-activity' => ['Sports Market Activity & Liquidity | BF Markets Analytics', 'Monitor real-time sports exchange market activity, Back/Lay prices, liquidity, momentum, volatility and price movements with BF Markets Analytics.', 'noindex, follow'],
        '/football/pinnacle-odds' => ['Pinnacle Odds Drops & Market Movement | BF Markets', 'Track Pinnacle odds drops and price movements in real time, with market data, NVP and exchange comparisons from BF Markets Analytics.', 'noindex, follow'],
    ];
    foreach (['en', 'ka'] as $locale) {
        foreach ($expected as $path => [$title, $description, $robots]) {
            $response = $this->withSession(['locale' => $locale])->get($path)->assertOk();
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $this->assertSame(__($title), $xpath->evaluate('string(//title)'));
            $this->assertSame(__($description), $xpath->evaluate('string(//meta[@name="description"]/@content)'));
            $this->assertSame($robots, $xpath->evaluate('string(//meta[@name="robots"]/@content)'));
            $this->assertSame(url($path), $xpath->evaluate('string(//link[@rel="canonical"]/@href)'));
            $this->assertSame($locale, $xpath->evaluate('string(/html/@lang)'));
            $this->assertSame(0, $xpath->query('//link[@hreflang]')->length);
            $this->assertSame(10, $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " footer-links ")]/a')->length);
            foreach (['//title', '//meta[@name="description"]', '//meta[@name="robots"]', '//link[@rel="canonical"]', '//meta[@property="og:title"]', '//meta[@name="twitter:card"]'] as $selector) {
                $this->assertSame(1, $xpath->query($selector)->length, $path.' '.$selector);
            }
            $this->assertSame(__($title), $xpath->evaluate('string(//meta[@property="og:title"]/@content)'));
            $this->assertSame('summary_large_image', $xpath->evaluate('string(//meta[@name="twitter:card"]/@content)'));
            $response->assertDontSee('seo-heading')->assertDontSee('Open terminal');
        }
    }
    foreach (['/fixtures', '/market-activity', '/pinnacle-odds', '/sports-market-alerts', '/guides', '/ka'] as $missing) {
        $this->get($missing)->assertNotFound();
    }
    $this->assertFalse(Route::has('seo.sitemap'));
    $popup = $this->get('/popup')->assertOk();
    $this->assertSame(1, substr_count($popup->getContent(), '<title>'));
    $this->get('/notifications')->assertRedirect(route('login'));
    Http::assertNothingSent();
});

test('filter canonicals noindex and existing language switching retain their current URLs', function () {
    foreach (['type=upcoming', 'filter=live', 'filter=fav-losing', 'filter=market-shock', 'sort=time', 'market=over25', 'page=2'] as $query) {
        $response = $this->get('/?'.$query)->assertOk();
        $response->assertSee('content="noindex, follow"', false)->assertSee('rel="canonical" href="'.url('/').'"', false);
    }
    $this->get('/?utm_source=test&gclid=example')->assertOk()->assertSee('content="index, follow"', false);
    $this->post('/locale', ['locale' => 'ka', 'return_to' => '/?type=upcoming&page=2'])->assertRedirect('/?type=upcoming&page=2');
    $this->get('/?type=upcoming')->assertOk()->assertSee('lang="ka"', false)->assertDontSee('hreflang=', false);
    foreach (['/login', '/register', '/admin/login', '/horse-racing', '/greyhounds'] as $private) {
        $this->get($private)->assertOk()->assertSee('content="noindex, follow"', false);
    }
    Http::assertNothingSent();
});

test('schema and static sitemap describe only existing public content without additional database work', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get('/faq')->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $this->assertCount(1, $queries, 'Only the existing branding table check is needed in the empty test database.');
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $response->getContent(), $match);
    $schema = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    $this->assertSame('https://schema.org', $schema['@context']);
    $this->assertSame(['BreadcrumbList', 'FAQPage'], array_column($schema['@graph'], '@type'));
    foreach ($schema['@graph'][1]['mainEntity'] as $question) {
        $response->assertSee($question['name'])->assertSee($question['acceptedAnswer']['text']);
    }
    $this->assertDoesNotMatchRegularExpression('/SportsAPIPro|sportsapipro|pinnodds|Redis|Reverb|api[_-]?key|password|secret/i', json_encode($schema));
    $home = $this->get('/')->assertOk();
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $home->getContent(), $match);
    $schema = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    $this->assertSame(['Organization', 'WebSite', 'SoftwareApplication'], array_column($schema['@graph'], '@type'));
    $this->assertSame('BusinessApplication', $schema['@graph'][2]['applicationCategory']);
    $this->assertSame('Web', $schema['@graph'][2]['operatingSystem']);
    $this->assertSame('Real-time sports exchange market analytics and market data platform.', $schema['@graph'][2]['description']);
    $home->assertSee('alt="BF Markets Analytics"', false);

    $xml = simplexml_load_file(public_path('sitemap.xml'));
    $this->assertNotFalse($xml);
    $this->assertCount(17, $xml->url);
    foreach ($xml->url as $entry) {
        $path = parse_url((string) $entry->loc, PHP_URL_PATH);
        $page = $this->get($path)->assertOk()->assertSee('content="index, follow"', false);
        $page->assertSee('rel="canonical" href="'.url($path).'"', false);
        $this->assertDoesNotMatchRegularExpression('~[?]|/admin|/notifications|/football/|/api/|/login|/guides|/ka~', (string) $entry->loc);
    }
    $robots = file_get_contents(public_path('robots.txt'));
    $this->assertStringContainsString('Disallow: /admin', $robots);
    $this->assertStringContainsString('Sitemap: https://bfmarketsanalytics.com/sitemap.xml', $robots);
    Http::assertNothingSent();
});
