<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('customers.{id}.alerts', function (Customer $customer, string $id): bool {
    return (int) $customer->id === (int) $id && $customer->is_verified && ! $customer->is_blocked && ! $customer->is_suspended;
}, ['guards' => ['customers']]);
