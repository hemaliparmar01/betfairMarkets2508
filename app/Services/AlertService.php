<?php

namespace App\Services;

use App\Http\Controllers\Users\Football\FootballSyncController;
use App\Jobs\ProcessAlert;
use App\Models\Customer;
use App\Models\FootballMatch;
use App\Traits\FootballTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class AlertService
{
    use FootballTrait;

    public const FILTERS = ['ht00', '00_70', 'fav_losing', 'ltd', 'prematch_drops', 'market_shock'];

    public const STRATEGY = ['en' => 'Strategy: 1st Half Lay The Draw (LTD)', 'ka' => 'სტრატეგია: 1-ლი ტაიმის Lay The Draw (LTD)'];

    public function eligible(Customer $customer): bool
    {
        return $customer->is_verified && ! $customer->is_blocked && ! $customer->is_suspended
            && ($customer->hasActiveSubscription() || $customer->hasActiveTrial());
    }

    /** @return array<string, mixed> */
    public function status(Customer $customer): array
    {
        return [
            'connected' => $customer->telegram_chat_id !== null && $customer->telegram_available,
            'username' => $customer->telegram_username,
            'error' => $customer->telegram_error,
            'popup_enabled' => $customer->popup_enabled ?? true,
            'preferences' => collect(self::FILTERS)->mapWithKeys(fn (string $type): array => [$type => (bool) $customer->{'tg_'.$type}])->all(),
        ];
    }

    public function connectionChanged(Customer $customer): void
    {
        Broadcast::private('customers.'.$customer->id.'.alerts')->as('notifications.changed')->with($this->status($customer))->send();
    }

    /** @param array<string, mixed> $update */
    public function bind(array $update): void
    {
        $message = $update['message'] ?? [];
        if (($message['chat']['type'] ?? '') !== 'private' || ($message['from']['is_bot'] ?? false)
            || ! preg_match('/^[1-9][0-9]{0,18}$/', (string) ($message['chat']['id'] ?? ''))
            || (string) ($message['chat']['id'] ?? '') !== (string) ($message['from']['id'] ?? '')) {
            return;
        }
        $text = $message['text'] ?? '';
        if (! is_string($text) || ! preg_match('/^\/start(?:@BFMarketsAlertsBot)?(?:\s+([a-f0-9]{48}))?$/i', trim($text), $token)) {
            return;
        }
        $chatId = (string) $message['chat']['id'];
        $customerId = isset($token[1])
            ? Customer::query()->where('telegram_token_hash', hash('sha256', $token[1]))->value('id')
            : Customer::query()->where('telegram_chat_id', $chatId)->value('id');
        if ($customerId === null) {
            return;
        }
        try {
            $customer = Cache::lock('alerts:customer:'.$customerId, 30)->block(5, fn () => DB::transaction(function () use ($message, $token, $chatId): ?Customer {
                if (isset($token[1])) {
                    $customer = Customer::query()->where('telegram_token_hash', hash('sha256', $token[1]))->lockForUpdate()->first();
                    if ($customer === null) {
                        return null;
                    }
                    if ($customer->telegram_token_expires_at === null || $customer->telegram_token_expires_at->isPast()) {
                        $customer->forceFill(['telegram_token_hash' => null, 'telegram_token_expires_at' => null, 'telegram_error' => 'Telegram connection token expired. Please connect again.'])->save();

                        return $customer;
                    }
                    if (! $customer->is_verified || $customer->is_blocked || $customer->is_suspended
                        || Customer::query()->where('telegram_chat_id', $chatId)->whereKeyNot($customer->id)->exists()) {
                        $customer->forceFill(['telegram_error' => 'Telegram account is unavailable or already linked. Please try again.'])->save();

                        return $customer;
                    }
                } else {
                    $customer = Customer::query()->where('telegram_chat_id', $chatId)->lockForUpdate()->first();
                    if ($customer === null || ! $customer->is_verified || $customer->is_blocked || $customer->is_suspended) {
                        return null;
                    }
                }
                $customer->forceFill([
                    'telegram_chat_id' => $chatId, 'telegram_username' => $message['from']['username'] ?? null,
                    'telegram_available' => true, 'telegram_error' => null,
                    'telegram_token_hash' => null, 'telegram_token_expires_at' => null,
                ])->save();

                return $customer;
            }));
            if ($customer !== null) {
                $this->connectionChanged($customer);
            }
        } catch (UniqueConstraintViolationException) {
            $customer = isset($token[1]) ? Customer::query()->where('telegram_token_hash', hash('sha256', $token[1]))->first() : null;
            if ($customer !== null) {
                $customer->forceFill(['telegram_error' => 'Telegram account is already linked. Please try again.'])->save();
                $this->connectionChanged($customer);
            }
        }
    }

    public function scan(): void
    {
        if (! config('services.telegram.enabled')) {
            return;
        }
        $types = array_values(array_filter(self::FILTERS, static fn (string $type): bool => $type !== 'ltd'
            && Customer::query()->where('tg_'.$type, true)->where('telegram_available', true)->exists()));
        if ($types !== []) {
            $predicates = array_combine($types, array_map(fn (string $type): ?\Closure => $this->footballFilterPredicate($type), $types));
            FootballMatch::query()->whereIn('result_status', ['Live', 'Prematch'])
                ->whereBetween('start_timestamp', [time() - 6 * 3600, now(getCurrentUserTimezone())->endOfDay()->timestamp])
                ->with('markets')->lazyById(10)->each(function (FootballMatch $record) use ($predicates): void {
                    $match = $record->payload;
                    if (MatchBackup::isHidden($match, 'football')) {
                        return;
                    }
                    $match['markets'] = $record->markets->groupBy('market_type')->map(fn ($runners) => $runners->pluck('payload')->all())->all();
                    foreach ($predicates as $type => $predicate) {
                        if ($predicate($match)) {
                            $this->process($type, $match);
                        }
                    }
                });
        }
        Customer::query()->whereNotNull('telegram_chat_id')->where('telegram_available', true)
            ->where(fn (Builder $query) => $query->whereNull('admin_free_days')->orWhere('admin_free_days', '!=', -1))->where(function (Builder $query): void {
                $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', now());
            })->where(function (Builder $query): void {
                $query->whereNull('subscription_ends_at')->orWhere('subscription_ends_at', '<=', now());
            })->where(function (Builder $query): void {
                $query->whereNotNull('trial_ends_at')->orWhereNotNull('subscription_ends_at');
            })->lazyById(100)->each(fn (Customer $customer) => $this->process('expired', [], ['expires' => max($customer->trial_ends_at?->timestamp ?? 0, $customer->subscription_ends_at?->timestamp ?? 0)], $customer->id));
        DB::table('alert_deliveries')->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(2))
            ->update(['status' => 'unknown', 'error' => 'Worker stopped before delivery acknowledgement.', 'updated_at' => now()]);
        DB::table('alerts')->whereNull('fanned_out_at')->orderBy('id')->lazyById(100)->each(
            fn (object $alert) => ProcessAlert::dispatch('fanout', ['alert_id' => $alert->id])
        );
        DB::table('alert_deliveries')->whereIn('status', ['pending', 'retry'])->where('updated_at', '<', now()->subMinutes(2))
            ->orderBy('id')->lazyById(100)->each(fn (object $delivery) => ProcessAlert::dispatch('deliver', ['delivery_id' => $delivery->id]));
    }

    /** @param array<string, mixed> $message */
    public function source(array $message): ?int
    {
        if (($message['source'] ?? '') !== config('services.telegram.source_channel') || empty($message['message_id'])
            || ! is_numeric($message['sent_at'] ?? null) || abs(time() - (int) $message['sent_at']) > 300) {
            return null;
        }
        $text = trim((string) ($message['text'] ?? ''));
        $context = trim($text."\n".($message['reply_text'] ?? ''));
        if (preg_match('/\b(1xbet|promo(?:tion|\s*code)?|register|bonus|deposit|referral|advertisement)\b/i', $context)) {
            return null;
        }
        $goal = preg_match('/\bgo+a+l+\b/i', $text) === 1;
        if (! $goal && (! preg_match('/\b(?:1st\s*half|first\s*half|HT)\b/i', $text)
            || ! preg_match('/\bOver\s*\(?0[.,]5\)?(?:\s*Goals)?\b/i', $text)
            || preg_match('/\b(?:Over\s*\(?[1-9]|Full\s*Time|Full\s*Match|Asian\s*Handicap)\b/i', $text))) {
            return null;
        }
        $context = preg_replace('~https?://\S+|t\.me/\S+~i', '', $context);
        $hasTeams = preg_match('/(?:^|\n)\s*(?:⚽\s*)?(?:Match\s*:\s*)?([^\n:]+?)\s+(?:vs\.?|v\.?|—)\s+([^\n]+?)(?=\s+[|@]|\s+-\s+(?:Over|Under)|\n|$)/iu', $context, $teams);
        if (! $hasTeams && $goal) {
            $hasTeams = preg_match('/\bgo+a+l+!?\s*([^\n]+?)\s+\d{1,2}\s*[-:]\s*\d{1,2}\s+([^\n]+?)(?=\s+@|\n|$)/iu', $text, $teams);
        }
        $hasLeague = preg_match('/(?:League|Competition|Tournament)\s*:\s*([^\n|]+)/iu', $context, $league);
        if (! $hasTeams || (! $goal && ! $hasLeague)) {
            return null;
        }
        $home = trim($teams[1]);
        $away = trim($teams[2]);
        $competition = $hasLeague ? trim($league[1]) : null;
        $resolver = app(FootballSyncController::class);
        $candidates = FootballMatch::query()->where('result_status', 'Live')->whereBetween('start_timestamp', [time() - 4 * 3600, time()])
            ->select(['id', 'sports_api_pro_match_id', 'team1', 'team2', 'tournament', 'unique_key'])->get()
            ->filter(fn (FootballMatch $match): bool => $resolver->teamSimilarity($match->team1, $home) >= 80
                && $resolver->teamSimilarity($match->team2, $away) >= 80
                && ($competition === null ? DB::table('alerts')->where('match_id', $match->sports_api_pro_match_id)->where('type', 'ltd')->exists() : $resolver->teamSimilarity($match->tournament ?? '', $competition) >= 80));
        if ($candidates->count() !== 1) {
            return null;
        }
        $record = $candidates->first()->refresh();
        $match = $record->payload;
        if (MatchBackup::isHidden($match, 'football') || empty($match['sportsApiPro_match_id'])) {
            return null;
        }
        if ($goal) {
            if (! preg_match('/(?:Score\s*:\s*|\b)(\d{1,2})\s*[-:]\s*(\d{1,2})\b/i', $text, $score)
                || ! preg_match('/\b(\d{1,3}(?:\+\d{1,2})?)\s*[\x{0027}\x{2019}\x{2032}]/u', $text, $minute)) {
                return null;
            }
            $parent = DB::table('alerts')->where('type', 'ltd')->where('match_id', (string) $match['sportsApiPro_match_id'])->first();
            if ($parent === null) {
                return null;
            }
            $parentPayload = json_decode($parent->payload, true);
            $previous = explode(' - ', $parentPayload['score']);
            if ((int) $score[1] < (int) $previous[0] || (int) $score[2] < (int) $previous[1]
                || (int) $score[1] + (int) $score[2] <= (int) $previous[0] + (int) $previous[1]) {
                return null;
            }

            return $this->process('goal', $match, ['parent_alert_id' => $parent->id, 'score' => $score[1].' - '.$score[2], 'minute' => $minute[1]]);
        }
        if (! in_array($match['minutes_status'] ?? '', ['1st half', 'Live'], true)) {
            return null;
        }
        $record->load(['markets' => fn ($query) => $query->where('market_type', 'HALF_TIME')]);
        $draws = $record->markets->filter(fn ($runner): bool => in_array(strtolower(trim($runner->runner ?? '')), ['the draw', 'draw', 'x'], true)
            && $runner->lay_odds > 1 && $runner->lay_odds <= 1000 && empty($runner->payload['suspended']) && $runner->updated_at->gt(now()->subMinutes(2)));
        if ($draws->count() !== 1) {
            return null;
        }

        return $this->process('ltd', $match, ['draw_lay' => $draws->first()->lay_odds]);
    }

    /** @param array<string, mixed> $match @param array<string, mixed> $context */
    public function process(string $type, array $match, array $context = [], ?int $customerId = null): ?int
    {
        if (! config('services.telegram.enabled') || ! in_array($type, [...self::FILTERS, 'goal', 'test', 'expired'], true)) {
            return null;
        }
        if (! in_array($type, ['test', 'expired'], true)) {
            if (empty($match['sportsApiPro_match_id']) || empty($match['team1']) || empty($match['team2']) || empty($match['tournament'])
                || MatchBackup::isHidden($match, 'football')) {
                return null;
            }
            $predicate = $this->footballFilterPredicate($type);
            if ($predicate !== null && ! $predicate($match)) {
                return null;
            }
            if ($type === 'ltd' && (($match['result_status'] ?? '') !== 'Live' || (float) ($context['draw_lay'] ?? 0) <= 1 || (float) ($context['draw_lay'] ?? 0) > 1000)) {
                return null;
            }
        } elseif ($customerId === null) {
            return null;
        }
        $minute = $context['minute'] ?? (isset($match['timestamp']) ? $this->calculateMinutes($match['minutes_status'] ?? null, (int) $match['timestamp'], $match['result_status'], $match['added_time'] ?? [], $match['current_period_start_timestamp'] ?? null) : null);
        if ($type === 'ltd' && ($minute === null || (! str_starts_with((string) $minute, '45+') && (! is_numeric($minute) || (int) $minute > 45)))) {
            return null;
        }
        $score = $context['score'] ?? (isset($match['home_score'], $match['away_score']) ? $match['home_score'].' - '.$match['away_score'] : null);
        if ($type === 'ltd' && ($score === null || ! in_array($match['minutes_status'] ?? '', ['Live', '1st half'], true)
            || (($match['minutes_status'] ?? '') === 'Live' && time() - (int) $match['timestamp'] > 3600))) {
            return null;
        }
        $trigger = match ($type) {
            'test' => $context['request_id'] ?? null,
            'expired' => $context['expires'] ?? null,
            'goal', 'fav_losing' => $score,
            default => 'first',
        };
        $movements = [];
        if (in_array($type, ['prematch_drops', 'market_shock'], true) && ! isset($context['movement'])) {
            foreach ($match['markets'] ?? [] as $runners) {
                foreach ($runners as $runner) {
                    if (hasMeaningfulOpeningOdds($runner) && (($type === 'prematch_drops' && ($runner['prematch_drop_triggered'] ?? false))
                        || ($type === 'market_shock' && (int) ($runner['market_shock_detected_at'] ?? 0) >= time() - 1200))) {
                        $movements[] = ['market' => $runner['marketType'] ?? '', 'selection' => $runner['runner'] ?? '',
                            'from' => $runner['prematch_drop_baseline_odds'] ?? $runner['initialBackOdds'] ?? null,
                            'to' => $runner['currentBackOdds'] ?? null, 'at' => $runner[$type === 'market_shock' ? 'market_shock_detected_at' : 'prematch_drop_detected_at'] ?? null,
                            'volume' => $runner['volume_surge'] ?? null];
                    }
                }
            }
            foreach ($match['pinnacle_markets'] ?? [] as $runner) {
                if (($type === 'prematch_drops' && ($runner['drop_triggered'] ?? false))
                    || ($type === 'market_shock' && (int) ($runner['shock_detected_at'] ?? 0) >= time() - 1200)) {
                    $movements[] = ['market' => $runner['sect'] ?? '', 'selection' => $runner['outcome'] ?? '',
                        'from' => $runner['from_price'] ?? $runner['baseline_odds'] ?? null, 'to' => $runner['current_odds'] ?? null,
                        'at' => $type === 'market_shock' ? ($runner['shock_detected_at'] ?? null) : ($runner['initialBackOddsTimestamp'] ?? null), 'volume' => $runner['volume_surge'] ?? null];
                }
            }
            if ($movements === []) {
                return null;
            }
            $result = null;
            foreach ($movements as $movement) {
                $result = $this->process($type, $match, ['movement' => $movement]) ?? $result;
            }

            return $result;
        }
        if (isset($context['movement'])) {
            $movement = $context['movement'];
            $trigger = implode('|', [$movement['market'], $movement['selection'], $movement['at']]);
        }
        if ($type === 'goal' && (empty($context['parent_alert_id']) || $score === null || $minute === null
            || ! DB::table('alerts')->where('id', $context['parent_alert_id'])->where('type', 'ltd')->where('match_id', (string) $match['sportsApiPro_match_id'])->exists())) {
            return null;
        }
        if ($trigger === null) {
            return null;
        }
        $key = hash('sha256', implode('|', [$type, $match['sportsApiPro_match_id'] ?? $customerId, $trigger]));
        $payload = [
            'alert_type' => $type, 'match_id' => $match['sportsApiPro_match_id'] ?? null,
            'match' => isset($match['team1']) ? $match['team1'].' vs '.$match['team2'] : null,
            'league' => $match['tournament'] ?? null, 'score' => $score, 'minute' => $minute,
            'kickoff' => isset($match['timestamp']) ? date('c', (int) $match['timestamp']) : null,
            'customer_id' => $customerId, 'context' => $context,
            'favorite' => $match['bet365_prematch'] ?? [], 'markets' => array_intersect_key($match['markets'] ?? [], array_flip(['MATCH_ODDS', 'HALF_TIME'])),
        ];
        $payload['templates'] = [
            'en' => ['title' => $type === 'goal' ? 'GOAL! | BF MARKETS UPDATE' : 'BF MARKETS | LTD ALERT', 'strategy_label' => self::STRATEGY['en'], 'score_label' => $type === 'goal' ? 'New Score' : 'Score', 'minute_label' => 'min', 'odds_label' => '1st Half Draw Odds', 'view_btn' => 'View', 'mute_btn' => "Don't show again", 'status_label' => $type === 'goal' ? self::STRATEGY['en'].' Successful!' : 'LIVE', 'half_label' => '1st Half'],
            'ka' => ['title' => $type === 'goal' ? 'გოლი! | BF MARKETS UPDATE' : 'BF MARKETS | LTD ALERT', 'strategy_label' => self::STRATEGY['ka'], 'score_label' => $type === 'goal' ? 'ახალი ანგარიში' : 'ანგარიში', 'minute_label' => 'წუთი', 'odds_label' => '1-ლი ტაიმის Draw Odds', 'view_btn' => 'ნახვა', 'mute_btn' => 'აღარ ჩამაჩვენო', 'status_label' => $type === 'goal' ? self::STRATEGY['ka'].' წარმატებულია!' : 'LIVE', 'half_label' => '1-ლი ტაიმი'],
        ];
        DB::table('alerts')->insertOrIgnore(['event_key' => $key, 'type' => $type, 'match_id' => $payload['match_id'],
            'parent_alert_id' => $context['parent_alert_id'] ?? null, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
        $alert = DB::table('alerts')->where('event_key', $key)->first();
        if ($alert->fanned_out_at === null) {
            ProcessAlert::dispatch('fanout', ['alert_id' => $alert->id])->afterCommit();
        }

        return $alert->id;
    }

    public function fanout(int $alertId): int
    {
        $alert = DB::table('alerts')->find($alertId);
        if ($alert === null || $alert->fanned_out_at !== null) {
            return 0;
        }
        if ($alert->type === 'goal' && (DB::table('alerts')->where('id', $alert->parent_alert_id)->whereNull('fanned_out_at')->exists()
            || DB::table('alert_deliveries')->where('alert_id', $alert->parent_alert_id)->whereIn('status', ['pending', 'retry', 'sending'])->exists())) {
            return 5;
        }
        $payload = json_decode($alert->payload, true);
        foreach (['telegram', 'platform'] as $channel) {
            if ($channel === 'platform' && ! in_array($alert->type, ['ltd', 'goal'], true)) {
                continue;
            }
            $query = Customer::query()->where('is_verified', true)->where('is_blocked', false)->where('is_suspended', false);
            if ($channel === 'telegram') {
                $query->where('telegram_available', true)->whereNotNull('telegram_chat_id');
                if (in_array($alert->type, self::FILTERS, true)) {
                    $query->where('tg_'.$alert->type, true);
                } elseif ($alert->type === 'goal') {
                    $query->where('tg_ltd', true);
                }
            } else {
                $query->where('popup_enabled', true);
            }
            if (in_array($alert->type, ['test', 'expired'], true)) {
                $query->whereKey($payload['customer_id']);
            }
            if ($alert->type !== 'expired') {
                $query->where(function (Builder $eligible): void {
                    $eligible->where('admin_free_days', -1)->orWhere('trial_ends_at', '>', now())->orWhere(function (Builder $paid): void {
                        $paid->where('subscription_ends_at', '>', now())->where(fn (Builder $start) => $start->whereNull('subscription_starts_at')->orWhere('subscription_starts_at', '<=', now()));
                    });
                });
            }
            if ($alert->type === 'goal') {
                $query->whereExists(function ($parent) use ($alert, $channel): void {
                    $parent->selectRaw('1')->from('alert_deliveries')->whereColumn('customer_id', 'customers.id')
                        ->where('alert_id', $alert->parent_alert_id)->where('channel', $channel)->where('status', 'sent');
                });
            }
            if ($channel === 'platform' && $query->exists()) {
                try {
                    $channels = Broadcast::connection('reverb')->getPusher()->getChannels(['filter_by_prefix' => 'private-customers.'])->channels;
                    $online = [];
                    foreach (array_keys((array) $channels) as $name) {
                        if (preg_match('/^private-customers\.(\d+)\.alerts$/', $name, $customer)) {
                            $online[] = (int) $customer[1];
                        }
                    }
                    $query->whereKey($online);
                } catch (Throwable) {
                    return 5;
                }
            }
            $query->select(['id', 'telegram_chat_id'])->lazyById(100)->each(function (Customer $customer) use ($alert, $channel): void {
                DB::table('alert_deliveries')->insertOrIgnore(['alert_id' => $alert->id, 'customer_id' => $customer->id, 'channel' => $channel,
                    'destination' => $channel === 'telegram' ? $customer->telegram_chat_id : null, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                $id = DB::table('alert_deliveries')->where(['alert_id' => $alert->id, 'customer_id' => $customer->id, 'channel' => $channel])->value('id');
                ProcessAlert::dispatch('deliver', ['delivery_id' => $id])->afterCommit();
            });
        }
        DB::table('alerts')->where('id', $alertId)->update(['fanned_out_at' => now()]);

        return 0;
    }

    public function deliver(int $deliveryId): int
    {
        if (! config('services.telegram.enabled')) {
            return 0;
        }
        $delivery = DB::table('alert_deliveries')->find($deliveryId);
        if ($delivery === null || ! in_array($delivery->status, ['pending', 'retry'], true)) {
            return 0;
        }

        $retry = Cache::lock('alerts:delivery:'.$deliveryId, 60)->get(function () use ($delivery, $deliveryId): int {
            $retry = Cache::lock('alerts:customer:'.$delivery->customer_id, 30)->get(function () use ($delivery, $deliveryId): int {
                $customer = Customer::query()->whereKey($delivery->customer_id)->first();
                $delivery = DB::table('alert_deliveries')->where('id', $deliveryId)->first();
                if ($delivery === null || ! in_array($delivery->status, ['pending', 'retry'], true)) {
                    return 0;
                }
                $alert = DB::table('alerts')->find($delivery->alert_id);
                $payload = json_decode($alert->payload, true);
                $allowed = $customer !== null && ($alert->type === 'expired' ? (! $this->eligible($customer) && $customer->is_verified && ! $customer->is_blocked && ! $customer->is_suspended) : $this->eligible($customer));
                if ($allowed && $delivery->channel === 'telegram') {
                    $allowed = $customer->telegram_available && $customer->telegram_chat_id === $delivery->destination
                        && (! in_array($alert->type, self::FILTERS, true) || $customer->{'tg_'.$alert->type})
                        && ($alert->type !== 'goal' || $customer->tg_ltd);
                } elseif ($allowed) {
                    $allowed = $customer->popup_enabled;
                }
                if ($allowed && $alert->type === 'goal') {
                    $allowed = DB::table('alert_deliveries')->where('alert_id', $alert->parent_alert_id)->where('customer_id', $customer->id)
                        ->where('channel', $delivery->channel)->where('status', 'sent')->exists();
                }
                if (! $allowed) {
                    DB::table('alert_deliveries')->where('id', $deliveryId)->update(['status' => 'skipped', 'updated_at' => now()]);

                    return 0;
                }
                if ($delivery->channel === 'telegram') {
                    $paused = (int) Cache::get('alerts:telegram:paused_until', 0) - time();
                    if ($paused > 0) {
                        return $paused;
                    }
                    $permitted = Cache::lock('alerts:telegram:rate', 5)->get(fn (): bool => RateLimiter::attempt('alerts:telegram:global', 25, static fn () => true, 1)
                        && RateLimiter::attempt('alerts:telegram:chat:'.$customer->id, 1, static fn () => true, 1));
                    if (! $permitted) {
                        return 2;
                    }
                }
                DB::table('alert_deliveries')->where('id', $deliveryId)->update(['status' => 'sending', 'updated_at' => now()]);
                $status = 'sent';
                $error = null;
                $remoteId = null;
                $delay = 0;
                try {
                    if ($delivery->channel === 'platform') {
                        $public = array_intersect_key($payload, array_flip(['alert_type', 'match_id', 'match', 'league', 'score', 'minute', 'templates']));
                        $public['alert_id'] = (string) $alert->id;
                        $public['draw_lay'] = $payload['context']['draw_lay'] ?? null;
                        $public['url'] = route('football.market-activity', ['match' => $alert->match_id], false);
                        Broadcast::private('customers.'.$customer->id.'.alerts')->as('alert.received')->with($public)->sendNow();
                    } else {
                        $botToken = config('services.telegram.bot_token');
                        if (blank($botToken)) {
                            $status = 'failed';
                            $error = 'Telegram bot is not configured.';
                        } else {
                            $response = Http::connectTimeout(3)->timeout(10)->post('https://api.telegram.org/bot'.$botToken.'/sendMessage', [
                                'chat_id' => $customer->telegram_chat_id, 'text' => $this->text($payload), 'protect_content' => true,
                            ]);
                            if ($response->json('ok') === true) {
                                $remoteId = (string) $response->json('result.message_id');
                            } elseif (in_array((int) $response->json('error_code', $response->status()), [400, 403], true)) {
                                $status = 'failed';
                                $error = 'Telegram delivery unavailable. Open @BFMarketsAlertsBot and start the bot again.';
                                $customer->forceFill(['telegram_available' => false, 'telegram_error' => $error])->save();
                                $this->connectionChanged($customer);
                            } elseif ($response->json('ok') === false && ($response->status() >= 500 || (int) $response->json('error_code') === 429)) {
                                $status = 'retry';
                                $error = 'Telegram temporarily unavailable.';
                                $delay = max(2, min(3600, (int) $response->json('parameters.retry_after', 15)));
                                if ((int) $response->json('error_code') === 429) {
                                    Cache::put('alerts:telegram:paused_until', time() + $delay, $delay);
                                }
                            } else {
                                $status = 'failed';
                                $error = 'Telegram rejected the notification.';
                            }
                        }
                    }
                } catch (ConnectionException) {
                    // Telegram has no idempotency key: a lost acknowledgement must not cause a second send.
                    $status = 'unknown';
                    $error = 'Delivery acknowledgement unavailable; automatic resend suppressed.';
                } catch (Throwable) {
                    $status = 'unknown';
                    $error = 'Delivery outcome unavailable; automatic resend suppressed.';
                }
                DB::table('alert_deliveries')->where('id', $deliveryId)->update(['status' => $status, 'error' => $error, 'remote_id' => $remoteId,
                    'delivered_at' => $status === 'sent' ? now() : null, 'updated_at' => now()]);

                if ($alert->type === 'test' && $status !== 'retry') {
                    Broadcast::private('customers.'.$customer->id.'.alerts')->as('notification.tested')->with(['alert_id' => (string) $alert->id, 'sent' => $status === 'sent', 'message' => $status === 'sent' ? 'Test notification sent successfully.' : $error])->send();
                }

                return $delay;
            });

            return $retry === false ? 2 : $retry;
        });

        return $retry === false ? 2 : $retry;
    }

    /** @param array<string, mixed> $payload */
    public function text(array $payload): string
    {
        $type = $payload['alert_type'];
        if ($type === 'test') {
            return "🔔 BF MARKETS | TEST NOTIFICATION\n\nYour Telegram connection is working correctly.\nYou will receive alerts here based on your selected notification filters.";
        }
        if ($type === 'expired') {
            return 'Your free trial / subscription has expired. To continue receiving live exchange alerts, please renew your plan at betfairmarkets.com.';
        }
        $base = "⚽ Match: {$payload['match']}\n🏆 League: {$payload['league']}";
        $time = str_pad((string) ($payload['minute'] ?? '—'), 2, '0', STR_PAD_LEFT);
        $score = $payload['score'] ?? '—';
        if ($type === 'goal') {
            return "⚽️ GOAL DETECTED! | BF MARKETS UPDATE\n\n{$base}\n🔢 New Score: {$score}\n⏱ Time: {$time}' min (1st Half)\n\n✅ Status: ".self::STRATEGY['en'].' Successful!';
        }
        if ($type === 'ltd') {
            return "🔔 BF MARKETS | ALERT DETECTED\n\n🎯 Filter: ".self::STRATEGY['en']."\n{$base}\n⏱ Time: {$time}' min | Score: {$score}\n📈 1st Half Draw Odds: ".number_format($payload['context']['draw_lay'], 2)."\n\n💡 Risk Tip: Recommended max stake: 10% of bankroll.";
        }
        if (in_array($type, ['prematch_drops', 'market_shock'], true)) {
            $m = $payload['context']['movement'];
            $from = is_numeric($m['from']) ? number_format((float) $m['from'], 2) : '—';
            $to = is_numeric($m['to']) ? number_format((float) $m['to'], 2) : '—';
            $drop = is_numeric($m['from']) && (float) $m['from'] > 0 && is_numeric($m['to']) ? round(((float) $m['from'] - (float) $m['to']) / (float) $m['from'] * 100, 1) : null;
            $kickoff = isset($payload['kickoff']) ? Carbon::parse($payload['kickoff'])->timezone(getCurrentUserTimezone())->format('d M H:i') : '—';
            $shock = $type === 'market_shock';
            $text = ($shock ? '⚡️ BF MARKETS | MARKET SHOCK DETECTED' : '📉 BF MARKETS | PREMATCH DROP ALERT')
                ."\n\n🎯 Filter: ".($shock ? 'Market Shock Detector' : 'Prematch Drops')."\n{$base}\n⏰ Kickoff: {$kickoff}\n\n📊 Market: ".str_replace('_', ' ', $m['market']).' ('.$m['selection'].")\n📈 Odds Movement: {$from} 📉 {$to}".($drop === null ? '' : " (-{$drop}% Drop)");

            return $text.($shock ? "\n💥 Volume Surge: ".($m['volume'] ?? 'No data available') : "\n🔥 Price Drop: ".($drop === null ? '—' : "-{$drop}%"));
        }
        $label = ['ht00' => 'HT 0-0 (Half Time 0-0)', '00_70' => "0-0 @ 70'", 'fav_losing' => 'Favorite Losing In-Play'][$type];
        $text = "🔔 BF MARKETS | ALERT DETECTED\n\n🎯 Filter: {$label}\n{$base}\n".($type === 'ht00' ? '⏱ Status: Half Time' : "⏱ Time: {$time}' min")." | Score: {$score}";
        $odds = [];
        foreach ($payload['markets'][$type === 'ht00' ? 'HALF_TIME' : 'MATCH_ODDS'] ?? [] as $runner) {
            $odds[strtolower((string) ($runner['runner'] ?? ''))] = $runner['currentBackOdds'] ?? $runner['backOdds'] ?? null;
        }
        if ($type === 'fav_losing') {
            $side = $payload['favorite']['favorite_side'] ?? null;
            $teams = explode(' vs ', $payload['match'], 2);
            $favorite = $teams[$side === 'home' ? 0 : 1] ?? '—';

            return $text."\n\n🔥 Fav Team: {$favorite} (Pre-match @ ".($payload['favorite']['favorite_odds'] ?? '—').")\n📈 Current Live Odds: ".($odds[strtolower($favorite)] ?? '—');
        }
        $teams = explode(' vs ', $payload['match'], 2);

        return $text."\n\n".($type === 'ht00' ? '📊 1st Half Market Odds:' : '📉 In-Play Odds:')
            ."\n• Home: ".($odds[strtolower($teams[0])] ?? '—')."\n• Draw: ".($odds['the draw'] ?? $odds['draw'] ?? '—')."\n• Away: ".($odds[strtolower($teams[1])] ?? '—');
    }
}
