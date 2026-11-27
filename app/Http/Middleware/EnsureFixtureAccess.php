<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFixtureAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $checkAuthAndSubscription = checkAuthAndSubscription();
        if ($checkAuthAndSubscription['display_odds']) {
            return $next($request);
        }

        if ($request->expectsJson() || ! $request->isMethod('GET')) {
            return response()->json([
                'message' => __('Register and activate your 7-day free trial (first-time users), or purchase a paid subscription to access Fixtures, Market Activity, Pinnacle Drops, Horse Racing and Greyhounds.'),
                'access_required' => true,
                'data' => [],
            ], 403);
        }

        return response()->view('users.fixtures-locked', compact('checkAuthAndSubscription'));
    }
}
