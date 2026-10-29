<?php

namespace App\Providers;

use App\Events\FootballSportsScoreUpdated;
use App\Jobs\ProcessAlert;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->shareCheckAuthSubscription();
        Event::listen(FootballSportsScoreUpdated::class, static function (): void {
            if (config('services.telegram.enabled')) {
                ProcessAlert::dispatch()->afterCommit();
            }
        });
    }

    private function shareCheckAuthSubscription(): void
    {
        View::composer('*', fn ($view) => $view->with('checkAuthAndSubscription', checkAuthAndSubscription())
        );
    }
}
