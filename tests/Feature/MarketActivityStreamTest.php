<?php

namespace Tests\Feature;

use App\Events\MarketActivityUpdated;
use App\Jobs\ProcessAlert;
use App\Services\AlertService;
use App\Services\CustomerAuthService;
use App\Services\MarketActivityStream;
use Database\Factories\CustomerFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\FileViewFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketActivityStreamTest extends TestCase
{
    use RefreshDatabase;

    public static function sports(): array
    {
        return array_map(static fn (string $sport): array => [$sport], array_keys(MarketActivityStream::SPORTS));
    }

    #[DataProvider('sports')]
    public function test_committed_exchange_quotes_stream_with_existing_indicators_and_without_history(string $prefix): void
    {
        config(['services.'.MarketActivityStream::SPORTS[$prefix].'_match_cache' => false]);
        $match = $this->match();
        Redis::shouldReceive('get')->with($prefix.':matches')->andReturn(json_encode(['match-a' => $match]));
        Event::fake([MarketActivityUpdated::class]);

        app(MarketActivityStream::class)->broadcastCurrent($prefix, [123 => []]);

        Event::assertDispatched(MarketActivityUpdated::class, function (MarketActivityUpdated $event) use ($prefix, $match): bool {
            $row = $event->markets[0];
            $expected = prepareMatchOddsMonitoring($match, true)['market_indicators']['123_WIN'];
            $this->assertSame('private-sports.'.MarketActivityStream::SPORTS[$prefix].'.market-activity', $event->broadcastOn()[0]->name);
            $this->assertSame('match-a', $row['matchKey']);
            $this->assertSame('123_WIN', $row['marketKey']);
            $this->assertSame(1.8, $row['runners'][0]['back']);
            $this->assertSame(1600.0, $row['runners'][0]['backSize']);
            $this->assertSame(40.72, $row['runners'][0]['laySize']);
            $this->assertSame($expected['balance'], $row['balance']);
            $this->assertSame($expected['momentum'], $row['momentum']);
            $this->assertSame($expected['delta'], $row['deltaValue']);
            $this->assertArrayNotHasKey('backOddsHistory', $row['runners'][0]);
            $this->assertArrayNotHasKey('liquidity_history', $row['runners'][0]);

            return ! $row['removed'];
        });
    }

    public function test_racing_books_remain_locked_before_the_existing_five_minute_window(): void
    {
        $match = array_replace($this->match(), ['timestamp' => time() + 600]);

        $this->assertSame([], app(MarketActivityStream::class)->markets('Greyhounds', 'match-a', $match));
        $rows = app(MarketActivityStream::class)->markets('Football', 'match-a', array_replace($match, ['result_status' => 'Live']));
        $this->assertTrue($rows[0]['removed']);
    }

    public function test_large_books_are_fragmented_without_dropping_runners_or_exceeding_reverb_limits(): void
    {
        $match = $this->match();
        $match['markets']['123_WIN'] = array_fill(0, 120, $match['markets']['123_WIN'][0]);
        $service = app(MarketActivityStream::class);
        $market = $service->markets('Football', 'match-a', $match)[0];

        $packets = $service->packets($market);

        $this->assertCount(12, $packets);
        $this->assertCount(120, array_merge(...array_column($packets, 'runners')));
        $this->assertCount(1, array_unique(array_column($packets, 'revision')));
        foreach ($packets as $packet) {
            $this->assertLessThan(8500, strlen(json_encode(json_encode(['markets' => [$packet]]))));
        }
    }

    public function test_market_channel_requires_an_active_verified_customer_and_valid_sport(): void
    {
        $this->withoutMiddleware(CustomerAuthService::class);
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        require base_path('routes/channels.php');
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-sports.football.market-activity', 'socket_id' => '123.456'])->assertOk();
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-sports.unknown.market-activity', 'socket_id' => '123.456'])->assertForbidden();
        $customer->update(['trial_ends_at' => now()->subDay()]);
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-sports.football.market-activity', 'socket_id' => '123.456'])->assertForbidden();
    }

    public function test_old_source_jobs_are_not_sent_as_current_trading_signals_when_the_worker_recovers(): void
    {
        config(['services.telegram.enabled' => true]);
        $alerts = \Mockery::mock(AlertService::class);
        $alerts->shouldNotReceive('source');
        $job = new ProcessAlert('source', ['message_id' => 12, 'received_at' => now()->subDay()->timestamp]);

        $job->handle($alerts);
    }

    public function test_fresh_source_jobs_are_processed_on_worker_recovery(): void
    {
        config(['services.telegram.enabled' => true]);
        $data = ['message_id' => 12, 'received_at' => now()->subSeconds(10)->timestamp];
        $alerts = \Mockery::mock(AlertService::class);
        $alerts->shouldReceive('source')->once()->with($data)->andReturn(123);

        (new ProcessAlert('source', $data))->handle($alerts);
    }

    public function test_selected_quote_endpoint_checks_access_and_returns_a_private_current_snapshot(): void
    {
        $this->withoutMiddleware(CustomerAuthService::class);
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        $quote = ['matchKey' => 'off-page', 'marketKey' => '123_WIN', 'runners' => [['backSize' => 400]]];
        $this->mock(MarketActivityStream::class)->shouldReceive('quote')->once()->with('Football', 'off-page', '123_WIN')->andReturn($quote);
        $query = ['sport' => 'football', 'match_key' => 'off-page', 'market_key' => '123_WIN'];

        $response = $this->getJson('/market-activity/quote?'.http_build_query($query));

        $response->assertJsonPath('markets.0', $quote)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/market-activity/quote?'.http_build_query(array_replace($query, ['sport' => 'invalid'])))->assertUnprocessable();
        $customer->update(['trial_ends_at' => now()->subDay()]);
        $this->getJson('/market-activity/quote?'.http_build_query($query))->assertForbidden();
    }

    #[DataProvider('sports')]
    public function test_all_market_pages_and_refresh_fragments_use_the_current_case_sensitive_shared_template(string $prefix): void
    {
        $this->withoutMiddleware(CustomerAuthService::class);
        $this->withoutVite();
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $this->actingAs($customer, 'customers');
        $files = new Filesystem;
        $strictFiles = \Mockery::mock(Filesystem::class)->makePartial();
        $strictFiles->shouldReceive('exists')->andReturnUsing(static fn (string $path): bool => ! preg_match('~/users/sports/(football|cricket|icehockey|americanfootball)/marketActivity\.blade\.php$~', $path) && $files->exists($path));
        app('view')->setFinder(new FileViewFinder($strictFiles, [resource_path('views')]));
        config(['cache.stores.redis.driver' => 'array']);
        Cache::purge('redis');
        Redis::shouldReceive('get')->andReturn(null);
        $controller = 'App\\Http\\Controllers\\Users\\'.$prefix.'\\'.$prefix.'Controller';
        $this->partialMock($controller, function ($mock): void {
            $mock->shouldReceive('getMarketActivites')->andReturn([]);
            $mock->shouldReceive('getAllMarkets')->andReturn(['options' => []]);
        });
        $sport = MarketActivityStream::SPORTS[$prefix];

        $url = route($sport.'.market-activity', [], false);
        $this->get($url)->assertOk()->assertSee('data-market-ui="selected-market-v2"', false)->assertSee('function refreshSelectedMarket()', false)->assertSee('function paintMarketDetail(event)', false);
        $this->getJson($url.'?_market_rows=1')->assertOk()->assertJsonStructure(['html', 'has_more', 'next_page']);
    }

    private function match(): array
    {
        return ['unique_key' => 'match-a', 'betfair_match_id' => 123, 'result_status' => 'Prematch', 'timestamp' => time() + 120,
            'markets' => ['123_WIN' => [
                ['runnerId' => 1, 'runner' => 'Favorite', 'marketType' => 'WIN', 'backOdds' => 1.8, 'layOdds' => 1.82,
                    'backSize' => 1600.0, 'laySize' => 40.72, 'liquidity_updated_at' => microtime(true),
                    'backOddsHistory' => [['odds' => 2.0, 'timestamp' => time() - 600]],
                    'liquidity_history' => [['back' => 30, 'lay' => 10, 'timestamp' => time() - 310], ['back' => 40, 'lay' => 10, 'timestamp' => time() - 70]]],
                ['runnerId' => 2, 'runner' => 'Away', 'marketType' => 'WIN', 'backOdds' => 3.0, 'layOdds' => 3.1,
                    'backSize' => 50.0, 'laySize' => 100.0, 'liquidity_updated_at' => microtime(true)],
            ]]];
    }
}
