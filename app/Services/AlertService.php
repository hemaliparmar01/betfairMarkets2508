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

        return (json_decode($stored ?? '', true) ?? []) + ['higher' => false, 'leagues' => null];
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
        $pinnacleSelected = ! empty($pinnacleFilters['higher']) || ! empty($pinnacleFilters['leagues']);
        $selected = $type === 'prematch_drops' ? $pinnacleSelected : $customer->{'tg_'.($type === 'goal' ? 'ltd' : $type)};
        if ($selected) {
            return true;
        }

        return ! $pinnacleSelected && ! array_any(self::FILTERS, fn (string $filter): bool => (bool) $customer->{'tg_'.$filter});
    }

    private function allowsPinnacleDrop(array $filters, array $context, ?float &$betfairBack): bool
    {
        if (empty($filters['higher']) && empty($filters['leagues'])) {
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
                $query->whereNull('subscription_ends_at')->orWhere('subscription_ends_at', '<=', now());
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

            return trim(implode("\n", array_filter($lines, static fn (string $line): bool => ! preg_match(
                '~https?://|t\.me/|\b(?:1xbet|promo(?:tion|\s*code)?|code\s+pomo|register|registration|bonus|deposit|referral|advertisement)\b~i', $line
            ))));
        };
        $text = $clean((string) ($message['text'] ?? ''));
        $context = trim($text."\n".$clean((string) ($message['reply_text'] ?? '')));
        $goal = preg_match('/(?:^|\n)\s*(?:⚽\s*)?go+a+l+(?:!+(?=\s|$)|(?=\h+(?:Score:|[^\n]+\h+\d{1,2}\h*[-:]\h*\d{1,2})))/iu', $text) === 1;
        $goal = $goal || preg_match('/\bStatus\s*:\s*(?:Won|Paid\s*out)\b/im', $text) === 1;
        $hasMinute = preg_match('/\b(\d{1,3}(?:\+\d{1,2})?)\s*[\x{0027}\x{2019}\x{2032}]/u', $text, $minute)
            || preg_match('/\b(?:1st\s*half\s*[-–:]|time\s+elapsed\s*:)\s*(\d{1,2}):[0-5]\d\b/iu', $text, $minute);
        $hasScore = preg_match('~(?<![\d:/-])(\d{1,2})\h*[-:–]\h*(\d{1,2})(?![\d:/-])~u', preg_replace('/^.*(?:1st\s*half\s*[-–:]|time\s+elapsed\s*:).*$/imu', '', $text), $score);
        if (! $goal && ! preg_match('/^\h*(?:(?:Market|Prediction|Selection)\h*:\h*)?(?:Totals?\.?\h*)?(?:Over\h*\(?0[.,]5\)?(?:\h*Goals?)?\h*[.\-–:]?\h*(?:1st\h*half|first\h*half|HT)|(?:1st\h*half|first\h*half|HT)\h*[.\-–:]?\h*Over\h*\(?0[.,]5\)?(?:\h*Goals?)?)(?=\h|$)/imu', $text)) {
            Log::debug('Telegram source post did not contain a supported LTD market.', ['message_id' => $message['message_id']]);

            return null;
        }
        $hasTeams = preg_match('/(?:^|\n)\s*(?:⚽\s*)?(?:Match\s*:\s*)?([^\n:]+?)\s+(?:vs\.?|v\.?|—)\s+([^\n]+?)(?=\s+[|@]|\s+-\s+(?:Over|Under)|\n|$)/iu', $context, $teams);
        if (! $hasTeams && $goal) {
            $hasTeams = preg_match('/\bgo+a+l+!?\s*([^\n]+?)\s+\d{1,2}\s*[-:]\s*\d{1,2}\s+([^\n]+?)(?=\s+@|\n|$)/iu', $text, $teams);
        }
        $hasLeague = preg_match('/(?:League|Competition|Tournament)\s*:\s*([^\n|]+)/iu', $context, $league);
        if (! $hasTeams) {
            $hasTeams = preg_match('/^\h*([\p{L}][^\n:]+?)\h+\d{1,2}\h*[:–-]\h*\d{1,2}\h+([\p{L}][^\n:]+?)(?:\n([\p{L}][\p{L} .-]+)(?=\n\h*\d))?\h*$/mu', $text, $teams);
            if ($hasTeams && isset($teams[3])) {
                $teams[2] .= ' '.$teams[3];
            }
        }
        if (! $hasLeague) {
            $hasLeague = preg_match('/^\h*Football[.\h]+[^\n.]+\.\h+([^\n]+)$/mi', $text, $league);
        }
        if (! $hasTeams || (! $goal && ! $hasLeague)) {
            Log::debug('Telegram source match details could not be parsed.', ['message_id' => $message['message_id']]);

            return null;
        }
        $home = trim($teams[1]);
        $away = trim($teams[2]);
        $competition = $hasLeague ? trim($league[1]) : null;
        $resolver = app(FootballSyncController::class);
        $normalizeTeam = static fn (string $name): string => match (strtolower(trim($name))) {
            'leeds' => 'Leeds United',
            'tottenham' => 'Tottenham Hotspur',
            default => $name,
        };
        $normalizeLeague = static fn (string $name): string => match (strtolower(trim($name))) {
            'english premier league', 'england premier league' => 'Premier League',
            default => trim(preg_replace('/^[^-]+ - /u', '', $name)),
        };
        $candidates = FootballMatch::query()->whereIn('result_status', $hasMinute && $hasScore ? ['Live', 'Prematch'] : ['Live'])->whereBetween('start_timestamp', [time() - 4 * 3600, time()])
            ->select(['id', 'sports_api_pro_match_id', 'team1', 'team2', 'tournament', 'unique_key'])->get()
            ->filter(fn (FootballMatch $match): bool => $resolver->teamSimilarity($normalizeTeam($match->team1), $normalizeTeam($home)) >= 80
                && $resolver->teamSimilarity($normalizeTeam($match->team2), $normalizeTeam($away)) >= 80
                && ($competition === null ? DB::table('alerts')->where('match_id', $match->sports_api_pro_match_id)->where('type', 'ltd')->exists()
                    : $resolver->teamSimilarity($normalizeLeague($match->tournament ?? ''), $normalizeLeague($competition)) >= 80));
        if ($candidates->count() !== 1) {
            Log::debug('Telegram source fixture could not be resolved uniquely.', ['message_id' => $message['message_id'], 'matches' => $candidates->count()]);

            return null;
        }
        $record = $candidates->first()->refresh();
        $match = $record->payload;
        if (MatchBackup::isHidden($match, 'football') || empty($match['sportsApiPro_match_id'])) {
            return null;
        }
        if ($goal) {
            if (! $hasScore || ! $hasMinute) {
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
        if (($match['result_status'] ?? null) === 'Prematch' && $hasMinute && $hasScore) {
            $match['result_status'] = 'Live';
            $match['minutes_status'] = '1st half';
        }
        if (! in_array($match['minutes_status'] ?? '', ['1st half', 'Live'], true)) {
            return null;
        }
        $record->load(['markets' => fn ($query) => $query->where('market_type', 'HALF_TIME')]);
        $draws = $record->markets->filter(fn ($runner): bool => in_array(strtolower(trim($runner->runner ?? '')), ['the draw', 'draw', 'x'], true)
            && $runner->lay_odds > 1 && $runner->lay_odds <= 1000 && empty($runner->payload['suspended']) && ! in_array($runner->payload['market_status'] ?? 'OPEN', ['SUSPENDED', 'CLOSED'], true) && $runner->updated_at->gt(now()->subMinutes(2)));
        $context = ['provider' => 'telegram_source', 'draw_lay' => $draws->count() === 1 ? $draws->first()->lay_odds : null];
        if ($hasMinute) {
            $context['minute'] = (int) $minute[1];
            if (str_starts_with($minute[1], '45+')) {
                $context['minute'] = $minute[1];
            }
        }
        if ($hasScore) {
            $context['score'] = $score[1].' - '.$score[2];
        }

        return $this->process('ltd', $match, $context);
    }

    /** @param array<string, mixed> $match @param array<string, mixed> $context */
    public function process(string $type, array $match, array $context = [], ?int $customerId = null): ?int
    {
        if (! config('services.telegram.enabled') || ! in_array($type, [...self::FILTERS, 'prematch_drops', 'market_shock', 'goal', 'test', 'expired'], true)) {
            return null;
        }
        $pinnacle = $context['pinnacle'] ?? null;
        $matchId = $match['sportsApiPro_match_id'] ?? null;
        if ($pinnacle !== null) {
            if ($type !== 'prematch_drops' || ! is_array($pinnacle) || empty($pinnacle['id']) || empty($pinnacle['home']) || empty($pinnacle['away'])
                || empty($pinnacle['alert_key']) || ($pinnacle['is_live'] ?? false) || ($pinnacle['feed'] ?? '') === 'live') {
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
        if (! in_array($type, ['test', 'expired'], true)) {
            if ($pinnacle === null && (empty($matchId) || empty($match['team1']) || empty($match['team2']) || empty($match['tournament'])
                || MatchBackup::isHidden($match, 'football'))) {
                return null;
            }
            $predicate = $pinnacle === null ? $this->footballFilterPredicate($type) : null;
            if ($predicate !== null && ! $predicate($match)) {
                return null;
            }
            if ($type === 'ltd' && (($match['result_status'] ?? '') !== 'Live' || (($context['provider'] ?? null) !== 'telegram_source' && ((float) ($context['draw_lay'] ?? 0) <= 1 || (float) ($context['draw_lay'] ?? 0) > 1000)))) {
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
        if ($type === 'goal' && (empty($context['parent_alert_id']) || $score === null || $minute === null
            || ! DB::table('alerts')->where('id', $context['parent_alert_id'])->where('type', 'ltd')->where('match_id', (string) $match['sportsApiPro_match_id'])->exists())) {
            return null;
        }
        if ($trigger === null) {
            return null;
        }
        $pinnacleAlertKey = $context['movement']['pinnacle_alert_key'] ?? null;
        $key = hash('sha256', $type === 'prematch_drops' && is_string($pinnacleAlertKey) && $pinnacleAlertKey !== ''
            ? implode('|', [$type, 'pinnacle', $context['movement']['sport'] ?? 'soccer', $pinnacleAlertKey])
            : implode('|', [$type, $matchId ?? $customerId, $trigger]));
        $payload = [
            'alert_type' => $type, 'match_id' => $matchId,
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
        if ($alert->type === 'goal' && (DB::table('alerts')->where('id', $alert->parent_alert_id)->whereNull('fanned_out_at')->exists()
            || DB::table('alert_deliveries')->where('alert_id', $alert->parent_alert_id)->whereIn('status', ['pending', 'retry', 'sending'])->exists())) {
            return 5;
        }
        $payload = json_decode($alert->payload, true);
        $pinnacle = $alert->type === 'prematch_drops' && ($payload['context']['provider'] ?? null) === 'pinnacle';
        if ($alert->type === 'market_shock' || ($alert->type === 'prematch_drops' && ! $pinnacle)) {
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
                        Broadcast::private('customers.'.$customer->id.'.alerts')->via('reverb')->as('alert.received')->with($public)->sendNow();
                    } else {
                        $botToken = config('services.telegram.bot_token');
                        if (blank($botToken)) {
                            $status = 'failed';
                            $error = 'Telegram bot is not configured.';
                        } else {
                            $response = Http::connectTimeout(3)->timeout(10)->post('https://api.telegram.org/bot'.$botToken.'/sendMessage', [
                                'chat_id' => $customer->telegram_chat_id, 'text' => $this->text($payload, $betfairBack), 'protect_content' => true,
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
    public function text(array $payload, ?float $betfairBack = null): string
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
            return "🔔 BF MARKETS | ALERT DETECTED\n\n🎯 Filter: ".self::STRATEGY['en']."\n{$base}\n⏱ Time: {$time}' min | Score: {$score}\n📈 1st Half Draw Odds: ".(isset($payload['context']['draw_lay']) ? number_format($payload['context']['draw_lay'], 2) : '—')."\n\n💡 Risk Tip: Recommended max stake: 10% of bankroll.";
        }
        if (in_array($type, ['prematch_drops', 'market_shock'], true)) {
            $m = $payload['context']['movement'];
            $from = is_numeric($m['from']) ? number_format((float) $m['from'], 2) : '—';
            $to = is_numeric($m['to']) ? number_format((float) $m['to'], 2) : '—';
            $drop = is_numeric($m['from']) && (float) $m['from'] > 0 && is_numeric($m['to']) ? round(((float) $m['from'] - (float) $m['to']) / (float) $m['from'] * 100, 1) : null;
            $kickoff = isset($payload['kickoff']) ? Carbon::parse($payload['kickoff'])->timezone(getCurrentUserTimezone())->format('d M H:i') : '—';
            $shock = $type === 'market_shock';
            $line = is_numeric($m['point'] ?? null) ? ' '.$m['point'] : '';
            $text = ($shock ? '⚡️ BF MARKETS | MARKET SHOCK DETECTED' : '📉 BF MARKETS | PREMATCH DROP ALERT')
                ."\n\n🎯 Filter: ".($shock ? 'Market Shock Detector' : 'Betfair > Pinnacle Drop')."\n{$base}\n⏰ Kickoff: {$kickoff}\n\n📊 Market: ".str_replace('_', ' ', $m['market']).$line.' ('.$m['selection'].")\n📈 Odds Movement: {$from} 📉 {$to}".($drop === null ? '' : " (-{$drop}% Drop)");

            if (! $shock && $betfairBack !== null) {
                $text .= "\n🟨 Betfair Back Odds: ".number_format($betfairBack, 2);
            }

            return $text.($shock ? "\n💥 Volume Surge: ".($m['volume'] ?? 'No data available') : "\n🔥 Price Drop: ".($drop === null ? '—' : "-{$drop}%"));
        }
        $label = ['ht00' => 'HT 0-0 (Half Time 0-0)', '00_70' => "0-0 @ 70'", 'fav_losing' => 'Favorite Losing In-Play'][$type];
        $text = "🔔 BF MARKETS | ALERT DETECTED\n\n🎯 Filter: {$label}\n{$base}\n".($type === 'ht00' ? '⏱ Status: Half Time' : "⏱ Time: {$time}' min")." | Score: {$score}";
        $odds = [];
        foreach ($payload['markets']['MATCH_ODDS'] ?? [] as $runner) {
            if ($type === 'ht00' && (! empty($runner['suspended']) || in_array($runner['market_status'] ?? 'OPEN', ['SUSPENDED', 'CLOSED'], true))) {
                continue;
            }
            $odds[strtolower((string) ($runner['runner'] ?? ''))] = $runner['currentBackOdds'] ?? $runner['backOdds'] ?? null;
        }
        if ($type === 'fav_losing') {
            $side = $payload['favorite']['favorite_side'] ?? null;
            $teams = explode(' vs ', $payload['match'], 2);
            $favorite = $teams[$side === 'home' ? 0 : 1] ?? '—';

            return $text."\n\n🔥 Fav Team: {$favorite} (Pre-match @ ".($payload['favorite']['favorite_odds'] ?? '—').")\n📈 Current Live Odds: ".($odds[strtolower($favorite)] ?? '—');
        }
        $teams = explode(' vs ', $payload['match'], 2);

        return $text."\n\n".($type === 'ht00' ? '📊 Match Odds (1X2):' : '📉 In-Play Odds:')
            ."\n• Home: ".($odds[strtolower($teams[0])] ?? '—')."\n• Draw: ".($odds['the draw'] ?? $odds['draw'] ?? '—')."\n• Away: ".($odds[strtolower($teams[1])] ?? '—');
    }
}
