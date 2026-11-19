<?php

namespace Tests\Feature;

use App\Console\Commands\ListenPinnodds;
use App\Jobs\ProcessAlert;
use App\Repositories\CustomerRepository;
use App\Services\AlertService;
use App\Services\CustomerAuthService;
use App\Services\PinnacleArchive;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionMethod;
use Tests\TestCase;

class PinnaclePrematchOnlyTest extends TestCase
{
    use RefreshDatabase;

    private array $cache = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.enabled' => false, 'services.sportsmeld.key' => '', 'services.football_fixture_index' => false]);
        Http::preventStrayRequests();
        $this->withoutVite();
        Redis::shouldReceive('get')->andReturnUsing(fn (string $key): ?string => $this->cache[$key] ?? null);
        Redis::shouldReceive('getrange')->andReturn('');
        $auth = Mockery::mock(CustomerAuthService::class, [app(CustomerRepository::class)])->makePartial();
        $auth->shouldReceive('handle')->andReturnUsing(fn ($request, $next) => $next($request));
        $this->app->instance(CustomerAuthService::class, $auth);
    }

    private function drop(int $id, string $sport = 'soccer'): array
    {
        return ['id' => $id, 'alert_key' => hash('sha256', "prematch-$id"), 'sport' => $sport,
            'home' => 'Home', 'away' => 'Away', 'sect' => 'Moneyline', 'outcome' => 'Home', 'period' => 0,
            'from_price' => 2.0, 'to_price' => 1.8, 'alerted' => now()->timestamp, 'starts' => now()->addHour()->timestamp,
            'feed' => 'prematch', 'is_live' => false, 'monitor_received_at' => microtime(true)];
    }

    #[TestWith([1, 'soccer'])]
    #[TestWith([2, 'tennis'])]
    #[TestWith([3, 'basketball'])]
    #[TestWith([4, 'ice-hockey'])]
    #[TestWith([6, 'baseball'])]
    #[TestWith([11, 'esports'])]
    #[TestWith([13, 'cricket'])]
    public function test_parser_accepts_only_supported_prematch_sports(int $id, string $sport): void
    {
        $normalize = new ReflectionMethod(ListenPinnodds::class, 'normalizeDrops');
        $listener = app(ListenPinnodds::class);
        $drop = $this->drop(10, 'unknown') + ['sport_id' => $id];
        $this->assertSame($sport, array_values($normalize->invoke($listener, [$drop], 'prematch'))[0]['sport']);
        $this->assertSame([], $normalize->invoke($listener, [$drop], 'live'));
        $this->assertSame([], $normalize->invoke($listener, [array_replace($drop, ['is_live' => true])], 'prematch'));
        $this->assertSame([], $normalize->invoke($listener, [array_replace($drop, ['feed' => 'live'])], 'prematch'));
    }

    public function test_blocked_sports_and_live_queued_jobs_do_no_archive_or_statistics_work(): void
    {
        $drops = [];
        foreach (['american-football', 'boxing', 'golf', 'mma', 'other', ''] as $id => $sport) {
            $drops[] = $this->drop($id + 1, $sport);
        }
        $drops[] = array_replace($this->drop(50), ['is_live' => true]);
        $drops[] = array_replace($this->drop(51), ['sport_id' => 5]);
        $drops[] = array_replace($this->drop(52), ['starts' => now()->subSecond()->timestamp]);
        $service = Mockery::mock(AlertService::class);
        $service->shouldNotReceive('process');
        (new ProcessAlert('pinnacle', ['alerts' => $drops]))->handle($service);
        $this->assertDatabaseCount('pinnacle_drop_archives', 0);
        $this->assertDatabaseCount('pinnacle_daily_statistics', 0);
        $this->assertDatabaseCount('pinnacle_drop_receipts', 0);
    }

    public function test_archive_purges_at_kickoff_and_retains_the_daily_total(): void
    {
        $now = now()->timestamp;
        $drop = $this->drop(1, 'cricket');
        $drop['starts'] = $now + 60;
        (new ProcessAlert('pinnacle', ['alerts' => [$drop]]))->handle(app(AlertService::class));
        $this->assertDatabaseHas('pinnacle_drop_archives', ['alert_key' => $drop['alert_key'], 'expires_at' => $now + 60]);
        $this->travel(61)->seconds();
        app(PinnacleArchive::class)->purge();
        $this->assertDatabaseCount('pinnacle_drop_archives', 0);
        $this->assertSame(1, app(PinnacleArchive::class)->summary(PinnacleArchive::dayStart($now))['total']);
    }

    public function test_cleanup_removes_old_live_and_unsupported_details_without_losing_historical_counts(): void
    {
        $now = now()->timestamp;
        $rows = [];
        foreach (['soccer', 'boxing', 'tennis'] as $index => $sport) {
            $rows[] = ['alert_key' => hash('sha256', "legacy-$index"), 'alerted_at' => $now, 'sport' => $sport,
                'is_live' => $index === 0, 'market_group' => 0, 'start_timestamp' => $now + 3600,
                'expires_at' => $now + 1800, 'payload' => '{}'];
        }
        DB::table('pinnacle_drop_archives')->insert($rows);
        $archive = app(PinnacleArchive::class);
        $this->assertSame(2, $archive->purge());
        $this->assertDatabaseCount('pinnacle_drop_archives', 1);
        $this->assertDatabaseHas('pinnacle_drop_archives', ['sport' => 'tennis']);
        $this->assertDatabaseHas('pinnacle_daily_statistics', ['sport' => 'soccer', 'total' => 1]);
        $this->assertDatabaseHas('pinnacle_daily_statistics', ['sport' => 'boxing', 'total' => 1]);
        $this->assertSame(1, $archive->summary(PinnacleArchive::dayStart($now))['total']);
    }

    public function test_default_monitor_returns_football_only_and_switching_sports_fetches_that_sport(): void
    {
        $this->actingAs(CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]), 'customers');
        $this->cache['Pinnacle:drops'] = json_encode(['alerts' => [$this->drop(1), $this->drop(2, 'tennis'),
            array_replace($this->drop(3), ['is_live' => true]), $this->drop(4, 'boxing')], 'feeds' => ['prematch' => ['status' => 'connected', 'last_frame_at' => now()->timestamp]]]);
        $this->getJson(route('football.pinnacle-odds'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 1);
        $this->getJson(route('football.pinnacle-odds', ['sport' => 'tennis', 'mode' => 'live']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 2);
        $this->get(route('football.pinnacle-odds'))->assertOk()->assertDontSee('data-mode="live"', false)->assertDontSee('data-mode="all"', false)->assertDontSee('data-sport="all"', false)->assertSee('Football');
        Http::assertNothingSent();
    }
}
