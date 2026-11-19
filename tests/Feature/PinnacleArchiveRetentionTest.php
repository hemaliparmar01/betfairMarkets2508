<?php

namespace Tests\Feature;

use App\Jobs\ProcessAlert;
use App\Repositories\CustomerRepository;
use App\Services\AlertService;
use App\Services\CustomerAuthService;
use App\Services\PinnacleArchive;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class PinnacleArchiveRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-18T10:00:00Z'));
        config(['services.telegram.enabled' => false]);
        Http::preventStrayRequests();
        $this->withoutVite();
        $auth = \Mockery::mock(CustomerAuthService::class, [app(CustomerRepository::class)])->makePartial();
        $auth->shouldReceive('handle')->andReturnUsing(fn ($request, $next) => $next($request));
        $this->app->instance(CustomerAuthService::class, $auth);
    }

    private function row(string $key, ?bool $higher = null, string $sport = 'basketball'): array
    {
        $at = now()->timestamp;

        return ['alert_key' => hash('sha256', $key), 'alerted_at' => $at, 'sport' => $sport, 'is_live' => false,
            'market_group' => 0, 'start_timestamp' => $at + 3600, 'betfair_higher' => $higher,
            'expires_at' => $at + 1800,
            'payload' => json_encode(['id' => $key, 'alert_key' => hash('sha256', $key), 'home' => 'Home', 'away' => 'Away',
                'sport' => $sport, 'sect' => 'Moneyline', 'outcome' => 'Home', 'alerted' => $at, 'monitor_received_at' => $at,
                'from_price' => 2, 'to_price' => 1.8, 'bf_at_drop' => $higher === null ? null : ($higher ? 1.9 : 1.7)])];
    }

    public function test_details_expire_at_thirty_minutes_but_totals_and_duplicate_protection_survive(): void
    {
        $archive = app(PinnacleArchive::class);
        $row = $this->row('first', true);
        $day = PinnacleArchive::dayStart(now()->timestamp);
        $archive->store([$row['alert_key'] => $row]);
        $archive->store([$row['alert_key'] => $row]);
        $this->travel(1799)->seconds();
        $this->assertSame(0, $archive->purge());
        $this->assertDatabaseCount('pinnacle_drop_archives', 1);

        $this->travel(1)->seconds();
        $this->assertSame(1, $archive->purge());
        $archive->store([$row['alert_key'] => array_replace($row, ['expires_at' => now()->addMinutes(30)->timestamp])]);

        $this->assertDatabaseCount('pinnacle_drop_archives', 0);
        $this->assertDatabaseCount('pinnacle_drop_receipts', 1);
        $this->assertSame(1, $archive->summary($day)['total']);
        $this->assertSame(1, $archive->summary($day)['compared']);
        $this->assertSame(1, $archive->summary($day)['higher']);
    }

    public function test_daily_sport_totals_preserve_compared_higher_and_unavailable_counts(): void
    {
        $rows = [$this->row('higher', true), $this->row('lower', false), $this->row('missing'), $this->row('soccer', true, 'soccer')];
        $archive = app(PinnacleArchive::class);
        $archive->store(array_column($rows, null, 'alert_key'));
        $this->travel(31)->minutes();
        $this->artisan('pinnacle:archive-maintain')->assertSuccessful();

        $summary = $archive->summary(PinnacleArchive::dayStart(now()->timestamp));
        $this->assertSame(4, $summary['total']);
        $this->assertSame(3, $summary['compared']);
        $this->assertSame(2, $summary['higher']);
        $this->assertSame(['basketball' => 3, 'soccer' => 1], $summary['sports']->pluck('total', 'sport')->all());
        $this->assertDatabaseCount('pinnacle_drop_archives', 0);
    }

    public function test_backfill_is_restartable_and_preserves_existing_counts_before_cleanup(): void
    {
        $row = $this->row('legacy', false);
        unset($row['expires_at']);
        DB::table('pinnacle_drop_archives')->insert($row);
        $archive = app(PinnacleArchive::class);
        $this->assertSame(1, $archive->backfill());
        $this->assertSame(1, $archive->backfill());
        $this->assertDatabaseHas('pinnacle_drop_archives', ['expires_at' => now()->timestamp + 1800]);
        $this->travel(31)->minutes();
        $this->artisan('pinnacle:archive-maintain')->assertSuccessful();

        $this->assertDatabaseCount('pinnacle_drop_archives', 0);
        $this->assertSame(1, $archive->summary(PinnacleArchive::dayStart(now()->timestamp))['total']);
    }

    public function test_cleanup_accounts_unbackfilled_legacy_rows_before_deleting_them(): void
    {
        $row = $this->row('unbackfilled', true);
        unset($row['expires_at']);
        DB::table('pinnacle_drop_archives')->insert($row);
        $this->travel(31)->minutes();

        $this->artisan('pinnacle:archive-maintain')->assertSuccessful();

        $this->assertDatabaseCount('pinnacle_drop_archives', 0);
        $this->assertSame(1, app(PinnacleArchive::class)->summary(PinnacleArchive::dayStart(now()->timestamp))['higher']);
    }

    public function test_georgia_six_am_starts_new_totals_without_deleting_previous_daily_statistics(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18T01:59:59Z'));
        $archive = app(PinnacleArchive::class);
        $old = $this->row('previous-day', true);
        $oldDay = PinnacleArchive::dayStart(now()->timestamp);
        $archive->store([$old['alert_key'] => $old]);
        $this->travel(1)->seconds();
        $new = $this->row('new-day');
        $newDay = PinnacleArchive::dayStart(now()->timestamp);
        $archive->store([$new['alert_key'] => $new]);
        $this->travel(31)->minutes();
        $archive->purge();

        $this->assertNotSame($oldDay, $newDay);
        $this->assertSame(1, $archive->summary($oldDay)['total']);
        $this->assertSame(1, $archive->summary($newDay)['total']);
        $this->assertDatabaseCount('pinnacle_daily_statistics', 2);
        $this->assertDatabaseCount('pinnacle_drop_receipts', 1);
    }

    public function test_archive_endpoint_hides_expired_details_before_cleanup_and_keeps_daily_summary(): void
    {
        $customer = CustomerFactory::new()->create(['trial_ends_at' => now()->addDay()]);
        $archive = app(PinnacleArchive::class);
        $old = $this->row('expired', true);
        $archive->store([$old['alert_key'] => $old]);
        $this->travel(23)->minutes();
        $recent = $this->row('recent', false);
        $archive->store([$recent['alert_key'] => $recent]);
        $this->travel(8)->minutes();

        $this->actingAs($customer, 'customers')->getJson(route('football.pinnacle-odds', ['archive' => 1, 'sport' => 'basketball']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.alert_key', $recent['alert_key'])
            ->assertJsonPath('summary.total', 2)->assertJsonPath('summary.compared', 2)->assertJsonPath('summary.higher', 1);
        $this->assertDatabaseCount('pinnacle_drop_archives', 2);
        Http::assertNothingSent();
    }

    public function test_pinnacle_job_counts_once_even_after_its_details_are_purged(): void
    {
        $drop = ['id' => 1, 'alert_key' => hash('sha256', 'job'), 'sport' => 'esports', 'home' => 'Home', 'away' => 'Away',
            'sect' => 'Moneyline', 'from_price' => 2, 'to_price' => 1.8, 'alerted' => now()->timestamp];
        $job = new ProcessAlert('pinnacle', ['alerts' => [$drop]]);
        $job->handle(app(AlertService::class));
        $this->travel(31)->minutes();
        app(PinnacleArchive::class)->purge();
        $job->handle(app(AlertService::class));

        $this->assertDatabaseCount('pinnacle_drop_archives', 0);
        $this->assertSame(1, app(PinnacleArchive::class)->summary(PinnacleArchive::dayStart(now()->timestamp))['total']);
        Http::assertNothingSent();
    }

    public function test_retention_cleanup_has_one_minute_schedule_without_overlap(): void
    {
        $events = collect(Schedule::events())->filter(fn ($event): bool => str_contains($event->command ?? '', 'pinnacle:archive-maintain'));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->sole()->expression);
        $this->assertTrue($events->sole()->withoutOverlapping);
    }
}
