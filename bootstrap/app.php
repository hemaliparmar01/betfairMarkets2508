<?php

use App\Services\CustomerAuthService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestForgery(except: ['subscriptions/payments/webhooks/*', 'subscriptions/payment/return']);
        $middleware->web(append: [CustomerAuthService::class]);
        $middleware->redirectGuestsTo(fn (Request $request): string => route('login'));
        $middleware->redirectUsersTo(fn (Request $request): string => route('football.home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['code']);
        $exceptions->render(function (LockTimeoutException $exception, Request $request): ?JsonResponse {
            if (is_array($request->input('prediction')) && $request->routeIs(
                'tennis.filter-tennis', 'cricket.filter-cricket', 'basketball.filter-basketball', 'icehockey.filter-icehockey',
                'americanfootball.filter-americanfootball', 'baseball.filter-baseball', 'greyhounds.filter-greyhounds', 'horseracing.filter-horseracing',
            )) {
                return response()->json(['message' => __('Unable to load matches. Please try again.')], 503, ['Retry-After' => '1']);
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
