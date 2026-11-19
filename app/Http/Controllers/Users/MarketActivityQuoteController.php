<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Services\MarketActivityStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketActivityQuoteController extends Controller
{
    public function __invoke(Request $request, MarketActivityStream $stream): JsonResponse
    {
        $customer = $request->user('customers');
        abort_unless($customer !== null && $customer->is_verified && ! $customer->is_blocked && ! $customer->is_suspended
            && ($customer->hasActiveSubscription() || $customer->hasActiveTrial()), 403);
        $data = $request->validate([
            'sport' => ['required', Rule::in(array_values(MarketActivityStream::SPORTS))],
            'match_key' => ['required', 'string', 'max:512'],
            'market_key' => ['required', 'string', 'max:512'],
        ]);
        $prefix = array_search($data['sport'], MarketActivityStream::SPORTS, true);
        $quote = $stream->quote($prefix, $data['match_key'], $data['market_key']);

        return response()->json(['markets' => [$quote], 'sent_at' => microtime(true)])->header('Cache-Control', 'private, no-store');
    }
}
