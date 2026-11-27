<?php

namespace App\Services;

use App\Http\Controllers\Users\Football\FootballController;
use App\Http\Controllers\Users\Football\FootballSyncController;
use App\Http\Controllers\Users\Tennis\TennisSyncController;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use RedisException;
use Throwable;

class AlertService
{
    use FootballTrait;

    public const FILTERS = ['ht00', '00_70', 'fav_losing', 'ltd'];

    private const MAIN_MARKETS = ['moneyline', 'money line', '1x2', 'match odds', 'btts', 'both teams to score',
        'over under 05', 'over under 15', 'over under 25', 'over under 35', 'over under 45'];

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
            'pinnacle_filters' => $this->pinnacleFilters($customer),
            'preferences' => collect(self::FILTERS)->mapWithKeys(fn (string $type): array => [$type => (bool) $customer->{'tg_'.$type}])->all(),
        ];
    }

    public function pinnacleFilters(Customer $customer): array
    {
        $stored = DB::table('site_settings')->where('key', 'customer.pinnacle_filters.'.$customer->id)->value('value');

        return (json_decode($stored ?? '', true) ?? []) + ['higher' => false, 'shock' => false, 'leagues' => null];
    }

    /** @param array<string, mixed> $drop */
    public const PINNACLE_SPORTS = ['soccer', 'tennis', 'basketball', 'ice-hockey', 'baseball', 'cricket', 'esports'];

    public static function pinnacleSport(array $drop): string
    {
        $id = (int) ($drop['sport_id'] ?? 0);
        if ($id > 0) {
            return [1 => 'soccer', 2 => 'tennis', 3 => 'basketball', 4 => 'ice-hockey', 6 => 'baseball', 11 => 'esports', 13 => 'cricket'][$id] ?? '';
        }
        $sport = str_replace(['_', ' '], '-', strtolower(trim((string) ($drop['sport'] ?? ''))));

        return ['football' => 'soccer', 'hockey' => 'ice-hockey', 'e-sports' => 'esports'][$sport] ?? $sport;
    }

    public static function pinnacleStartsAt(array $drop): int
    {
        $starts = $drop['starts'] ?? $drop['start'] ?? $drop['archive_parent']['starts'] ?? null;
        $timestamp = is_numeric($starts) ? (int) $starts : (is_string($starts) ? (int) strtotime($starts) : 0);

        return $timestamp > 100_000_000_000 ? (int) floor($timestamp / 1000) : $timestamp;
    }

    public static function allowsPinnacleMarket(array $drop): bool
    {
        $sport = self::pinnacleSport($drop);
        $starts = self::pinnacleStartsAt($drop);
        if (! in_array($sport, self::PINNACLE_SPORTS, true)
            || filter_var($drop['is_live'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || strtolower((string) ($drop['feed'] ?? 'prematch')) !== 'prematch'
            || ($starts > 0 && $starts <= now()->timestamp)) {
            return false;
        }
        $basketball = $sport === 'basketball';
        if (! $basketball) {
            return true;
        }
        foreach (['sect', 'market_type', 'marketType'] as $field) {
            $market = trim(preg_replace('/[\s_-]+/', ' ', strtolower((string) ($drop[$field] ?? ''))));
            if (preg_match('/^(?:(?:asian )?handicaps?|(?:point )?spreads?|ah)(?:$|\s|\()/i', $market) === 1) {
                return false;
            }
        }

        return true;
    }

    /** @return array{string, string}|null */
    public static function pinnacleCompetition(array $drop): ?array
    {
        $label = trim((string) ($drop['league_name'] ?? $drop['league'] ?? ''));
        $sport = match ((int) ($drop['sport_id'] ?? 0)) {
            1 => 'soccer',
            2 => 'tennis',
            0 => strtolower((string) ($drop['sport'] ?? '')),
            default => '',
        };
        if ($sport === 'tennis') {
            return TennisSyncController::pinnacleCompetition($label);
        }
        if (! in_array($sport, ['soccer', 'football'], true)) {
            return null;
        }

        $country = $drop['country'] ?? '';
        $country = trim((string) (is_array($country) ? ($country['name'] ?? '') : $country));
        $competition = FootballSyncController::pinnacleCompetition($country, $label);
        if ($competition === ['UEFA', 'UEFA Nations League']) {
            return $competition;
        }
        $parts = explode(' - ', $label, 2);
        if (isset($parts[1])) {
            if ($country !== '' && strcasecmp($country, trim($parts[0])) !== 0
                && ! in_array(strtolower(trim($parts[0])), ['uefa', 'europe', 'fifa', 'world', 'international'], true)
                && ! preg_match('/uefa|fifa|friendl/i', $parts[1])) {
                return null;
            }
            [$country, $label] = array_map('trim', $parts);
        }

        return FootballSyncController::pinnacleCompetition($country, $label);
    }

    /** @return list<array{country: string, leagues: array<string, string>}> */
    public function pinnacleMonitorLeagueOptions(): array
    {
        $options = [];
        $groups = config('services.pinnodds.football_priority', []);
        foreach (config('services.pinnodds.tennis_priority', []) as $group => $tournaments) {
            foreach (array_keys($tournaments) as $tournament) {
                $groups[] = [$group, $tournament];
            }
        }
        foreach ($groups as [$country, $league]) {
            $last = array_key_last($options);
            if ($last === null || $options[$last]['country'] !== $country) {
                $options[] = ['country' => $country, 'leagues' => []];
                $last = array_key_last($options);
            }
            $options[$last]['leagues'][self::pinnacleLeagueKey($country, $league)] = $league;
        }

        return $options;
    }

    private static function pinnacleLeagueKey(string $country, string $league): string
    {
        return substr(hash('sha256', mb_strtolower($country.'|'.$league)), 0, 16);
    }

    public static function pinnacleLeague(array $drop): array
    {
        $parent = $drop['archive_parent'] ?? [];
        $competition = $drop['pinnacle_competition'] ?? $parent['pinnacle_competition'] ?? self::pinnacleCompetition($parent + $drop);
        if ($competition !== null) {
            [$country, $league] = $competition;

            return [$country, $league, self::pinnacleLeagueKey($country, $league)];
        }
        $label = trim((string) ($parent['league'] ?? $drop['league'] ?? ''));
        $parts = explode(' - ', $label, 2);
        $country = $parent['country'] ?? $drop['country'] ?? null;
        $country = trim((string) (is_array($country) ? ($country['name'] ?? '') : $country));
        $hasCountryPrefix = isset($parts[1]) && ($country === '' || strcasecmp(trim($parts[0]), $country) === 0);
        $league = $hasCountryPrefix ? trim($parts[1]) : $label;
        $country = $country !== '' ? $country : ($hasCountryPrefix ? trim($parts[0]) : '');
        $key = self::pinnacleLeagueKey($country, $league);

        return [$country, $league, $key];
    }

    public function pinnacleLeagueOptions(?array $drops = null): array
    {
        $catalog = config('services.pinnodds.football_priority', []);
        if ($catalog !== []) {
            $drops = [];
            foreach ($catalog as [$country, $league]) {
                $drops[] = ['country' => $country, 'league' => $league];
            }
        }
        if ($drops === null) {
            try {
                $feed = getInRedis('Pinnacle:drops', readOnly: true) ?? [];
                $drops = $feed['signals'] ?? $feed['alerts'] ?? [];
            } catch (RedisException) {
                $drops = [];
            }
        }
        $options = [];
        foreach ($drops as $drop) {
            [$country, $league, $key] = self::pinnacleLeague($drop);
            if ($league !== '') {
                $options[$country][$key] = $league;
            }
        }
        ksort($options);
        foreach ($options as &$leagues) {
            asort($leagues);
        }

        return $options;
    }

    private function allowsTelegramFilter(Customer $customer, string $type, array $pinnacleFilters): bool
    {
        $pinnacleSelected = ! empty($pinnacleFilters['higher']) || ! empty($pinnacleFilters['shock']) || ! empty($pinnacleFilters['leagues']);
        $selected = $type === 'prematch_drops' ? $pinnacleSelected : $customer->{'tg_'.($type === 'goal' ? 'ltd' : $type)};
        if ($selected) {
            return true;
        }

        return ! $pinnacleSelected && ! array_any(self::FILTERS, fn (string $filter): bool => (bool) $customer->{'tg_'.$filter});
    }

    /** A single existing-to-new Pinnacle quote change; never a sum of separate drops.
     * @param  array<string, mixed>  $drop
     */
    public static function isPinnacleMarketShock(array $drop): bool
    {
        $from = $drop['from_price'] ?? null;
        $to = $drop['to_price'] ?? null;
        if (! is_numeric($from) || ! is_numeric($to) || ! is_finite((float) $from) || ! is_finite((float) $to)
            || (float) $from <= 1 || (float) $to <= 1 || (float) $to >= (float) $from) {
            return false;
        }

        return (float) $to <= (float) $from * 0.92 + PHP_FLOAT_EPSILON * max((float) $from, (float) $to) * 8;
    }

    private function allowsPinnacleDrop(array $filters, array $context, ?float &$betfairBack): bool
    {
        if (empty($filters['higher']) && empty($filters['shock']) && empty($filters['leagues'])) {
            return true;
        }
        $drop = $context['pinnacle_drop'] ?? [];
        if ($drop === [] && isset($context['movement']['pinnacle_alert_key'])) {
            $feed = getInRedis('Pinnacle:drops', readOnly: true) ?? [];
            $drop = array_find($feed['signals'] ?? $feed['alerts'] ?? [], fn (array $item): bool => ($item['alert_key'] ?? null) === $context['movement']['pinnacle_alert_key']) ?? [];
        }
        if (! empty($filters['leagues']) && ($drop === [] || ! in_array(self::pinnacleLeague($drop)[2], $filters['leagues'], true))) {
            return false;
        }
        if (! empty($filters['shock']) && ! self::isPinnacleMarketShock([
            'from_price' => $context['movement']['from'] ?? null,
            'to_price' => $context['movement']['to'] ?? null,
        ])) {
            return false;
        }
        if (empty($filters['higher'])) {
            return true;
        }
        if ($drop === [] || ! is_numeric($drop['to_price'] ?? null) || (float) $drop['to_price'] <= 1) {
            return false;
        }
        $resolved = app(FootballController::class)->marketActivity(['alerts' => [$drop]], currentPinnacleQuote: true);
        $quote = $resolved[0]['bf_back'] ?? null;

        if (! is_numeric($quote) || ! is_finite((float) $quote) || (float) $quote <= (float) $drop['to_price']) {
            return false;
        }

        $betfairBack = (float) $quote;

        return true;
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
                $query->whereNull('subscription_ends_at')->orWhere('subscription_ends_at', '<', Customer::subscriptionDateCutoff());
            })->where(function (Builder $query): void {
                $query->whereNotNull('trial_ends_at')->orWhereNotNull('subscription_ends_at');
            })->lazyById(100)->each(fn (Customer $customer) => $this->process('expired', [], ['expires' => max($customer->trial_ends_at?->timestamp ?? 0, $customer->subscription_ends_at?->timestamp ?? 0)], $customer->id));
        DB::table('alert_deliveries')->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(2))
            ->update(['status' => 'unknown', 'error' => 'Worker stopped before delivery acknowledgement.', 'updated_at' => now()]);
        DB::table('alerts')->whereNull('fanned_out_at')->orderBy('id')->lazyById(100)->each(
            fn (object $alert) => ProcessAlert::dispatch('fanout', ['alert_id' => $alert->id])->onQueue(in_array($alert->type, ['ltd', 'goal'], true) ? 'alerts-ltd' : 'alerts')
        );
        DB::table('alert_deliveries')->join('alerts', 'alerts.id', '=', 'alert_deliveries.alert_id')
            ->select('alert_deliveries.*', 'alerts.type as alert_type')
            ->whereIn('alert_deliveries.status', ['pending', 'retry'])->where('alert_deliveries.updated_at', '<', now()->subMinutes(2))
            ->lazyById(100, 'alert_deliveries.id', 'id')->each(function (object $delivery): void {
                $queue = $delivery->channel === 'platform' ? 'alerts-platform' : (in_array($delivery->alert_type, ['ltd', 'goal'], true) ? 'alerts-ltd' : 'alerts-telegram');
                ProcessAlert::dispatch('deliver', ['delivery_id' => $delivery->id])->onQueue($queue);
            });
    }

    /** @param array<string, mixed> $message */
    public function source(array $message): ?int
    {
        if (($message['source'] ?? '') !== config('services.telegram.source_channel') || empty($message['message_id'])
            || ! is_numeric($message['sent_at'] ?? null) || abs((int) ($message['received_at'] ?? time()) - (int) $message['sent_at']) > 300) {
            return null;
        }
        $clean = static function (string $text): string {
            $lines = preg_split('/\R/u', $text);

            return trim(preg_replace('/[\p{So}\p{Sk}\x{FE0F}\x{200D}]/u', '', implode("\n", array_filter($lines, static fn (string $line): bool => ! preg_match(
                '~https?://|t\.me/|\b(?:1xbet|promo(?:tion|\s*code)?|code\s+pomo|register|registration|bonus|deposit|referral|advertisement)\b~i', $line
            )))));
        };
        $text = $clean((string) ($message['text'] ?? ''));
        $context = trim($text."\n".$clean((string) ($message['reply_text'] ?? '')));
        $goal = preg_match('/(?:^|\n)\s*(?:⚽\s*)?go+a+l+(?:\h+alert)?(?:!+(?=\s|$)|(?=\h*(?:\n|$))|(?=\h+(?:Score:|[^\n]+\h+\d{1,2}\h*[-:]\h*\d{1,2})))/iu', $text) === 1;
        $goal = $goal || preg_match('/^\h*(?:Status\s*:\s*)?(?:Signal\h+)?(?:Won|Paid\s*out)[\h\p{So}\p{Sk}\x{FE0F}\x{200D}!]*$/imu', $text) === 1;
        $hasMinute = preg_match('/\b(\d{1,3}(?:\+\d{1,2})?)\s*[\x{0027}\x{2019}\x{2032}]/u', $text, $minute)
            || preg_match('/\b(?:1st\s*half\s*[-–:]|time\s+elapsed\s*:)\s*(\d{1,2}):[0-5]\d\b/iu', $text, $minute);
        $hasScore = preg_match('~(?<![\d:/-])(\d{1,2})\h*[-:–]\h*(\d{1,2})(?![\d:/-])~u', preg_replace('/^.*(?:1st\s*half\s*[-–:]|time\s+elapsed\s*:|\b\d{1,2}\h+[\p{L}.]+\h+20\d{2}\h*\(\d{1,2}:\d{2}\)|\b\d{1,2}[.\/-]\d{1,2}[.\/-]20\d{2}\h+\d{1,2}:\d{2}).*$/imu', '', $text), $score);
        $settlement = null;
        if (preg_match('/^\h*(?:(?:Status|Signal)\h*:\h*)?(Won|Paid\h*out|Lost|Void|Refunded|Cancelled)[\h!]*$/imu', $text, $result)) {
            $settlement = match (strtolower(preg_replace('/\s+/', '', $result[1]))) {
                'won', 'paidout' => 'won', 'lost' => 'lost', default => 'void',
            };
            $goal = true;
        }
        $signal = TelegramSourceSignal::parse($context);
        if (! $goal && $signal === null) {
            Log::info('Telegram source post did not contain a supported market.', ['message_id' => $message['message_id']]);

            return null;
        }
        if (! $goal && $signal !== null) {
            $placeholderId = DB::table('alerts')->where('type', 'ltd')
                ->where('match_id', 'telegram-source:'.hash('sha256', $message['source'].'|'.$message['message_id']))
                ->where('payload->context->signal->key', $signal['key'])->value('id');
            if ($placeholderId !== null) {
                return (int) $placeholderId;
            }
        }
        $hasTeams = preg_match('/(?:^|\n)\s*(?:⚽\s*)?(?:Match\s*:\s*)?([^\n:]+?)\s+(?:vs\.?|v\.?|—)\s+([^\n]+?)(?=\s+[|@]|\s+-\s+(?:Over|Under)|\n|$)/iu', $context, $teams);
        $fallback = fn (?string $home = null, ?string $away = null, ?string $competition = null): ?int => $this->unresolvedSource($message, $signal, [
            'goal' => $goal, 'settlement' => $settlement, 'home' => $home, 'away' => $away, 'competition' => $competition,
            'score' => $hasScore ? $score[1].' - '.$score[2] : null, 'minute' => $hasMinute ? $minute[1] : null,
            'status' => $hasMinute || preg_match('/^\h*LIVE\b/imu', $text) ? 'Live' : '—',
        ]);
        if (! $hasTeams && $goal) {
            $hasTeams = preg_match('/\bgo+a+l+!?\h+([^\n]+?)\h+\d{1,2}\h*[-:]\h*\d{1,2}\h+([^\n]+?)(?=\h+@|\n|$)/iu', $text, $teams);
        }
        $hasLeague = preg_match('/(?:League|Competition|Tournament)\s*:\s*([^\n|]+)/iu', $context, $league);
        $teamPairs = [];
        if (! $hasTeams) {
            $cardText = preg_replace('/^(\h*[\p{L}][^\n]*?)\n\h*(\((?:Women|W|Men|M)\))(?=\h|\n|$)/imu', '$1 $2', $text);
            $cardText = preg_replace('/^(\h*[\p{L}][\p{L} .\x{2019}-]+)\n\h*(United|City|Town|Athletic|Hotspur)\h+(?=\d{1,2}\h*[:–-]\h*\d{1,2})/imu', '$1 $2 ', $cardText);
            $hasTeams = preg_match('/^\h*([\p{L}][^\n:]+?)\h+\d{1,2}\h*[:–-]\h*\d{1,2}\h+([\p{L}][^\n:]+?)(?:\n([\p{L}][\p{L} .-]+)(?=\n\h*\d))?\h*$/mu', $cardText, $teams);
            if ($hasTeams && isset($teams[3])) {
                $teamPairs[] = [trim($teams[1].' '.$teams[3]), trim($teams[2])];
                $teams[2] .= ' '.$teams[3];
            }
        }
        if (! $hasLeague) {
            $hasLeague = preg_match('/^\h*Football[.\h]+(?:[^\n.]+\.\h+)?([^\n]+)$/mi', $text, $league);
            if ($hasLeague && preg_match('/^(?:Women|Men)\h*\.?$/iu', trim($league[1]))) {
                $hasLeague = preg_match('/^\h*Football[.\h]+([^\n]+)$/mi', $text, $league);
            }
        }
        if (! $hasTeams && $goal && ! empty($message['reply_message_id'])) {
            $replyParents = DB::table('alerts')->where('type', 'ltd')->where('created_at', '>=', now()->subHours(54))
                ->where(function ($query) use ($message): void {
                    $query->where('payload->context->source_message_id', $message['reply_message_id'])
                        ->orWhereJsonContains('payload->context->source_message_ids', $message['reply_message_id']);
                })->get();
            if ($replyParents->count() === 1) {
                $body = json_decode($replyParents->first()->payload, true);
                $names = explode(' vs ', $body['match'] ?? '', 2);
                if (count($names) === 2 && ($body['context']['provider'] ?? '') === 'telegram_source') {
                    $teams = [null, ...$names];
                    $hasTeams = true;
                    $league = [null, $body['league']];
                    $hasLeague = true;
                    $signal ??= $body['context']['signal'] ?? TelegramSourceSignal::parse('1st Half Over 0.5');
                }
            }
        }
        if (! $hasTeams && $goal) {
            Log::info('Telegram source match details could not be parsed.', ['message_id' => $message['message_id'],
                'teams_parsed' => (bool) $hasTeams, 'league_parsed' => (bool) $hasLeague]);

            return $fallback();
        }
        $home = $hasTeams ? trim($teams[1]) : '';
        $away = $hasTeams ? trim($teams[2]) : '';
        $teamPairs[] = [$home, $away];
        $competition = $hasLeague ? trim($league[1]) : null;
        $resolver = app(FootballSyncController::class);
        $womenCompetition = preg_match('/\bwomen\b/iu', $competition ?? '') === 1;
        $normalizeTeam = static function (string $name) use ($womenCompetition): string {
            if ($womenCompetition) {
                $name = preg_replace('/\bl\.\h*f\.\h*c\.?(?=\h|$)/iu', '', $name);
                $name = trim(preg_replace('/(?:\(\h*(?:women|w)\h*\)|\b(?:women|w)\b)\h*$/iu', '', $name));
                if (mb_strtolower($name) === 'ol lyonnes') {
                    $name = 'Lyon';
                }
            }

            return $name;
        };
        $normalizeLeague = static function (string $name) use ($womenCompetition): string {
            $name = match (strtolower(trim($name))) {
                'english premier league', 'england premier league' => 'Premier League',
                'italian serie a' => 'Serie A',
                'german bundesliga' => 'Bundesliga',
                'french ligue 1', 'france ligue 1' => 'Ligue 1',
                default => trim(preg_replace('/^[^-]+ - /u', '', $name)),
            };
            if ($womenCompetition) {
                $name = preg_replace('/\bwomen[\x{0027}\x{2019}]s\b/iu', 'women', mb_strtolower($name));
                $words = preg_split('/\s+/u', trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name)));
                sort($words);
                $name = implode(' ', $words);
            }

            return $name;
        };
        $matchesCompetition = static function (string $name) use ($competition, $normalizeLeague, $resolver): bool {
            if ($competition === null) {
                return true;
            }
            $candidate = $normalizeLeague($name);
            $reference = $normalizeLeague($competition);
            preg_match_all('/[0-9]+/', $candidate, $candidateNumbers);
            preg_match_all('/[0-9]+/', $reference, $referenceNumbers);

            return $candidateNumbers[0] === $referenceNumbers[0] && $resolver->teamSimilarity($candidate, $reference) >= 80;
        };
        $requiredSimilarity = ! $goal && ! $hasLeague ? 100 : 80;
        $availableFixtures = FootballMatch::query()->whereIn('result_status', $goal ? ['Live', 'Prematch', 'Finished'] : ['Live', 'Prematch'])
            ->whereBetween('start_timestamp', [time() - 6 * 3600, time() + 48 * 3600])
            ->get(['id', 'sports_api_pro_match_id', 'betfair_match_id', 'team1', 'team2', 'tournament', 'unique_key', 'start_timestamp']);
        $candidates = $availableFixtures->filter(fn (FootballMatch $match): bool => (! $womenCompetition || preg_match('/\bwomen\b/iu', $match->tournament ?? '') === 1)
                && array_any($teamPairs, static fn (array $pair): bool => FootballFixtureNames::similarity($normalizeTeam($match->team1), $normalizeTeam($pair[0])) >= $requiredSimilarity
                    && FootballFixtureNames::similarity($normalizeTeam($match->team2), $normalizeTeam($pair[1])) >= $requiredSimilarity)
                && $matchesCompetition($match->tournament ?? ''));
        $cardCandidates = $goal ? collect() : $availableFixtures->filter(function (FootballMatch $match) use ($text, $womenCompetition, $matchesCompetition): bool {
            if (($womenCompetition && preg_match('/\bwomen\b/iu', $match->tournament ?? '') !== 1) || ! $matchesCompetition($match->tournament ?? '')) {
                return false;
            }
            $homeScore = FootballFixtureNames::mentionScore($text, $match->team1);
            $awayScore = FootballFixtureNames::mentionScore($text, $match->team2);

            return ($homeScore === 100 && $awayScore >= 50) || ($awayScore === 100 && $homeScore >= 50);
        });
        $candidates = $candidates->merge($cardCandidates)->unique('id');
        $anchor = $candidates->first();
        $conflictingTeams = (count($teamPairs) > 1 || $cardCandidates->isNotEmpty()) && $anchor !== null && $candidates->contains(static fn (FootballMatch $match): bool => FootballFixtureNames::similarity($normalizeTeam($match->team1), $normalizeTeam($anchor->team1)) < 80
            || FootballFixtureNames::similarity($normalizeTeam($match->team2), $normalizeTeam($anchor->team2)) < 80);
        $ambiguousCompetition = ! $goal && ! $hasLeague && $candidates->map(static fn (FootballMatch $match): string => mb_strtolower($normalizeLeague($match->tournament ?? '')))->unique()->count() !== 1;
        if ($candidates->isEmpty() || $conflictingTeams || $ambiguousCompetition || $candidates->max('start_timestamp') - $candidates->min('start_timestamp') > 900) {
            Log::info('Telegram source fixture could not be resolved uniquely.', ['message_id' => $message['message_id'], 'matches' => $candidates->count(),
                'home' => $home, 'away' => $away, 'competition' => $competition, 'ocr_excerpt' => mb_substr($text, 0, 1500)]);

            return $fallback($home, $away, $competition);
        }
        $candidates = $candidates->sortByDesc(static fn (FootballMatch $match): int => (filled($match->sports_api_pro_match_id) ? 2 : 0) + (filled($match->betfair_match_id) ? 1 : 0));
        $record = $candidates->first()->refresh();
        if ($cardCandidates->isNotEmpty() && (! $hasTeams || FootballFixtureNames::similarity($normalizeTeam($record->team1), $normalizeTeam($home)) < $requiredSimilarity
            || FootballFixtureNames::similarity($normalizeTeam($record->team2), $normalizeTeam($away)) < $requiredSimilarity)) {
            Log::info('Telegram source fixture recovered from card text.', ['message_id' => $message['message_id'],
                'home' => $record->team1, 'away' => $record->team2, 'competition' => $record->tournament]);
        }
        if (! $goal && ! $hasLeague) {
            Log::info('Telegram source fixture resolved without OCR league.', ['message_id' => $message['message_id'],
                'home' => $record->team1, 'away' => $record->team2, 'competition' => $record->tournament]);
        }
        $oddsRecord = $candidates->first(static fn (FootballMatch $match): bool => filled($match->betfair_match_id));
        $match = array_replace($record->payload, ['team1' => $record->team1, 'team2' => $record->team2,
            'tournament' => $record->tournament, 'timestamp' => (int) $record->start_timestamp, 'result_status' => $record->result_status]);
        $matchId = filled($record->sports_api_pro_match_id) ? $record->sports_api_pro_match_id : ($oddsRecord === null ? null : 'betfair:'.$oddsRecord->betfair_match_id);
        unset($match['internal_backup']);
        if ($matchId === null) {
            return $fallback($record->team1, $record->team2, $record->tournament);
        }
        if (! $goal && MatchBackup::isHidden($match, 'football')) {
            return null;
        }
        $match['sportsApiPro_match_id'] = $matchId;
        if ($goal) {
            $parentMatchIds = [(string) $matchId];
            if ($oddsRecord !== null) {
                $parentMatchIds[] = 'betfair:'.$oddsRecord->betfair_match_id;
            }
            $parents = DB::table('alerts')->where('type', 'ltd')->whereIn('match_id', $parentMatchIds)->orderByDesc('id')->get()
                ->filter(function (object $alert) use ($signal, $message): bool {
                    $payload = json_decode($alert->payload, true);
                    if (($payload['context']['provider'] ?? '') !== 'telegram_source') {
                        return false;
                    }
                    $parentSignal = $payload['context']['signal'] ?? TelegramSourceSignal::parse('1st Half Over 0.5');
                    if ($signal !== null) {
                        return $parentSignal['key'] === $signal['key'];
                    }
                    if (! empty($message['reply_message_id'])) {
                        return in_array($message['reply_message_id'], $payload['context']['source_message_ids'] ?? [$payload['context']['source_message_id'] ?? null], true);
                    }

                    return $parentSignal['is_ltd'];
                });
            if ($parents->count() !== 1) {
                Log::info('Telegram source result parent could not be resolved uniquely.', ['message_id' => $message['message_id'], 'matching_parents' => $parents->count()]);

                return null;
            }
            $parent = $parents->first();
            $match['sportsApiPro_match_id'] = $parent->match_id;
            $parentPayload = json_decode($parent->payload, true);
            $parentSignal = $parentPayload['context']['signal'] ?? TelegramSourceSignal::parse('1st Half Over 0.5');
            if ($settlement === null) {
                if (! $hasScore || ! $hasMinute || ($parentSignal['is_ltd'] && ((! str_starts_with($minute[1], '45+') && (int) $minute[1] > 45)
                    || preg_match('/^\h*(?:(?:Status|Period|Half)\h*:\h*)?(?:2nd\h*half|second\h*half|full\h*time|half\h*time)\b/imu', $text)))) {
                    return null;
                }
                $previous = explode(' - ', $parentPayload['score'] ?? '0 - 0');
                if ((int) $score[1] < (int) ($previous[0] ?? 0) || (int) $score[2] < (int) ($previous[1] ?? 0)
                    || (int) $score[1] + (int) $score[2] <= (int) ($previous[0] ?? 0) + (int) ($previous[1] ?? 0)) {
                    return null;
                }
            }
            $id = $this->process('goal', $match, ['provider' => 'telegram_source', 'signal' => $parentSignal,
                'parent_alert_id' => $parent->id, 'source_message_id' => $message['message_id'],
                'result_status' => $settlement ?? ($parentSignal['is_ltd'] ? 'won' : 'goal'),
                'score' => $hasScore ? $score[1].' - '.$score[2] : ($match['home_score'] ?? 0).' - '.($match['away_score'] ?? 0),
                'minute' => $hasMinute ? $minute[1] : null]);
            Log::info('Telegram source goal processed.', ['message_id' => $message['message_id'], 'alert_id' => $id]);

            return $id;
        }
        $drawRecord = $oddsRecord ?? $record;
        $drawRecord->load(['markets' => fn ($query) => $query->where('market_type', 'HALF_TIME')]);
        $draws = $drawRecord->markets->filter(fn ($runner): bool => in_array(strtolower(trim($runner->runner ?? '')), ['the draw', 'draw', 'x'], true)
            && $runner->lay_odds > 1 && $runner->lay_odds <= 1000 && empty($runner->payload['suspended']) && ! in_array($runner->payload['market_status'] ?? 'OPEN', ['SUSPENDED', 'CLOSED'], true) && $runner->updated_at->gt(now()->subMinutes(2)));
        $context = ['provider' => 'telegram_source', 'signal' => $signal, 'source_message_id' => $message['message_id'],
            'match_status' => $match['result_status'] ?? $record->result_status,
            'betfair_match_id' => $oddsRecord?->betfair_match_id,
            'betfair_back' => $oddsRecord === null ? null : $this->sourceSignalBack($oddsRecord, $signal),
            'draw_lay' => $signal['is_ltd'] && $draws->count() === 1 ? $draws->first()->lay_odds : null];
        if ($hasMinute && $context['match_status'] === 'Live') {
            $context['minute'] = $minute[1];
        }
        if ($hasScore && $context['match_status'] === 'Live') {
            $context['score'] = $score[1].' - '.$score[2];
        }
        $id = $this->process('ltd', $match, $context);
        Log::info('Telegram source LTD processed.', ['message_id' => $message['message_id'], 'alert_id' => $id, 'signal' => $signal['label']]);

        return $id;
    }

    /** @param array<string, mixed> $message @param array<string, mixed>|null $signal @param array<string, mixed> $details */
    private function unresolvedSource(array $message, ?array $signal, array $details): ?int
    {
        if (! config('services.telegram.allow_unresolved_source')) {
            return null;
        }
        if (! $details['goal'] && $signal !== null) {
            $existingId = DB::table('alerts')->where('type', 'ltd')->where('payload->context->provider', 'telegram_source')
                ->where('payload->context->signal->key', $signal['key'])->where(function ($query) use ($message): void {
                    $query->where('payload->context->source_message_id', $message['message_id'])
                        ->orWhereJsonContains('payload->context->source_message_ids', $message['message_id']);
                })->value('id');
            if ($existingId !== null) {
                return (int) $existingId;
            }
        }
        $parent = null;
        $parentPayload = [];
        if ($details['goal']) {
            if (empty($message['reply_message_id'])) {
                return null;
            }
            $parents = DB::table('alerts')->where('type', 'ltd')->where('created_at', '>=', now()->subHours(54))
                ->where('payload->context->provider', 'telegram_source')->where(function ($query) use ($message): void {
                    $query->where('payload->context->source_message_id', $message['reply_message_id'])
                        ->orWhereJsonContains('payload->context->source_message_ids', $message['reply_message_id']);
                })->limit(2)->get();
            if ($parents->count() !== 1) {
                return null;
            }
            $parent = $parents->first();
            $parentPayload = json_decode($parent->payload, true);
            $parentSignal = $parentPayload['context']['signal'] ?? null;
            if ($parentSignal === null || ($signal !== null && $signal['key'] !== $parentSignal['key'])) {
                return null;
            }
            $signal = $parentSignal;
            if ($details['settlement'] === null && $signal['is_ltd'] && $details['minute'] !== null
                && ! str_starts_with($details['minute'], '45+') && (int) $details['minute'] > 45) {
                return null;
            }
        }
        if ($signal === null) {
            return null;
        }
        $name = static function (?string $value): string {
            if ($value === null || ! preg_match('/[\p{L}]{2}/u', $value)) {
                return '—';
            }

            return trim(preg_replace('/[^\p{L}\p{N}\h().\x{0027}\x{2019}-]/u', '', $value));
        };
        $names = isset($parentPayload['match']) ? explode(' vs ', $parentPayload['match'], 2) : [];
        $match = ['sportsApiPro_match_id' => $parent?->match_id ?? 'telegram-source:'.hash('sha256', $message['source'].'|'.$message['message_id']),
            'team1' => $names[0] ?? $name($details['home']), 'team2' => $names[1] ?? $name($details['away']),
            'tournament' => $parentPayload['league'] ?? ($details['competition'] ?: '—'), 'result_status' => $details['status']];
        $context = ['provider' => 'telegram_source', 'signal' => $signal, 'source_message_id' => $message['message_id'],
            'unresolved_fixture' => true, 'match_status' => $details['status'], 'score' => $details['score'], 'minute' => $details['minute']];
        if ($parent !== null) {
            $context['parent_alert_id'] = $parent->id;
            $context['score'] ??= '—';
            $context['result_status'] = $details['settlement'] ?? 'goal';
            if ($signal['is_ltd'] && $details['settlement'] === null && $details['minute'] !== null && $details['score'] !== null
                && is_string($parentPayload['score'] ?? null) && preg_match('/^[0-9]+ - [0-9]+$/', $parentPayload['score'])) {
                $previous = array_map('intval', explode(' - ', $parentPayload['score']));
                $current = array_map('intval', explode(' - ', $details['score']));
                if ($current[0] >= ($previous[0] ?? 0) && $current[1] >= ($previous[1] ?? 0) && array_sum($current) > array_sum($previous)) {
                    $context['result_status'] = 'won';
                }
            }
        }
        $id = $this->process($parent === null ? 'ltd' : 'goal', $match, $context);
        Log::info('Telegram source temporary placeholder alert processed.', ['message_id' => $message['message_id'], 'alert_id' => $id]);

        return $id;
    }

    /** @param array<string, mixed> $match @param array<string, mixed> $context */
    public function process(string $type, array $match, array $context = [], ?int $customerId = null): ?int
    {
        $sourceSignal = ($context['provider'] ?? null) === 'telegram_source' && isset($context['signal']['key']);
        if (! config('services.telegram.enabled') || ! in_array($type, [...self::FILTERS, 'prematch_drops', 'market_shock', 'goal', 'test', 'expired'], true)) {
            return null;
        }
        $pinnacle = $context['pinnacle'] ?? null;
        $matchId = $match['sportsApiPro_match_id'] ?? null;
        if ($pinnacle !== null) {
            if ($type !== 'prematch_drops' || ! is_array($pinnacle) || empty($pinnacle['id']) || empty($pinnacle['home']) || empty($pinnacle['away'])
                || empty($pinnacle['alert_key']) || ! self::allowsPinnacleMarket($pinnacle)
                || ($pinnacle['is_live'] ?? false) || ($pinnacle['feed'] ?? '') === 'live') {
                return null;
            }
            $from = (float) ($pinnacle['from_price'] ?? 0);
            $to = (float) ($pinnacle['to_price'] ?? 0);
            $at = (int) ($pinnacle['alerted'] ?? 0);
            if (! is_finite($from) || ! is_finite($to) || $from <= 1 || $to <= 1 || round(($from - $to) / $from * 100, 8) < 5
                || $at < time() - 10800 || $at > time() + 30) {
                return null;
            }
            $sport = strtolower((string) ($pinnacle['sport'] ?? 'other'));
            $matchId = 'pinnacle:'.$sport.':'.$pinnacle['id'];
            $starts = $pinnacle['starts'] ?? $pinnacle['start'] ?? null;
            $starts = is_numeric($starts) ? (int) $starts : (is_string($starts) ? (int) strtotime($starts) : 0);
            if ($starts > 100_000_000_000) {
                $starts = intdiv($starts, 1000);
            }
            $match = ['team1' => $pinnacle['home'], 'team2' => $pinnacle['away'], 'tournament' => $pinnacle['league'] ?? null,
                'timestamp' => $starts > 0 ? $starts : null, 'result_status' => 'Prematch'];
            $context = ['provider' => 'pinnacle', 'sport' => $sport, 'pinnacle_event_id' => $pinnacle['id'],
                'pinnacle_drop' => array_intersect_key($pinnacle, array_flip(['id', 'sport', 'home', 'away', 'country', 'league', 'starts', 'start', 'parent_id', 'archive_parent', 'pinnacle_competition', 'sect', 'outcome', 'point', 'points', 'hdp', 'period', 'to_price'])),
                'movement' => [
                    'market' => $pinnacle['sect'] ?? '', 'selection' => $pinnacle['outcome'] ?? '',
                    'point' => $pinnacle['point'] ?? $pinnacle['points'] ?? $pinnacle['hdp'] ?? null, 'period' => $pinnacle['period'] ?? 0,
                    'from' => $from, 'to' => $to, 'at' => $at, 'sport' => $sport, 'pinnacle_alert_key' => $pinnacle['alert_key'],
                ]];
        }
        $visibleMatch = $match;
        if ($sourceSignal) {
            unset($visibleMatch['internal_backup']);
        }
        if (! in_array($type, ['test', 'expired'], true)) {
            if ($pinnacle === null && (empty($matchId) || empty($match['team1']) || empty($match['team2']) || empty($match['tournament'])
                || (! ($sourceSignal && $type === 'goal') && MatchBackup::isHidden($visibleMatch, 'football')))) {
                return null;
            }
            $predicate = $pinnacle === null && ! $sourceSignal ? $this->footballFilterPredicate($type) : null;
            if ($predicate !== null && ! $predicate($match)) {
                return null;
            }
            if ($type === 'ltd' && ! $sourceSignal && (($match['result_status'] ?? '') !== 'Live' || (($context['provider'] ?? null) !== 'telegram_source' && ((float) ($context['draw_lay'] ?? 0) <= 1 || (float) ($context['draw_lay'] ?? 0) > 1000)))) {
                return null;
            }
        } elseif ($customerId === null) {
            return null;
        }
        $minute = $context['minute'] ?? (isset($match['timestamp']) ? $this->calculateMinutes($match['minutes_status'] ?? null, (int) $match['timestamp'], $match['result_status'], $match['added_time'] ?? [], $match['current_period_start_timestamp'] ?? null) : null);
        if ($sourceSignal && ($match['result_status'] ?? '') === 'Prematch') {
            $minute = null;
        }
        if ($sourceSignal && ! preg_match('/^\d{1,3}(?:\+\d{1,2})?$/', (string) $minute)) {
            $minute = null;
        }
        if ($type === 'ltd' && ! $sourceSignal && ($minute === null || (! str_starts_with((string) $minute, '45+') && (! is_numeric($minute) || (int) $minute > 45)))) {
            return null;
        }
        $score = $context['score'] ?? (isset($match['home_score'], $match['away_score']) ? $match['home_score'].' - '.$match['away_score'] : null);
        if ($type === 'fav_losing') {
            $context['bet365_live_odds'] = $this->favoriteLiveBet365Odds((string) $matchId, $score, $match['bet365_prematch']['favorite_side'] ?? null);
            if ($context['bet365_live_odds'] === null) {
                return null;
            }
        }
        if ($type === 'ltd' && ! $sourceSignal && ($score === null || ! in_array($match['minutes_status'] ?? '', ['Live', '1st half'], true)
            || (($match['minutes_status'] ?? '') === 'Live' && time() - (int) $match['timestamp'] > 3600))) {
            return null;
        }
        $trigger = match ($type) {
            'test' => $context['request_id'] ?? null,
            'expired' => $context['expires'] ?? null,
            'goal' => $sourceSignal ? implode('|', [$context['parent_alert_id'] ?? '', $context['result_status'] ?? 'goal', $score]) : $score,
            'ltd' => $sourceSignal ? $context['signal']['key'] : 'first',
            'fav_losing' => $score,
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
                        'point' => $runner['point'] ?? null, 'period' => $runner['period'] ?? 0,
                        'sport' => 'soccer', 'pinnacle_alert_key' => $runner['alert_key'] ?? null,
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
            if (in_array($type, ['prematch_drops', 'market_shock'], true)) {
                $market = strtolower(str_replace('_', ' ', trim((string) ($movement['market'] ?? ''))));
                $line = $movement['point'] ?? null;
                if (! is_numeric($line) && preg_match('/(?:over|under|total|o\/u)\s*([0-9]+(?:\.[0-9]+)?)/i', $market.' '.($movement['selection'] ?? ''), $lineMatch)) {
                    $line = $lineMatch[1];
                }
                if ((int) ($movement['period'] ?? 0) !== 0 || (! in_array($market, self::MAIN_MARKETS, true)
                    && ! (preg_match('/^(total|totals|o\/u)(?:\s|$)/', $market) === 1 && is_numeric($line) && in_array((float) $line, [0.5, 1.5, 2.5, 3.5, 4.5], true)))) {
                    return null;
                }
            }
            $trigger = implode('|', [$movement['market'], $movement['selection'], $movement['at']]);
        }
        if ($type === 'goal' && (empty($context['parent_alert_id']) || $score === null || (! $sourceSignal && $minute === null)
            || ! DB::table('alerts')->where('id', $context['parent_alert_id'])->where('type', 'ltd')->where('match_id', (string) $match['sportsApiPro_match_id'])->exists())) {
            return null;
        }
        if ($trigger === null) {
            return null;
        }
        $pinnacleAlertKey = $context['movement']['pinnacle_alert_key'] ?? null;
        $eventMatchId = $sourceSignal && $type === 'ltd' && filled($context['betfair_match_id'] ?? null)
            ? 'betfair:'.$context['betfair_match_id'] : $matchId;
        if ($sourceSignal && $type === 'goal') {
            $eventMatchId = 'source-parent:'.$context['parent_alert_id'];
        }
        $key = hash('sha256', $type === 'prematch_drops' && is_string($pinnacleAlertKey) && $pinnacleAlertKey !== ''
            ? implode('|', [$type, 'pinnacle', $context['movement']['sport'] ?? 'soccer', $pinnacleAlertKey])
            : implode('|', [$type, $eventMatchId ?? $customerId, $trigger]));
        if ($sourceSignal && $type === 'ltd' && $eventMatchId !== $matchId) {
            $existingSource = DB::table('alerts')->where('event_key', hash('sha256', implode('|', [$type, $matchId, $trigger])))->first();
            if ($existingSource !== null) {
                $key = $existingSource->event_key;
            }
        }
        if ($sourceSignal && $type === 'ltd' && $context['signal']['is_ltd']) {
            $legacy = DB::table('alerts')->where('event_key', hash('sha256', implode('|', ['ltd', $matchId, 'first'])))->first();
            if ($legacy !== null && (json_decode($legacy->payload, true)['context']['provider'] ?? '') === 'telegram_source') {
                $key = $legacy->event_key;
            }
        }
        $payload = [
            'alert_type' => $type, 'match_id' => $matchId,
            'match' => isset($match['team1']) ? (($context['unresolved_fixture'] ?? false) && $match['team1'] === '—' && $match['team2'] === '—' ? '—' : $match['team1'].' vs '.$match['team2']) : null,
            'league' => $match['tournament'] ?? null, 'score' => $score, 'minute' => $minute,
            'kickoff' => isset($match['timestamp']) ? date('c', (int) $match['timestamp']) : null,
            'customer_id' => $customerId, 'context' => $context,
            'favorite' => $match['bet365_prematch'] ?? [], 'markets' => array_intersect_key($match['markets'] ?? [], array_flip(['MATCH_ODDS', 'HALF_TIME'])),
        ];
        $payload['templates'] = [
            'en' => ['title' => $type === 'goal' ? 'GOAL! | BF MARKETS UPDATE' : 'BF MARKETS | LTD ALERT', 'strategy_label' => self::STRATEGY['en'], 'score_label' => $type === 'goal' ? 'New Score' : 'Score', 'minute_label' => 'min', 'odds_label' => '1st Half Draw Odds', 'view_btn' => 'View', 'mute_btn' => "Don't show again", 'status_label' => $type === 'goal' ? self::STRATEGY['en'].' Successful!' : 'LIVE', 'half_label' => '1st Half'],
            'ka' => ['title' => $type === 'goal' ? 'გოლი! | BF MARKETS UPDATE' : 'BF MARKETS | LTD ALERT', 'strategy_label' => self::STRATEGY['ka'], 'score_label' => $type === 'goal' ? 'ახალი ანგარიში' : 'ანგარიში', 'minute_label' => 'წუთი', 'odds_label' => '1-ლი ტაიმის Draw Odds', 'view_btn' => 'ნახვა', 'mute_btn' => 'აღარ ჩამაჩვენო', 'status_label' => $type === 'goal' ? self::STRATEGY['ka'].' წარმატებულია!' : 'LIVE', 'half_label' => '1-ლი ტაიმი'],
        ];
        if ($sourceSignal) {
            foreach (['en', 'ka'] as $locale) {
                $signal = $context['signal'];
                $result = $context['result_status'] ?? null;
                $label = $signal['is_ltd'] ? self::STRATEGY[$locale] : ($locale === 'ka' ? $signal['label_ka'] : $signal['label']);
                $payload['templates'][$locale]['strategy_label'] = $label;
                $payload['templates'][$locale]['status_label'] = $type === 'goal'
                    ? ($locale === 'ka' ? ['won' => 'მოგებულია', 'lost' => 'წაგებულია', 'void' => 'გაუქმებულია', 'goal' => 'გოლი დაფიქსირდა'][$result] : ucfirst($result))
                    : (($match['result_status'] ?? '') === 'Prematch' ? ($locale === 'ka' ? 'მატჩამდე' : 'PREMATCH') : (($match['result_status'] ?? '') === 'Live' ? 'LIVE' : '—'));
                $payload['templates'][$locale]['odds_label'] = $locale === 'ka' ? 'Betfair Back კოეფიციენტი' : 'Betfair Back Odds';
                $payload['templates'][$locale]['half_label'] = $signal['period'] === 1 ? ($locale === 'ka' ? '1-ლი ტაიმი' : '1st Half')
                    : ($signal['period'] === 2 ? ($locale === 'ka' ? 'მე-2 ტაიმი' : '2nd Half') : ($locale === 'ka' ? 'სრული მატჩი' : 'Full Time'));
                if (! $signal['is_ltd'] || in_array($result, ['lost', 'void'], true)) {
                    $payload['templates'][$locale]['title'] = $type === 'goal' ? 'BF MARKETS | SIGNAL UPDATE' : 'BF MARKETS | SOURCE SIGNAL';
                }
            }
        }
        DB::table('alerts')->insertOrIgnore(['event_key' => $key, 'type' => $type, 'match_id' => $payload['match_id'],
            'parent_alert_id' => $context['parent_alert_id'] ?? null, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
        $alert = DB::table('alerts')->where('event_key', $key)->first();
        if ($sourceSignal && isset($context['source_message_id'])) {
            DB::transaction(function () use ($alert, $context): void {
                $stored = DB::table('alerts')->where('id', $alert->id)->lockForUpdate()->first();
                $body = json_decode($stored->payload, true);
                $ids = $body['context']['source_message_ids'] ?? [$body['context']['source_message_id'] ?? $context['source_message_id']];
                $body['context']['source_message_ids'] = array_values(array_unique([...$ids, $context['source_message_id']]));
                DB::table('alerts')->where('id', $alert->id)->update(['payload' => json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
            });
        }
        if ($alert->fanned_out_at === null) {
            ProcessAlert::dispatch('fanout', ['alert_id' => $alert->id])->onQueue(in_array($type, ['ltd', 'goal'], true) ? 'alerts-ltd' : 'alerts')->afterCommit();
        }

        return $alert->id;
    }

    public function fanout(int $alertId): int
    {
        $alert = DB::table('alerts')->find($alertId);
        if ($alert === null || $alert->fanned_out_at !== null) {
            return 0;
        }
        if ($alert->type === 'goal' && DB::table('alerts')->where('id', $alert->parent_alert_id)->whereNull('fanned_out_at')->exists()) {
            return 5;
        }
        $waitingForParent = $alert->type === 'goal' && DB::table('alert_deliveries')->where('alert_id', $alert->parent_alert_id)->whereIn('status', ['pending', 'retry', 'sending'])->exists();
        $payload = json_decode($alert->payload, true);
        $pinnacle = $alert->type === 'prematch_drops' && ($payload['context']['provider'] ?? null) === 'pinnacle';
        $pinnacleDrop = ($payload['context']['pinnacle_drop'] ?? []) + ['sport' => $payload['context']['sport'] ?? $payload['context']['movement']['sport'] ?? '',
            'sect' => $payload['context']['movement']['market'] ?? ''];
        if ($alert->type === 'market_shock' || ($alert->type === 'prematch_drops' && ! $pinnacle)
            || ($pinnacle && ! self::allowsPinnacleMarket($pinnacleDrop))) {
            DB::table('alerts')->where('id', $alertId)->update(['fanned_out_at' => now()]);

            return 0;
        }
        foreach (['telegram', 'platform'] as $channel) {
            if ($channel === 'platform' && ! in_array($alert->type, ['ltd', 'goal'], true)) {
                continue;
            }
            $query = Customer::query()->where('is_verified', true)->where('is_blocked', false)->where('is_suspended', false);
            if ($channel === 'telegram') {
                $query->where('telegram_available', true)->whereNotNull('telegram_chat_id');
            } else {
                $query->where('popup_enabled', true);
            }
            if (in_array($alert->type, ['test', 'expired'], true)) {
                $query->whereKey($payload['customer_id']);
            }
            if ($alert->type !== 'expired') {
                $query->where(function (Builder $eligible): void {
                    $eligible->where('admin_free_days', -1)->orWhere('trial_ends_at', '>', now())->orWhere(function (Builder $paid): void {
                        $paid->where('subscription_ends_at', '>=', Customer::subscriptionDateCutoff())->where(fn (Builder $start) => $start->whereNull('subscription_starts_at')->orWhere('subscription_starts_at', '<=', now()));
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
            $filterTelegram = $channel === 'telegram' && ($pinnacle || in_array($alert->type, [...self::FILTERS, 'goal'], true));
            $columns = ['id', 'telegram_chat_id'];
            if ($filterTelegram) {
                foreach (self::FILTERS as $filter) {
                    $columns[] = 'tg_'.$filter;
                }
            }
            $query->select($columns)->chunkById(100, function ($customers) use ($alert, $channel, $filterTelegram): void {
                if ($filterTelegram) {
                    $keys = $customers->mapWithKeys(fn (Customer $customer): array => [$customer->id => 'customer.pinnacle_filters.'.$customer->id]);
                    $preferences = DB::table('site_settings')->whereIn('key', $keys->values()->all())->pluck('value', 'key');
                    $customers = $customers->filter(fn (Customer $customer): bool => $this->allowsTelegramFilter(
                        $customer, $alert->type, json_decode($preferences[$keys[$customer->id]] ?? '', true) ?? [],
                    ));
                }
                foreach ($customers as $customer) {
                    DB::table('alert_deliveries')->insertOrIgnore(['alert_id' => $alert->id, 'customer_id' => $customer->id, 'channel' => $channel,
                        'destination' => $channel === 'telegram' ? $customer->telegram_chat_id : null, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                    $id = DB::table('alert_deliveries')->where(['alert_id' => $alert->id, 'customer_id' => $customer->id, 'channel' => $channel])->value('id');
                    ProcessAlert::dispatch('deliver', ['delivery_id' => $id])->onQueue($channel === 'platform' ? 'alerts-platform' : (in_array($alert->type, ['ltd', 'goal'], true) ? 'alerts-ltd' : 'alerts-telegram'))->afterCommit();
                }
            });
        }
        if ($waitingForParent) {
            return 5;
        }
        DB::table('alerts')->where('id', $alertId)->update(['fanned_out_at' => now()]);

        return 0;
    }

    private function favoriteLiveBet365Odds(string $matchId, ?string $score, ?string $side): ?float
    {
        $selection = match ($side) {
            'home' => '1',
            'away' => '2',
            default => null,
        };
        if ($score === null || $selection === null) {
            return null;
        }
        try {
            $snapshot = Cache::store('redis')->get('Football:bet365:live:'.$matchId, []);
        } catch (RedisException) {
            return null;
        }
        if (($snapshot['provider_id'] ?? null) !== 1 || ($snapshot['score'] ?? null) !== $score || ($snapshot['updated_at'] ?? 0) < microtime(true) - 120) {
            return null;
        }
        $market = array_find($snapshot['markets'] ?? [], static fn (array $market): bool => (int) ($market['market_id'] ?? 0) === 1
            && in_array(strtolower((string) ($market['period'] ?? 'full-time')), ['full-time', 'full time'], true));
        if ($market === null || ($market['suspended'] ?? true)) {
            return null;
        }
        $choice = array_find($market['choices'] ?? [], static fn (array $choice): bool => (string) ($choice['name'] ?? '') === $selection);
        $price = $choice['decimal_odds'] ?? null;

        return ($choice['quote_score'] ?? null) === $score && is_numeric($price) && is_finite((float) $price) && (float) $price > 1
            ? (float) $price : null;
    }

    /** @param array<string, mixed> $signal */
    private function sourceSignalBack(FootballMatch $fixture, array $signal): ?float
    {
        $quotes = $fixture->markets()->where('market_type', $signal['market_type'])->get()->filter(function ($row) use ($fixture, $signal): bool {
            $quote = $row->payload;
            $price = $quote['backOdds'] ?? $quote['currentBackOdds'] ?? null;
            $size = $quote['backSize'] ?? $row->back_size;
            $observed = $quote['liquidity_updated_at'] ?? $quote['odds_history_processed_at'] ?? $row->updated_at?->timestamp;
            if (! is_numeric($price) || ! is_finite((float) $price) || $price <= 1 || $price > 1000
                || ! is_numeric($size) || $size <= 0 || ! is_numeric($observed) || $observed < microtime(true) - 75 || $observed > microtime(true) + 5
                || ! empty($quote['suspended']) || in_array($quote['market_status'] ?? 'OPEN', ['SUSPENDED', 'CLOSED'], true)) {
                return false;
            }
            $name = trim((string) ($quote['runner'] ?? $row->runner));
            if ($signal['family'] === 'total_goals') {
                return preg_match('/^'.preg_quote($signal['selection'], '/').'\\h*'.preg_quote((string) $signal['points'], '/').'(?:\\h*Goals?)?$/iu', $name) === 1;
            }
            if ($signal['family'] === 'btts') {
                return strcasecmp($name, $signal['selection']) === 0;
            }
            if ($signal['selection'] === 'draw') {
                return in_array(strtolower($name), ['draw', 'the draw', 'x'], true);
            }

            return app(FootballSyncController::class)->teamSimilarity($name, $signal['selection'] === 'home' ? $fixture->team1 : $fixture->team2) >= 80;
        });

        return $quotes->count() === 1 ? (float) ($quotes->first()->payload['backOdds'] ?? $quotes->first()->payload['currentBackOdds']) : null;
    }

    /** @return array{source: string, market_id: string, home: float, draw: float, away: float}|null */
    private function betfairMatchOdds(string $matchId, ?string $score): ?array
    {
        $fixtures = FootballMatch::query()->where('sports_api_pro_match_id', $matchId)->whereNotNull('betfair_match_id')
            ->with(['markets' => fn ($query) => $query->where('market_type', 'MATCH_ODDS')])->get();
        if ($fixtures->count() !== 1) {
            return null;
        }
        $fixture = $fixtures->first();
        $match = $fixture->payload;
        if (blank($fixture->betfair_match_id) || ($match['result_status'] ?? '') !== 'Live' || $score === null
            || (($match['home_score'] ?? null).' - '.($match['away_score'] ?? null)) !== $score) {
            return null;
        }
        $resolver = app(FootballSyncController::class);
        $books = [];
        foreach ($fixture->markets->groupBy('market_id') as $marketId => $runners) {
            if (blank($marketId) || $runners->count() !== 3) {
                continue;
            }
            $prices = [];
            foreach ($runners as $runner) {
                $quote = $runner->payload;
                $price = $quote['backOdds'] ?? null;
                $size = $quote['backSize'] ?? null;
                $updated = $quote['liquidity_updated_at'] ?? null;
                if (($quote['marketType'] ?? '') !== 'MATCH_ODDS' || ($quote['liquidity_feed'] ?? '') !== 'live'
                    || ! empty($quote['suspended']) || ($quote['market_status'] ?? '') !== 'OPEN'
                    || ($quote['liquidity_valid'] ?? true) !== true || (float) ($runner->handicap ?? 0) !== 0.0
                    || ! is_numeric($price) || ! is_finite((float) $price) || (float) $price <= 1 || (float) $price > 1000
                    || ! is_numeric($size) || ! is_finite((float) $size) || (float) $size <= 0
                    || ! is_numeric($updated) || (float) $updated < microtime(true) - 75 || (float) $updated > microtime(true) + 5) {
                    continue 2;
                }
                $name = trim((string) ($quote['runner'] ?? $runner->runner));
                if (in_array(strtolower($name), ['draw', 'the draw', 'x'], true)) {
                    $side = 'draw';
                } else {
                    $home = $resolver->teamSimilarity($name, $fixture->team1);
                    $away = $resolver->teamSimilarity($name, $fixture->team2);
                    $side = $home >= 80 && $home > $away ? 'home' : ($away >= 80 && $away > $home ? 'away' : null);
                }
                if ($side === null || isset($prices[$side])) {
                    continue 2;
                }
                $prices[$side] = (float) $price;
            }
            if (count($prices) === 3 && array_sum(array_map(static fn (float $price): float => 1 / $price, $prices)) <= 1.20) {
                $books[] = ['source' => 'betfair_best_available_back', 'market_id' => (string) $marketId, ...$prices];
            }
        }

        return count($books) === 1 ? $books[0] : null;
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
                if ($allowed && $alert->type === 'prematch_drops' && ($payload['context']['provider'] ?? null) === 'pinnacle') {
                    $drop = ($payload['context']['pinnacle_drop'] ?? []) + ['sport' => $payload['context']['sport'] ?? $payload['context']['movement']['sport'] ?? '',
                        'sect' => $payload['context']['movement']['market'] ?? ''];
                    $allowed = self::allowsPinnacleMarket($drop);
                }
                $betfairBack = null;
                if ($allowed && $delivery->channel === 'telegram') {
                    $allowed = $customer->telegram_available && $customer->telegram_chat_id === $delivery->destination;
                    $pinnacleFilters = [];
                    if ($allowed && in_array($alert->type, [...self::FILTERS, 'goal', 'prematch_drops'], true)) {
                        $pinnacleFilters = $this->pinnacleFilters($customer);
                        $allowed = $this->allowsTelegramFilter($customer, $alert->type, $pinnacleFilters);
                    }
                    if ($alert->type === 'market_shock' || ($alert->type === 'prematch_drops' && ($payload['context']['provider'] ?? null) !== 'pinnacle')) {
                        $allowed = false;
                    }
                    if ($allowed && $alert->type === 'prematch_drops') {
                        $movement = $payload['context']['movement'] ?? [];
                        $market = strtolower(str_replace('_', ' ', trim((string) ($movement['market'] ?? ''))));
                        $line = $movement['point'] ?? null;
                        if (! is_numeric($line) && preg_match('/(?:over|under|total|o\/u)\s*([0-9]+(?:\.[0-9]+)?)/i', $market.' '.($movement['selection'] ?? ''), $lineMatch)) {
                            $line = $lineMatch[1];
                        }
                        $allowed = (int) ($movement['period'] ?? 0) === 0 && (in_array($market, self::MAIN_MARKETS, true)
                            || (preg_match('/^(total|totals|o\/u)(?:\s|$)/', $market) === 1 && is_numeric($line) && in_array((float) $line, [0.5, 1.5, 2.5, 3.5, 4.5], true)));
                    }
                    if ($allowed && $alert->type === 'prematch_drops') {
                        $allowed = $this->allowsPinnacleDrop($pinnacleFilters, $payload['context'], $betfairBack);
                    }
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
                    if ($alert->type === 'prematch_drops' && ! empty($pinnacleFilters['shock'])) {
                        $payload['context']['pinnacle_market_shock'] = true;
                    }
                    if ($alert->type === 'ltd' && isset($payload['context']['signal'], $payload['context']['betfair_match_id'])) {
                        $fixtures = FootballMatch::query()->where('betfair_match_id', $payload['context']['betfair_match_id'])->get();
                        $payload['context']['betfair_back'] = $fixtures->count() === 1 ? $this->sourceSignalBack($fixtures->first(), $payload['context']['signal']) : null;
                    }
                    if (in_array($alert->type, ['ht00', '00_70'], true)) {
                        $quote = $this->betfairMatchOdds((string) $alert->match_id, $payload['score'] ?? null);
                        if ($quote === null) {
                            if (now()->diffInSeconds(Carbon::parse($alert->created_at), true) < 120) {
                                DB::table('alert_deliveries')->where('id', $deliveryId)->update(['status' => 'retry',
                                    'error' => 'Waiting for fresh valid Betfair Match Odds (1X2).', 'updated_at' => now()]);

                                return 5;
                            }
                            DB::table('alert_deliveries')->where('id', $deliveryId)->update(['status' => 'skipped',
                                'error' => 'Fresh valid Betfair Match Odds (1X2) unavailable.', 'updated_at' => now()]);

                            return 0;
                        }
                        $payload['context']['betfair_match_odds'] = $quote;
                    }
                    if ($alert->type === 'fav_losing') {
                        $payload['context']['bet365_live_odds'] = $this->favoriteLiveBet365Odds((string) $alert->match_id, $payload['score'] ?? null, $payload['favorite']['favorite_side'] ?? null);
                        if ($payload['context']['bet365_live_odds'] === null) {
                            if (now()->diffInSeconds(Carbon::parse($alert->created_at), true) < 120) {
                                return 5;
                            }
                            DB::table('alert_deliveries')->where('id', $deliveryId)->update(['status' => 'skipped', 'error' => 'Fresh Bet365 odds unavailable for the alert score.', 'updated_at' => now()]);

                            return 0;
                        }
                    }
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
                        $public['betfair_back'] = $payload['context']['betfair_back'] ?? null;
                        $public['match_status'] = $payload['context']['match_status'] ?? null;
                        $public['result_status'] = $payload['context']['result_status'] ?? null;
                        $public['url'] = route('football.market-activity', ['match' => $alert->match_id], false);
                        Broadcast::private('customers.'.$customer->id.'.alerts')->via('reverb')->as('alert.received')->with($public)->sendNow();
                    } else {
                        $botToken = config('services.telegram.bot_token');
                        if (blank($botToken)) {
                            $status = 'failed';
                            $error = 'Telegram bot is not configured.';
                        } else {
                            $response = Http::connectTimeout(3)->timeout(10)->post('https://api.telegram.org/bot'.$botToken.'/sendMessage', [
                                'chat_id' => $customer->telegram_chat_id, 'text' => $this->text($payload, $betfairBack, $customer->timezone ?: (string) config('app.timezone', 'UTC')), 'protect_content' => true,
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

                if (in_array($alert->type, ['ltd', 'goal'], true)) {
                    Log::info('LTD alert delivery processed.', ['alert_id' => $alert->id, 'delivery_id' => $deliveryId, 'channel' => $delivery->channel, 'status' => $status]);
                }

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
    public function text(array $payload, ?float $betfairBack = null, ?string $timezone = null): string
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
            $signal = $payload['context']['signal'] ?? null;
            $result = $payload['context']['result_status'] ?? 'won';
            if ($signal !== null && (! $signal['is_ltd'] || $result !== 'won')) {
                return "📣 BF MARKETS | SIGNAL UPDATE\n\n{$base}\n📊 Market: {$signal['label']}\nStatus: ".strtoupper($result)."\nScore: {$score}".($payload['minute'] === null ? '' : "\nTime: {$time}' min");
            }

            return "⚽️ GOAL DETECTED! | BF MARKETS UPDATE\n\n{$base}\n🔢 New Score: {$score}\n⏱ Time: {$time}' min (1st Half)\n\n✅ Status: SIGNAL WON\n💰 ".self::STRATEGY['en']." Successful!\n\n⚠️ Informational signal only. Trade responsibly.";
        }
        if ($type === 'ltd') {
            $signal = $payload['context']['signal'] ?? null;
            if ($signal !== null) {
                $status = $payload['context']['match_status'] ?? 'Live';
                $quote = $payload['context']['betfair_back'] ?? null;
                $label = $signal['is_ltd'] ? self::STRATEGY['en'] : 'Source Channel Signal';

                return "🟢 BF MARKETS | ALERT DETECTED\n\n🎯 Filter: {$label}\n{$base}\n📊 Market: {$signal['label']}\n⏱ Status: {$status}"
                    .(($payload['context']['unresolved_fixture'] ?? false) && $payload['minute'] === null ? ' | Time: —' : ($status === 'Prematch' || $payload['minute'] === null ? '' : " | Time: {$time}' min"))
                    .($status === 'Prematch' ? '' : " | Score: {$score}")
                    ."\n📈 Betfair Back Odds: ".($quote === null ? '— (currently unavailable)' : number_format($quote, 2))
                    .($signal['is_ltd'] ? "\n📈 1st Half Draw Odds: ".(isset($payload['context']['draw_lay']) ? number_format($payload['context']['draw_lay'], 2) : '—')."\n📊 Action: LAY THE DRAW (LTD)" : '')
                    ."\n\n⚠️ Informational signal only. Trade responsibly.";
            }

            return "🟢 BF MARKETS | ALERT DETECTED\n\n🎯 Filter: ".self::STRATEGY['en']."\n{$base}\n⏱ Time: {$time}' min (1st Half) | Score: {$score}\n📈 1st Half Draw Odds: ".(isset($payload['context']['draw_lay']) ? number_format($payload['context']['draw_lay'], 2) : '—')."\n📊 Action: LAY THE DRAW (LTD)\n💡 Strategy: Lay the draw in the 1st half.\n\n⚠️ Informational signal only. Trade responsibly.";
        }
        if (in_array($type, ['prematch_drops', 'market_shock'], true)) {
            $m = $payload['context']['movement'];
            $from = is_numeric($m['from']) ? number_format((float) $m['from'], 2) : '—';
            $to = is_numeric($m['to']) ? number_format((float) $m['to'], 2) : '—';
            $drop = is_numeric($m['from']) && (float) $m['from'] > 0 && is_numeric($m['to']) ? round(((float) $m['from'] - (float) $m['to']) / (float) $m['from'] * 100, 1) : null;
            try {
                $zone = new \DateTimeZone(($timezone ?? getCurrentUserTimezone()) ?: 'UTC');
            } catch (\Exception) {
                $zone = new \DateTimeZone('UTC');
            }
            $kickoff = isset($payload['kickoff']) ? Carbon::parse($payload['kickoff'])->timezone($zone)->format('d M H:i') : '—';
            $pinnacleShock = (bool) ($payload['context']['pinnacle_market_shock'] ?? false);
            $shock = $type === 'market_shock';
            $line = is_numeric($m['point'] ?? null) ? ' '.$m['point'] : '';
            $text = ($pinnacleShock ? "⚡️ BF MARKETS | PINNACLE MARKET SHOCK\n🎯 Filter: Market Shock (≥8%)" : ($shock ? "⚡️ BF MARKETS | MARKET SHOCK DETECTED\n\n🎯 Filter: Market Shock Detector" : "🟡 BF MARKETS | PREMATCH DROP ALERT 📉\n🟡 Filter: Betfair > Pinnacle Drop"))
                ."\n{$base}\n⏰ Kickoff: {$kickoff}\n\n📊 Market: ".str_replace('_', ' ', $m['market']).$line.' ('.$m['selection'].")\n📈 Odds Movement: {$from} 📉 {$to}".($drop === null ? '' : " (-{$drop}% Drop)");

            if (! $shock && $betfairBack !== null) {
                $text .= "\n🟨 Betfair Back Odds: ".number_format($betfairBack, 2);
            }

            return $text.($shock ? "\n💥 Volume Surge: ".($m['volume'] ?? 'No data available') : "\n🔥 Price Drop: ".($drop === null ? '—' : "-{$drop}%"));
        }
        $label = ['ht00' => 'HT 0-0 (Half Time 0-0)', '00_70' => "0-0 @ 70' (Late Goal Radar)", 'fav_losing' => 'Fav Losing (Favorite Losing)'][$type];
        $indicator = ['ht00' => '🔵', '00_70' => '🔴', 'fav_losing' => '🟧'][$type];
        $text = "{$indicator} BF MARKETS | ALERT DETECTED 🎯\n{$indicator} Filter: {$label}\n{$base}\n".($type === 'ht00' ? '⏱ Status: Half Time' : "⏱ Time: {$time}' min")." | Score: {$score}";
        if ($type === 'fav_losing') {
            $side = $payload['favorite']['favorite_side'] ?? null;
            $teams = explode(' vs ', $payload['match'], 2);
            $favorite = $teams[$side === 'home' ? 0 : 1] ?? '—';

            return $text."\n\n🔥 Fav Team: {$favorite} (Pre-match @ ".($payload['favorite']['favorite_odds'] ?? '—').")\n📈 Current Live Odds: ".($payload['context']['bet365_live_odds'] ?? '—');
        }
        $odds = $payload['context']['betfair_match_odds'] ?? [];
        $format = static fn (string $side): string => isset($odds[$side]) ? number_format($odds[$side], 2) : '—';

        return $text."\n\n".($type === 'ht00' ? '📊 Match Odds (1X2):' : '📉 In-Play Odds:')
            ."\n• Home: ".$format('home')."\n• Draw: ".$format('draw')."\n• Away: ".$format('away');
    }
}
