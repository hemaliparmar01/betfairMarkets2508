<?php

use App\Models\Customer;
use App\Services\MarketActivityStream;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('customers.{id}.alerts', function (Customer $customer, string $id): bool {
    return (int) $customer->id === (int) $id && $customer->is_verified && ! $customer->is_blocked && ! $customer->is_suspended;
}, ['guards' => ['customers']]);

Broadcast::channel('sports.{sport}.market-activity', function (Customer $customer, string $sport): bool {
    return in_array($sport, MarketActivityStream::SPORTS, true)
        && $customer->is_verified && ! $customer->is_blocked && ! $customer->is_suspended
        && ($customer->hasActiveSubscription() || $customer->hasActiveTrial());
}, ['guards' => ['customers']]);

Broadcast::channel('sports.horseracing.odds.{race}', function (Customer $customer, string $race): bool {
    return preg_match('/^[0-9_]{1,40}$/', $race) === 1
        && $customer->is_verified && ! $customer->is_blocked && ! $customer->is_suspended
        && ($customer->hasActiveSubscription() || $customer->hasActiveTrial());
}, ['guards' => ['customers']]);
