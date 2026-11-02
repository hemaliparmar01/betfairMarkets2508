<?php

use App\Http\Controllers\Users\AmericanFootball\AmericanFootballSyncController;
use App\Http\Controllers\Users\Baseball\BaseballSyncController;
use App\Http\Controllers\Users\Basketball\BasketballSyncController;
use App\Http\Controllers\Users\Cricket\CricketSyncController;
use App\Http\Controllers\Users\Football\FootballSyncController;
use App\Http\Controllers\Users\Greyhounds\GreyhoundsSyncController;
use App\Http\Controllers\Users\HorseRacing\HorseRacingSyncController;
use App\Http\Controllers\Users\IceHockey\IceHockeySyncController;
use App\Http\Controllers\Users\Tennis\TennisSyncController;
use App\Jobs\ProcessAlert;
use App\Services\FootballV6Stats;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(static function (): void {
    if (config('services.telegram.enabled')) {
        ProcessAlert::dispatch();
    }
})->name('customer-alerts')->everyMinute()->withoutOverlapping(2);

Schedule::command('sports:process-match-backups --scheduled')->runInBackground()->everyMinute()->withoutOverlapping(5);

Schedule::call(static function (): void {
    DB::table('football_timeline')->where('created_at', '<=', now()->subDays(3))->delete();
})->name('football-timeline-cleanup')->dailyAt('00:00')->timezone('Asia/Kolkata')->withoutOverlapping();

Schedule::call(function () {
    app(FootballSyncController::class)->queueMatchStatistics();
})->name('football-prematch-statistics')->everyMinute()->withoutOverlapping(2);

Schedule::call(function () {
    app(FootballV6Stats::class)->queueDueMatches();
})->name('football-v6-live-statistics')->everyMinute()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(FootballSyncController::class, 'syncBetFairLiveData');
})->name('football-betfair-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(FootballSyncController::class, 'syncSportsApiProLiveData');
})->name('football-sportApiPro-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(FootballSyncController::class, 'syncBetFairPrematchData');
})->name('football-betfair-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(FootballSyncController::class, 'syncSportsApiProPrematchData');
})->name('football-sportApiPro-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(CricketSyncController::class, 'syncBetFairLiveData');
})->name('cricket-betfair-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(CricketSyncController::class, 'syncSportsApiProLiveData');
})->name('cricket-sportApiPro-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(CricketSyncController::class, 'syncBetFairPrematchData');
})->name('cricket-betfair-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(CricketSyncController::class, 'syncSportsApiProPrematchData');
})->name('cricket-sportApiPro-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(IceHockeySyncController::class, 'syncBetFairLiveData');
})->name('icehockey-betfair-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(IceHockeySyncController::class, 'syncSportsApiProLiveData');
})->name('icehockey-sportApiPro-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(IceHockeySyncController::class, 'syncBetFairPrematchData');
})->name('icehockey-betfair-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(IceHockeySyncController::class, 'syncSportsApiProPrematchData');
})->name('icehockey-sportApiPro-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(AmericanFootballSyncController::class, 'syncBetFairLiveData');
})->name('americanfootball-betfair-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(AmericanFootballSyncController::class, 'syncSportsApiProLiveData');
})->name('americanfootball-sportApiPro-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(AmericanFootballSyncController::class, 'syncBetFairPrematchData');
})->name('americanfootball-betfair-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(AmericanFootballSyncController::class, 'syncSportsApiProPrematchData');
})->name('americanfootball-sportApiPro-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(BaseballSyncController::class, 'syncBetFairLiveData');
})->name('baseball-betfair-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(BaseballSyncController::class, 'syncSportsApiProLiveData');
})->name('baseball-sportApiPro-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(BaseballSyncController::class, 'syncBetFairPrematchData');
})->name('baseball-betfair-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(BaseballSyncController::class, 'syncSportsApiProPrematchData');
})->name('baseball-sportApiPro-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(BasketballSyncController::class, 'syncBetFairLiveData');
})->name('basketball-betfair-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(BasketballSyncController::class, 'syncSportsApiProLiveData');
})->name('basketball-sportApiPro-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(BasketballSyncController::class, 'syncBetFairPrematchData');
})->name('basketball-betfair-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(BasketballSyncController::class, 'syncSportsApiProPrematchData');
})->name('basketball-sportApiPro-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(TennisSyncController::class, 'syncBetFairLiveData');
})->name('tennis-betfair-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(TennisSyncController::class, 'syncSportsApiProLiveData');
})->name('tennis-sportApiPro-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(TennisSyncController::class, 'syncBetFairPrematchData');
})->name('tennis-betfair-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(TennisSyncController::class, 'syncSportsApiProPrematchData');
})->name('tennis-sportApiPro-prematch')->everyFifteenMinutes()->withoutOverlapping(10);

Schedule::call(function () {
    queueSportsSynchronization(GreyhoundsSyncController::class, 'syncBetFairPrematchData');
})->name('greyhounds-betfair-feed')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(HorseRacingSyncController::class, 'syncBetFairPrematchData');
})->name('horseracing-betfair-feed')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(HorseRacingSyncController::class, 'syncSportsApiProLiveData');
})->name('horseracing-sports-live')->everyFiveMinutes()->withoutOverlapping(2);

Schedule::call(function () {
    queueSportsSynchronization(HorseRacingSyncController::class, 'syncSportsApiProPrematchData');
})->name('horseracing-sports-prematch')->everyFifteenMinutes()->withoutOverlapping(2);

Schedule::command('subscriptions:process')->everyFiveMinutes()->withoutOverlapping(10);
