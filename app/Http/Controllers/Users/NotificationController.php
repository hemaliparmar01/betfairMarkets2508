<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAlert;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function notifications(Request $request, AlertService $alerts): View
    {
        return view('users.notifications', ['notificationState' => $alerts->status($request->user('customers'))]);
    }

    public function status(Request $request, AlertService $alerts): JsonResponse
    {
        return response()->json($alerts->status($request->user('customers')))->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, AlertService $alerts): JsonResponse
    {
        $data = $request->validate([
            'popup_enabled' => ['sometimes', 'required', 'boolean'],
            'preferences' => ['sometimes', 'required', 'array:'.implode(',', AlertService::FILTERS)],
            'preferences.*' => ['required', 'boolean'],
        ]);
        $customer = $request->user('customers');
        Cache::lock('alerts:customer:'.$customer->id, 30)->block(5, function () use ($customer, $data): void {
            $customer->refresh();
            $attributes = [];
            if (array_key_exists('popup_enabled', $data)) {
                $attributes['popup_enabled'] = $data['popup_enabled'];
            }
            foreach ($data['preferences'] ?? [] as $type => $enabled) {
                $attributes['tg_'.$type] = $enabled;
            }
            $customer->forceFill($attributes)->save();
        });
        $alerts->connectionChanged($customer->refresh());

        return response()->json($alerts->status($customer));
    }

    public function connect(Request $request): JsonResponse
    {
        abort_unless(config('services.telegram.enabled') && filled(config('services.telegram.bot_token')), 503, 'Telegram is not configured.');
        $token = bin2hex(random_bytes(24));
        $customer = $request->user('customers');
        Cache::lock('alerts:customer:'.$customer->id, 30)->block(5, function () use ($customer, $token): void {
            $customer->refresh()->forceFill(['telegram_token_hash' => hash('sha256', $token), 'telegram_token_expires_at' => now()->addMinutes(5), 'telegram_error' => null])->save();
        });

        return response()->json([
            'url' => 'https://t.me/BFMarketsAlertsBot?start='.$token,
            'web_url' => 'https://web.telegram.org/k/#?tgaddr='.rawurlencode('tg://resolve?domain=BFMarketsAlertsBot&start='.$token),
            'expires_in' => 300,
        ])->header('Cache-Control', 'no-store');
    }

    public function disconnect(Request $request, AlertService $alerts): JsonResponse
    {
        $customer = $request->user('customers');
        Cache::lock('alerts:customer:'.$customer->id, 30)->block(5, function () use ($customer): void {
            $customer->refresh()->forceFill(['telegram_chat_id' => null, 'telegram_username' => null, 'telegram_available' => false,
                'telegram_token_hash' => null, 'telegram_token_expires_at' => null, 'telegram_error' => null])->save();
        });
        $alerts->connectionChanged($customer->refresh());

        return response()->json($alerts->status($customer));
    }

    public function test(Request $request, AlertService $alerts): JsonResponse
    {
        $customer = $request->user('customers');
        abort_unless($customer->telegram_available && $customer->telegram_chat_id !== null, 422, 'Connect Telegram first.');
        abort_unless($alerts->eligible($customer), 403, 'An active trial or subscription is required.');
        abort_unless(config('services.telegram.enabled'), 503, 'Telegram is not configured.');
        $data = $request->validate(['request_id' => ['required', 'uuid']]);
        $id = $alerts->process('test', [], ['request_id' => $data['request_id']], $customer->id);

        return response()->json(['alert_id' => (string) $id, 'message' => 'Test notification queued.'], 202);
    }

    public function webhook(Request $request, AlertService $alerts): JsonResponse
    {
        $secret = config('services.telegram.webhook_secret');
        abort_unless(config('services.telegram.enabled') && filled($secret)
            && hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);
        $data = $request->validate(['update_id' => ['required', 'integer'], 'message' => ['sometimes', 'array']]);
        $alerts->bind($data);

        return response()->json(['ok' => true]);
    }

    public function source(Request $request): JsonResponse
    {
        $secret = config('services.telegram.source_secret');
        abort_unless(config('services.telegram.enabled') && filled($secret)
            && hash_equals($secret, (string) $request->header('X-BF-Source-Secret')), 403);
        $data = $request->validate([
            'source' => ['required', Rule::in([config('services.telegram.source_channel')])],
            'message_id' => ['required', 'integer', 'min:1'], 'sent_at' => ['required', 'integer'],
            'text' => ['required', 'string', 'max:8192'], 'reply_text' => ['nullable', 'string', 'max:8192'],
        ]);
        ProcessAlert::dispatch('source', $data);

        return response()->json(['accepted' => true], 202);
    }
}
