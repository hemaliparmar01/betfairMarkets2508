<?php

namespace Tests\Feature;

use App\Console\Commands\ListenPinnodds;
use App\Jobs\ProcessAlert;
use App\Services\AlertService;
use App\Services\PinnacleArchive;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionMethod;
use Tests\TestCase;

class PinnacleBasketballHandicapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.enabled' => false]);
        Http::preventStrayRequests();
    }

    private function drop(int $id, string $sport, string $section): array
    {
        return ['id' => $id, 'alert_key' => hash('sha256', 'handicap-filter-'.$id), 'sport' => $sport,
            'home' => 'Home', 'away' => 'Away', 'sect' => $section, 'outcome' => 'Home',
            'from_price' => 2, 'to_price' => 1.8, 'alerted' => time(), 'point' => -4.5];
    }

    #[TestWith(['Asian Handicap'])]
    #[TestWith(['ASIAN_HANDICAP'])]
    #[TestWith(['Asian-Handicap'])]
    #[TestWith(['Asian Handicaps'])]
    #[TestWith(['Spread'])]
    #[TestWith(['Spreads'])]
    #[TestWith(['Point Spread'])]
    #[TestWith(['Handicap'])]
    #[TestWith(['Asian Handicap (1st Half)'])]
    #[TestWith(['Asian Handicap - 1st Quarter'])]
    #[TestWith(['AH'])]
    public function test_listener_discards_basketball_handicaps_before_queueing_for_every_mode_and_period(string $section): void
    {
        $normalize = new ReflectionMethod(ListenPinnodds::class, 'normalizeDrops');
        $listener = app(ListenPinnodds::class);
        foreach (['live', 'prematch'] as $mode) {
            foreach ([0, 1, 2, 3, 4] as $period) {
                $numericSport = $this->drop(1, 'other', $section) + ['sport_id' => 3, 'period' => $period];
                $namedSport = $this->drop(2, 'basketball', $section) + ['period' => $period];

                $this->assertSame([], $normalize->invoke($listener, [$numericSport, $namedSport], $mode));
            }
        }
    }

    public function test_listener_preserves_other_basketball_markets_and_other_sports_handicaps(): void
    {
        $drops = [
            $this->drop(1, 'basketball', 'Moneyline'),
            $this->drop(2, 'basketball', 'Total'),
            $this->drop(3, 'basketball', 'Team Total'),
            $this->drop(4, 'basketball', 'European Handicap'),
            $this->drop(5, 'soccer', 'Asian Handicap'),
            $this->drop(6, 'tennis', 'Spread'),
            $this->drop(7, 'ice-hockey', 'Handicap'),
        ];

        $normalized = (new ReflectionMethod(ListenPinnodds::class, 'normalizeDrops'))->invoke(app(ListenPinnodds::class), $drops, 'prematch');

        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_column($normalized, 'id'));
    }

    public function test_already_queued_blocked_market_does_no_database_or_notification_work(): void
    {
        config(['services.telegram.enabled' => true]);
        $service = \Mockery::mock(AlertService::class);
        $service->shouldNotReceive('process');
        DB::enableQueryLog();
        try {
            (new ProcessAlert('pinnacle', ['alerts' => [$this->drop(1, 'basketball', 'Asian Handicap')]]))->handle($service);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertSame([], $queries);
        Http::assertNothingSent();
    }

    public function test_only_enabled_markets_are_recorded_and_counted_without_rewriting_previous_totals(): void
    {
        $drops = [$this->drop(1, 'basketball', 'Asian Handicap'), $this->drop(2, 'basketball', 'Moneyline'), $this->drop(3, 'soccer', 'Asian Handicap')];
        $job = new ProcessAlert('pinnacle', ['alerts' => $drops]);
        $job->handle(app(AlertService::class));
        $job->handle(app(AlertService::class));

        $this->assertDatabaseCount('pinnacle_drop_archives', 2);
        $this->assertDatabaseMissing('pinnacle_drop_archives', ['alert_key' => $drops[0]['alert_key']]);
        $summary = app(PinnacleArchive::class)->summary(PinnacleArchive::dayStart(now()->timestamp));
        $this->assertSame(2, $summary['total']);
        $this->assertSame(['basketball' => 1, 'soccer' => 1], $summary['sports']->pluck('total', 'sport')->all());
        Http::assertNothingSent();
    }

    public function test_historical_basketball_totals_are_preserved_when_new_handicaps_are_blocked(): void
    {
        $day = PinnacleArchive::dayStart(now()->timestamp);
        DB::table('pinnacle_daily_statistics')->insert(['day_start' => $day, 'sport' => 'basketball', 'total' => 100, 'compared' => 60, 'higher' => 20]);

        (new ProcessAlert('pinnacle', ['alerts' => [$this->drop(1, 'basketball', 'Spread')]]))->handle(app(AlertService::class));

        $summary = app(PinnacleArchive::class)->summary($day);
        $this->assertSame(100, $summary['total']);
        $this->assertSame(60, $summary['compared']);
        $this->assertSame(20, $summary['higher']);
        $this->assertDatabaseCount('pinnacle_drop_archives', 0);
    }

    public function test_pending_basketball_handicap_notification_is_skipped_at_delivery(): void
    {
        config(['services.telegram.enabled' => true]);
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay(), 'is_verified' => true,
            'is_blocked' => false, 'is_suspended' => false, 'popup_enabled' => true]);
        $service = app(AlertService::class);
        $this->assertTrue($service->eligible($customer));
        $alert = DB::table('alerts')->insertGetId(['event_key' => hash('sha256', 'old-basketball-handicap'), 'type' => 'prematch_drops',
            'created_at' => now(), 'payload' => json_encode(['context' => ['provider' => 'pinnacle', 'sport' => 'basketball',
                'pinnacle_drop' => $this->drop(1, 'basketball', 'Asian Handicap')]])]);
        $delivery = DB::table('alert_deliveries')->insertGetId(['alert_id' => $alert, 'customer_id' => $customer->id, 'channel' => 'platform',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(0, $service->deliver($delivery));

        $this->assertDatabaseHas('alert_deliveries', ['id' => $delivery, 'status' => 'skipped']);
        Http::assertNothingSent();
    }
}
