<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class BetfairChartController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $customer = $request->user('customers');
        abort_unless($customer !== null && $customer->is_verified && ! $customer->is_blocked && ! $customer->is_suspended
            && ($customer->hasActiveSubscription() || $customer->hasActiveTrial()), 403);
        $data = $request->validate([
            'marketId' => ['required', 'regex:/^(?:[1-9][0-9]{0,17}|1\.[0-9]{1,18})$/'],
            'selectionId' => ['required', 'regex:/^[1-9][0-9]{0,17}$/'],
        ]);
        $url = 'https://xtsd.betfair.com/LoadRunnerInfoChartAction/?marketId='.$data['marketId'].'&selectionId='.$data['selectionId'].'&timestamp='.Carbon::now()->timestamp;
        try {
            $response = Http::withOptions(['allow_redirects' => true])->get($url);
            if ($response->successful()) {
                return response($response->body(), 200, [
                    'Content-Type' => $response->header('Content-Type') ?: 'image/jpeg',
                    'Cache-Control' => 'private, no-cache',
                ]);
            }
        } catch (Throwable) {
        }

        return response('', 502);
    }
}
