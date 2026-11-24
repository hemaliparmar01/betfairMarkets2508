<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Subscriptions;
use App\Services\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $customer = $request->user('customers')->fresh();
        $html = view('users.subscriptionStatus', [
            'customer' => $customer,
            'customerSubscriptions' => $customer->subscriptions()->with('subscriptionPlan')->latest('id')->get(),
        ])->render();

        return response()->json(['html' => $html, 'version' => hash('sha256', $html)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function subscriptions(SubscriptionService $subscriptions): View
    {
        return view('users.subscription', [
            'plans' => Subscriptions::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'isGeorgian' => $subscriptions->countryCode() === 'GE',
        ]);
    }

    public function getSubscriptions(Request $request, SubscriptionService $subscriptions): View|RedirectResponse
    {
        $plans = Subscriptions::query()->where('is_active', true)->orderBy('sort_order')->get();
        $selectedPlan = $request->filled('plan') ? $plans->firstWhere('id', $request->integer('plan')) : null;
        abort_if($request->filled('plan') && $selectedPlan === null, 404);

        if ($selectedPlan !== null && $request->user('customers') === null) {
            return redirect()->guest(route('login'));
        }
        $countryCode = $subscriptions->countryCode();
        $gateways = $subscriptions->gateways($countryCode);
        if ($selectedPlan !== null && blank($selectedPlan->paypal_plan_id)) {
            unset($gateways['paypal']);
        }

        return view('users.subscriptionDetails', [
            'plans' => $plans, 'selectedPlan' => $selectedPlan, 'gateways' => $gateways, 'isGeorgian' => $countryCode === 'GE',
        ]);
    }
}
