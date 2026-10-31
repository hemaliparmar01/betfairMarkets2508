<?php

use App\Http\Controllers\Users\AmericanFootball\AmericanFootballController;
use App\Http\Controllers\Users\AuthController;
use App\Http\Controllers\Users\Baseball\BaseballController;
use App\Http\Controllers\Users\Basketball\BasketballController;
use App\Http\Controllers\Users\ContactController;
use App\Http\Controllers\Users\Cricket\CricketController;
use App\Http\Controllers\Users\FavoriteController;
use App\Http\Controllers\Users\FeatureRequestController;
use App\Http\Controllers\Users\Football\FootballController;
use App\Http\Controllers\Users\Greyhounds\GreyhoundsController;
use App\Http\Controllers\Users\HorseRacing\HorseRacingController;
use App\Http\Controllers\Users\IceHockey\IceHockeyController;
use App\Http\Controllers\Users\NotificationController;
use App\Http\Controllers\Users\StaticPageController;
use App\Http\Controllers\Users\SubscriptionController;
use App\Http\Controllers\Users\SubscriptionPaymentController;
use App\Http\Controllers\Users\Tennis\TennisController;
use App\Services\CustomerAuthService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::post('locale', function (Request $request): RedirectResponse {
    $data = $request->validate(['locale' => ['required', 'string', Rule::in(array_keys(config('app.supported_locales')))]]);
    $request->session()->put('locale', $data['locale']);
    app()->setLocale($data['locale']);
    $returnTo = $request->input('return_to', '/');
    if (! is_string($returnTo) || ! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//') || str_contains($returnTo, '\\') || preg_match('/[\x00-\x1F\x7F]/', $returnTo)) {
        $returnTo = '/';
    }

    return redirect($returnTo);
})->name('locale.update');

Route::get('popup', function () {
    return view('popup');
});

Route::middleware('guest:customers')->group(function () {
    Route::get('login', [AuthController::class, 'login'])->name('login');
    Route::post('login/mobile/send-code', [AuthController::class, 'sendLoginCode'])->middleware('throttle:5,1')->name('login.mobile.send-code');
    Route::get('login/mobile/verification', [AuthController::class, 'loginCode'])->name('login.mobile.verification');
    Route::post('login/mobile/verify-code', [AuthController::class, 'verifyLoginCode'])->middleware('throttle:10,1')->name('login.mobile.verify-code');
    Route::post('login/mobile/resend-code', [AuthController::class, 'resendLoginCode'])->middleware('throttle:5,1')->name('login.mobile.resend-code');
    Route::get('forgot-password', [AuthController::class, 'forgotPassword'])->name('customers.password.request');
    Route::post('forgot-password', [AuthController::class, 'sendPasswordReset'])->middleware('throttle:5,1')->name('customers.password.email');
    Route::get('reset-password/{token}', [AuthController::class, 'resetPassword'])->name('customers.password.reset');
    Route::post('reset-password', [AuthController::class, 'updatePassword'])->middleware('throttle:10,1')->name('customers.password.update');
    Route::get('register', [AuthController::class, 'register'])->name('register');
    Route::get('verify-email', [AuthController::class, 'verification'])->name('customers.verification');
    Route::post('verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:20,10')->name('customers.verification.verify');
    Route::post('verify-email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:5,60')->name('customers.verification.resend');
    Route::post('login', [AuthController::class, 'authenticate'])->name('login.store');
    Route::post('register', [AuthController::class, 'store'])->name('register.store');
    Route::post('login/logout-previous-sessions', [AuthController::class, 'confirmLogin'])->name('login.sessions.confirm');
    // Route::post('login', [AuthController::class, 'authenticate'])->middleware('throttle:30,1')->name('login.store');
    // Route::post('register', [AuthController::class, 'store'])->middleware('throttle:5,60')->name('register.store');
    // Route::post('login/logout-previous-sessions', [AuthController::class, 'confirmLogin'])->middleware('throttle:10,1')->name('login.sessions.confirm');
    Route::post('login/cancel', [AuthController::class, 'cancelLogin'])->name('login.sessions.cancel');
});
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:customers')->name('logout');
Route::get('my-profile', [AuthController::class, 'profile'])->middleware('auth:customers')->name('customers.profile');
Route::put('my-profile', [AuthController::class, 'updateProfile'])->middleware('auth:customers')->name('customers.profile.update');
Route::get('my-profile/avatar', [AuthController::class, 'avatar'])->middleware('auth:customers')->name('customers.avatar');

Route::get('/', [FootballController::class, 'football'])->name('football.home');
Route::post('favorites/toggle', [FavoriteController::class, 'toggle'])->middleware('auth:customers')->name('favorites.toggle');
Route::prefix('football')->name('football.')->group(function () {
    Route::get('/matches/{matchId}', [FootballController::class, 'matchDetail'])->name('matches.show');
    Route::get('/matches/{matchId}/statistics', [FootballController::class, 'statistics'])->whereNumber('matchId')->name('matches.statistics');
    Route::get('/market-activity', [FootballController::class, 'marketActivity'])->name('market-activity');
    Route::get('/pinnacle-odds', [FootballController::class, 'marketActivity'])->name('pinnacle-odds');
    Route::post('filter-football', [FootballController::class, 'filterFootball'])->name('filter-football');
    Route::post('websocket-football', [FootballController::class, 'websocketFootball']);
});

Route::prefix('cricket')->name('cricket.')->group(function () {
    Route::get('/', [CricketController::class, 'cricket'])->name('home');
    Route::get('matches/{matchId}', [CricketController::class, 'matchDetail'])->name('matches.show');
    Route::get('market-activity', [CricketController::class, 'marketActivity'])->name('market-activity');
    Route::post('filter-cricket', [CricketController::class, 'filterCricket'])->name('filter-cricket');
    Route::post('websocket-cricket', [CricketController::class, 'websocketCricket'])->name('websocket-cricket');
});

Route::prefix('icehockey')->name('icehockey.')->group(function () {
    Route::get('/', [IceHockeyController::class, 'icehockey'])->name('home');
    Route::get('matches/{matchId}', [IceHockeyController::class, 'matchDetail'])->name('matches.show');
    Route::get('market-activity', [IceHockeyController::class, 'marketActivity'])->name('market-activity');
    Route::post('filter-icehockey', [IceHockeyController::class, 'filterIceHockey'])->name('filter-icehockey');
    Route::post('websocket-icehockey', [IceHockeyController::class, 'websocketIceHockey'])->name('websocket-icehockey');
});

Route::prefix('americanfootball')->name('americanfootball.')->group(function () {
    Route::get('/', [AmericanFootballController::class, 'americanfootball'])->name('home');
    Route::get('matches/{matchId}', [AmericanFootballController::class, 'matchDetail'])->name('matches.show');
    Route::get('market-activity', [AmericanFootballController::class, 'marketActivity'])->name('market-activity');
    Route::post('filter-americanfootball', [AmericanFootballController::class, 'filterAmericanFootball'])->name('filter-americanfootball');
    Route::post('websocket-americanfootball', [AmericanFootballController::class, 'websocketAmericanFootball'])->name('websocket-americanfootball');
});

Route::prefix('basketball')->name('basketball.')->group(function () {
    Route::get('/', [BasketballController::class, 'basketball'])->name('home');
    Route::get('matches/{matchId}', [BasketballController::class, 'matchDetail'])->name('matches.show');
    Route::get('market-activity', [BasketballController::class, 'marketActivity'])->name('market-activity');
    Route::post('filter-basketball', [BasketballController::class, 'filterBasketball'])->name('filter-basketball');
    Route::post('websocket-basketball', [BasketballController::class, 'websocketBasketball'])->name('websocket-basketball');
});

Route::prefix('tennis')->name('tennis.')->group(function () {
    Route::get('/', [TennisController::class, 'tennis'])->name('home');
    Route::get('matches/{matchId}', [TennisController::class, 'matchDetail'])->name('matches.show');
    Route::get('market-activity', [TennisController::class, 'marketActivity'])->name('market-activity');
    Route::post('filter-tennis', [TennisController::class, 'filterTennis'])->name('filter-tennis');
    Route::post('websocket-tennis', [TennisController::class, 'websocketTennis'])->name('websocket-tennis');
    Route::get('get-cache-store-data', [TennisController::class, 'getCacheStoreData']);
});

Route::prefix('greyhounds')->name('greyhounds.')->group(function () {
    Route::get('/', [GreyhoundsController::class, 'greyhounds'])->name('home');
    Route::get('matches/{matchId}', [GreyhoundsController::class, 'matchDetail'])->name('matches.show');
    Route::get('market-activity', [GreyhoundsController::class, 'marketActivity'])->name('market-activity');
    Route::post('filter-greyhounds', [GreyhoundsController::class, 'filterGreyhounds'])->name('filter-greyhounds');
    Route::post('websocket-greyhounds', [GreyhoundsController::class, 'websocketGreyhounds'])->name('websocket-greyhounds');
});

Route::prefix('horse-racing')->name('horseracing.')->group(function () {
    Route::get('/', [HorseRacingController::class, 'horseracing'])->name('home');
    Route::get('matches/{matchId}', [HorseRacingController::class, 'matchDetail'])->name('matches.show');
    Route::get('market-activity', [HorseRacingController::class, 'marketActivity'])->name('market-activity');
    Route::post('filter-horseracing', [HorseRacingController::class, 'filterHorseRacing'])->name('filter-horseracing');
    Route::post('websocket-horseracing', [HorseRacingController::class, 'websocketHorseRacing'])->name('websocket-horseracing');
});

Route::prefix('baseball')->name('baseball.')->group(function () {
    Route::get('/', [BaseballController::class, 'baseball'])->name('home');
    Route::get('matches/{matchId}', [BaseballController::class, 'matchDetail'])->name('matches.show');
    Route::get('market-activity', [BaseballController::class, 'marketActivity'])->name('market-activity');
    Route::post('filter-baseball', [BaseballController::class, 'filterBaseball'])->name('filter-baseball');
    Route::post('websocket-baseball', [BaseballController::class, 'websocketBaseball'])->name('websocket-baseball');
});

Route::post('/set-timezone', function (Request $request) {
    $data = $request->timezone;
    abort_unless(in_array($data, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC), true), 422);
    $request->session()->put([
        'local_timezone' => $data,
    ]);
    return response()->json([
        'timezone' => $request->session()->get('local_timezone'),
    ]);
})->name('set-timezone');

Route::get('contact-us', [ContactController::class, 'contact'])->name('contact-us');
Route::post('contact-us', [ContactController::class, 'store'])->name('contact-us.store');
Route::middleware('auth:customers')->group(function (): void {
    Route::get('notifications', [NotificationController::class, 'notifications'])->name('notifications');
    Route::get('notifications/status', [NotificationController::class, 'status'])->name('notifications.status');
    Route::patch('notifications', [NotificationController::class, 'update'])->name('notifications.update');
    Route::post('notifications/telegram/connect', [NotificationController::class, 'connect'])->middleware('throttle:5,1')->name('notifications.connect');
    Route::delete('notifications/telegram', [NotificationController::class, 'disconnect'])->name('notifications.disconnect');
    Route::post('notifications/telegram/test', [NotificationController::class, 'test'])->middleware('throttle:3,1')->name('notifications.test');
});
Route::post('notifications/telegram/webhook', [NotificationController::class, 'webhook'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, CustomerAuthService::class, PreventRequestForgery::class]);
Route::post('notifications/telegram/source', [NotificationController::class, 'source'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, CustomerAuthService::class, PreventRequestForgery::class]);
Route::get('subscriptions', [SubscriptionController::class, 'subscriptions'])->name('subscriptions');
Route::get('subscriptions-details', [SubscriptionController::class, 'getSubscriptions'])->name('checkout-single');
Route::get('feature-request', [FeatureRequestController::class, 'featureRequest'])->name('feature-request');
Route::get('disclaimer', [StaticPageController::class, 'disclaimer'])->name('disclaimer');
Route::get('privacy-policy', [StaticPageController::class, 'privacyPolicy'])->name('privacy-policy');
Route::get('terms-of-service', [StaticPageController::class, 'termsOfService'])->name('terms-of-service');
Route::get('refund-policy', [StaticPageController::class, 'refundPolicy'])->name('refund-policy');
Route::get('faq', [StaticPageController::class, 'faq'])->name('faq');
Route::get('manifesto', [StaticPageController::class, 'manifesto'])->name('manifesto');
Route::get('filter-guide', [StaticPageController::class, 'filterGuide'])->name('filter-guide');
Route::get('market-activity-guide', [StaticPageController::class, 'marketActivityGuide'])->name('market-activity-guide');
Route::get('pinnacle-odds-drops-guide', [StaticPageController::class, 'pinnacleOddsDropsGuide'])->name('pinnacle-odds-drops-guide');

require __DIR__.'/admin.php';

Route::middleware('auth:customers')->group(function (): void {
    Route::post('subscriptions/{plan}/checkout', [SubscriptionPaymentController::class, 'checkout'])->middleware('throttle:10,1')->name('subscriptions.checkout');
    Route::get('subscriptions/payment/return', [SubscriptionPaymentController::class, 'returned'])->name('subscriptions.return');
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionPaymentController::class, 'cancel'])->middleware('throttle:10,1')->name('subscriptions.cancel');
});
Route::post('subscriptions/payments/webhooks/{gateway}', [SubscriptionPaymentController::class, 'webhook'])->name('subscriptions.webhook');

Route::post('subscriptions/payment/return', [SubscriptionPaymentController::class, 'returnedPost'])
    ->withoutMiddleware([PreventRequestForgery::class, StartSession::class, ShareErrorsFromSession::class, CustomerAuthService::class]);
