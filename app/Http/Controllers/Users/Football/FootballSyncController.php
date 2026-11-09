<?php

namespace App\Http\Controllers\Users\Football;

use Amp\Cancellation;
use App\Events\FootballSportsScoreUpdated;
use App\Http\Controllers\Controller;
use App\Models\FootballMarket;
use App\Models\FootballMatch;
use App\Services\FootballV6Stats;
use App\Services\MatchBackup;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class FootballSyncController extends Controller
{
    public const PREMATCH_STATISTICS_SECTIONS = ['overview', 'pregame-form', 'h2h', 'streaks', 'home-season', 'away-season'];

    public string $getBetferKeys;

    public string $getsportsAPIProKeys;

    public string $sports;

    public function __construct()
    {
        $this->getBetferKeys = GetBetfairKey();
        $this->getsportsAPIProKeys = GetSportApiProKey();
        $this->sports = 'Football';
    }

    /**
     * Store every football event and its reconciliation state under the single timeline key.
     *
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $previous
     */
    public function storeTimeline(array &$match, string $channel = '', array $payload = [], string $frameType = 'update', ?float $requestedAt = null, array $previous = [], ?array $storedTimeline = null): bool
    {
        $matchId = $match['sportsApiPro_match_id'] ?? null;
        $validMatchId = is_scalar($matchId) && preg_match('/^[1-9][0-9]*$/', (string) $matchId);
        if ($validMatchId && $storedTimeline === null && $frameType !== 'read') {
            try {
                return DB::transaction(function () use (&$match, $channel, $payload, $frameType, $requestedAt, $previous, $matchId): bool {
                    $stored = DB::table('football_timeline')->where('match_id', (string) $matchId)->lockForUpdate()->first();

                    return $this->storeTimeline($match, $channel, $payload, $frameType, $requestedAt, $previous, json_decode($stored->timeline ?? '[]', true) ?? []);
                }, 3);
            } catch (Throwable $exception) {
                if ($frameType === 'repair') {
                    throw $exception;
                }
                Log::warning('Football timeline database unavailable', ['match_id' => $matchId, 'exception' => $exception::class]);
            }
        }
        $storedTimeline ??= [];
        $original = $match;
        $match['timeline'] = array_replace($match['timeline_state'] ?? [], $match['timeline'] ?? []);
        $previous['timeline'] = array_replace($previous['timeline_state'] ?? [], $previous['timeline'] ?? []);
        foreach (['last_goal', 'goal_highlight_until'] as $field) {
            if (isset($match[$field])) {
                $match['timeline'][$field] ??= $match[$field];
                unset($match[$field]);
            }
        }
        $groups = ['home_goals', 'away_goals', 'home_yellow_cards', 'away_yellow_cards', 'home_red_cards', 'away_red_cards', 'home_corners', 'away_corners'];
        $timeline = array_replace($storedTimeline, $previous['timeline'] ?? [], $match['timeline'] ?? []);
        foreach (['clock', 'reported_counts', 'change_timestamps'] as $field) {
            if (isset($storedTimeline[$field])) {
                $timeline[$field] = array_replace($storedTimeline[$field], $previous['timeline'][$field] ?? [], $match['timeline'][$field] ?? []);
            }
        }
        foreach (['initialized', 'finalized'] as $field) {
            if ($storedTimeline[$field] ?? false) {
                $timeline[$field] = true;
            }
        }
        $events = [];
        $eventSources = [$storedTimeline, $previous['timeline'] ?? [], $match['timeline'] ?? []];
        if (in_array($frameType, ['read', 'repair'], true)) {
            usort($eventSources, static fn (array $first, array $second): int => (float) ($first['incidents_updated_at'] ?? 0) <=> (float) ($second['incidents_updated_at'] ?? 0));
            $incidentTimes = array_column($eventSources, 'incidents_updated_at');
            if ($incidentTimes !== []) {
                $timeline['incidents_updated_at'] = max($incidentTimes);
            }
            $timeline['reported_counts'] = array_replace(...array_map(static fn (array $source): array => $source['reported_counts'] ?? [], $eventSources));
            $clockSources = $eventSources;
            usort($clockSources, static fn (array $first, array $second): int => (float) ($first['clock_updated_at'] ?? 0) <=> (float) ($second['clock_updated_at'] ?? 0));
            if (array_column($clockSources, 'clock') !== []) {
                $timeline['clock'] = array_replace(...array_map(static fn (array $source): array => $source['clock'] ?? [], $clockSources));
                $timeline['clock_updated_at'] = max(array_column($clockSources, 'clock_updated_at') ?: [0]);
            }
        }
        foreach ($eventSources as $sourceIndex => $source) {
            if ($sourceIndex === 2 && ($previous['timeline'] ?? []) === [] && $source === $storedTimeline && $source === ($match['timeline'] ?? [])) {
                continue;
            }
            foreach ($source['events'] ?? [] as $id => $event) {
                $events[(string) ($event['id'] ?? $id)] = $event;
            }
            foreach ($groups as $group) {
                foreach ($source[$group] ?? [] as $event) {
                    if (! is_array($event) || ! isset($event['id'])) {
                        continue;
                    }
                    $event['is_home'] = str_starts_with($group, 'home_');
                    $event['kind'] = str_ends_with($group, 'goals') ? 'goal' : (str_ends_with($group, 'yellow_cards') ? 'yellow' : (str_ends_with($group, 'red_cards') ? 'red' : 'corner'));
                    if (($events[(string) $event['id']]['time_known'] ?? false)
                        && (($event['time_known'] ?? true) === false || (($event['time_estimated'] ?? false) && ! ($events[(string) $event['id']]['time_estimated'] ?? false)))) {
                        foreach (['minute', 'added_time', 'seconds', 'time_known', 'time_estimated'] as $field) {
                            unset($event[$field]);
                        }
                    }
                    $events[(string) $event['id']] = array_replace($events[(string) $event['id']] ?? [], array_filter($event, static fn (mixed $value): bool => $value !== null && $value !== ''));
                    $events[(string) $event['id']] += ['timestamp' => null, 'player' => '', 'player_surname' => null, 'home_score' => null, 'away_score' => null];
                }
            }
        }
        $timeline['removed'] = $storedTimeline['removed'] ?? [];
        foreach ([$previous['timeline']['removed'] ?? [], $match['timeline']['removed'] ?? []] as $removed) {
            foreach ($removed as $id => $removedAt) {
                $timeline['removed'][$id] = max($removedAt, $timeline['removed'][$id] ?? 0);
            }
        }
        $timeline['events'] = array_diff_key($events, $timeline['removed']);
        $canEstimateCards = $frameType === 'update' && $requestedAt === null && ($timeline['initialized'] ?? false) && ($match['result_status'] ?? '') === 'Live';
        $eventClock = null;
        if ($channel !== '') {
            if (! preg_match('/^match:(\d+)(?::(incidents|stats))?$/', $channel, $channelMatch)
                || (string) ($match['sportsApiPro_match_id'] ?? '') !== $channelMatch[1]) {
                return false;
            }
            $isIncidents = ($channelMatch[2] ?? '') === 'incidents';
            $receivedAt = $requestedAt ?? microtime(true);
            if (! $isIncidents && ($match['result_status'] ?? '') === 'Finished' && ($timeline['finalized'] ?? false) && $requestedAt === null) {
                return false;
            }
            $sequence = data_get($payload, 'changes.changeTimestamp', $payload['changes.changeTimestamp'] ?? null);
            if ($requestedAt === null && is_numeric($sequence) && (int) $sequence > 0) {
                $lastSequence = (int) ($timeline['change_timestamps'][$channel] ?? 0);
                if ((int) $sequence < $lastSequence || (! $isIncidents && (int) $sequence === $lastSequence)) {
                    return false;
                }
                $timeline['change_timestamps'][$channel] = (int) $sequence;
            }
            $preserveNewerEvents = ($timeline['incidents_updated_at'] ?? 0) > $receivedAt;
            if ($isIncidents && (! $preserveNewerEvents || ($frameType === 'snapshot' && ! ($timeline['initialized'] ?? false)))) {
                $incidents = $payload['incidents'] ?? $payload['events'] ?? (array_is_list($payload) ? $payload : (isset($payload['incidentType']) || isset($payload['isDeleted'], $payload['id']) ? [$payload] : null));
                if (! is_array($incidents)) {
                    return false;
                }
                $events = $timeline['events'] ?? [];
                $beforeEvents = $events;
                $incomingGoals = [];
                $incomingCards = [];
                $goalPeriod = array_find($incidents, static fn (mixed $incident): bool => is_array($incident)
                    && ($incident['incidentType'] ?? null) === 'period'
                    && is_numeric($incident['homeScore'] ?? null) && is_numeric($incident['awayScore'] ?? null)
                    && (($incident['isLive'] ?? null) === true || (in_array(strtoupper((string) ($incident['text'] ?? '')), ['FT', 'AET', 'AP'], true) && ($incident['isLive'] ?? null) === false)));
                $cardHistory = $goalPeriod !== null && ($frameType === 'snapshot' || (is_array($payload['home'] ?? null) && is_array($payload['away'] ?? null)));
                foreach ($incidents as $incident) {
                    if (! is_array($incident)) {
                        continue;
                    }
                    if (($incident['isDeleted'] ?? false) === true && isset($incident['id'])) {
                        unset($events[(string) $incident['id']]);
                        $timeline['removed'][(string) $incident['id']] = $receivedAt;

                        continue;
                    }
                    if (isset($incident['id'], $timeline['removed'][(string) $incident['id']]) && ($frameType === 'snapshot' || ($incident['isDeleted'] ?? null) !== false)
                        && ! ($cardHistory && ($incident['incidentType'] ?? null) === 'card' && ($incident['rescinded'] ?? null) === false)) {
                        continue;
                    }
                    $kind = $incident['incidentType'] ?? null;
                    $class = ($incident['incClass'] ?? '') ?: ($incident['incidentClass'] ?? $incident['from'] ?? '');
                    $home = filter_var($incident['isHome'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if (! array_key_exists('isHome', $incident) || $home === null || (! is_numeric($incident['time'] ?? null) && ! isset($incident['id']))
                        || ! in_array($kind, ['goal', 'card', 'corner'], true)
                        || ($kind === 'card' && ! in_array($class, ['yellow', 'red', 'yellowRed'], true))) {
                        continue;
                    }
                    $incidentTime = (string) ($incident['time'] ?? '-1');
                    $stoppage = [];
                    preg_match('/^(\d+)\+(\d+)$/', $incidentTime, $stoppage);
                    $incidentMinute = is_numeric($incidentTime) || $stoppage !== [] ? (int) ($stoppage[1] ?? $incidentTime) : -1;
                    $incidentSeconds = is_numeric($incident['timeSeconds'] ?? null) ? (int) $incident['timeSeconds'] : null;
                    $timeKnown = $incidentMinute >= 0 || $incidentSeconds > 0;
                    $minute = $incidentMinute >= 0 ? $incidentMinute : ($incidentSeconds > 0 ? (int) floor($incidentSeconds / 60) : 0);
                    $added = max(0, (int) ($incident['addedTime'] ?? $stoppage[2] ?? 0));
                    $player = trim((string) ($incident['player']['name'] ?? $incident['playerName'] ?? ''));
                    if ($player === '') {
                        $player = trim(($incident['player']['firstName'] ?? '').' '.($incident['player']['lastName'] ?? ''));
                    }
                    $id = (string) ($incident['id'] ?? sha1(json_encode([$kind, $class, $minute, $added, $home, $incident['player']['id'] ?? $player])));
                    if (($preserveNewerEvents && isset($events[$id])) || ($timeline['removed'][$id] ?? 0) > $receivedAt) {
                        continue;
                    }
                    unset($timeline['removed'][$id]);
                    $timestamp = $events[$id]['timestamp'] ?? null;
                    $providerTime = $incident['timestamp'] ?? $incident['eventTimestamp'] ?? null;
                    if ($timestamp === null && $providerTime !== null) {
                        try {
                            if (is_numeric($providerTime) && (float) $providerTime >= 1000000000) {
                                $timestamp = Carbon::createFromTimestampUTC((float) $providerTime > 100000000000 ? (float) $providerTime / 1000 : (float) $providerTime)->toIso8601ZuluString();
                            } elseif (is_string($providerTime) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $providerTime)) {
                                $timestamp = Carbon::parse($providerTime)->utc()->toIso8601ZuluString();
                            }
                        } catch (Throwable) {
                            $timestamp = null;
                        }
                    }
                    $timeEstimated = ! $timeKnown && ($events[$id]['time_estimated'] ?? false);
                    if (! $timeKnown && ($events[$id]['time_known'] ?? false)) {
                        $minute = $events[$id]['minute'];
                        $added = $events[$id]['added_time'];
                        $incidentSeconds = $events[$id]['seconds'];
                        $timeKnown = true;
                    } elseif (! $timeKnown && (($kind === 'card' && ! isset($events[$id]) && $canEstimateCards) || ($kind === 'goal' && ($match['result_status'] ?? '') === 'Live')) && ($incident['isLive'] ?? true) !== false) {
                        $estimated = array_find($events, static fn (array $event): bool => ($event['source'] ?? '') === ($kind === 'goal' ? 'score' : 'statistics')
                            && ($event['kind'] ?? '') === ($kind === 'goal' ? 'goal' : ($class === 'yellow' ? 'yellow' : 'red')) && $event['is_home'] === $home
                            && ($kind !== 'goal' || ! is_numeric($incident[$home ? 'homeScore' : 'awayScore'] ?? null) || (int) ($event[$home ? 'home_score' : 'away_score'] ?? 0) === (int) $incident[$home ? 'homeScore' : 'awayScore']));
                        if ($estimated !== null) {
                            $minute = $estimated['minute'];
                            $added = $estimated['added_time'];
                            $incidentSeconds = $estimated['seconds'];
                            $timeKnown = $timeEstimated = (bool) ($estimated['time_known'] ?? false);
                            unset($events[$estimated['id']]);
                        }
                        if (! $timeKnown) {
                            $eventClock ??= app(FootballController::class)->footballTimeline($match, $timeline);
                        }
                        if (! $timeKnown && ! $eventClock['finished'] && ($eventClock['minute'] > 0 || $eventClock['running'])) {
                            $minute = min($eventClock['period_limit'], $eventClock['minute']);
                            $added = max(0, $eventClock['minute'] - $minute);
                            $timeKnown = $timeEstimated = true;
                        }
                    }
                    $events[$id] = array_replace($events[$id] ?? [], array_filter([
                        'timestamp' => $timestamp,
                        'id' => $id,
                        'kind' => $kind !== 'card' ? $kind : ($class === 'yellow' ? 'yellow' : 'red'),
                        'incident_class' => $class,
                        'minute' => $minute,
                        'time_known' => $timeKnown,
                        'time_estimated' => in_array($kind, ['card', 'goal'], true) ? $timeEstimated : null,
                        'added_time' => $added,
                        'seconds' => $incidentSeconds !== null && $incidentSeconds >= 0 ? $incidentSeconds : ($minute + $added) * 60,
                        'is_home' => $home,
                        'player' => $player,
                        'player_surname' => ! empty($incident['player']['lastName']) ? (string) $incident['player']['lastName'] : null,
                        'home_score' => $incident['homeScore'] ?? null,
                        'away_score' => $incident['awayScore'] ?? null,
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''));
                    $events[$id] += ['timestamp' => null, 'player' => '', 'player_surname' => null, 'home_score' => null, 'away_score' => null];
                    if ($kind === 'goal') {
                        $incomingGoals[$id] = $home;
                    } elseif ($kind === 'card') {
                        $incomingCards[$id] = ($home ? 'home_' : 'away_').($class === 'yellow' ? 'yellow_cards' : 'red_cards');
                    }
                }
                $confirmedHome = $goalPeriod['homeScore'] ?? ($requestedAt !== null && $frameType === 'snapshot' ? $match['home_score'] ?? null : null);
                $confirmedAway = $goalPeriod['awayScore'] ?? ($requestedAt !== null && $frameType === 'snapshot' ? $match['away_score'] ?? null : null);
                $incomingHomeGoals = count(array_filter($incomingGoals));
                // Only a complete score-confirmed history may remove events absent from a frame.
                if (! $preserveNewerEvents && is_numeric($confirmedHome) && is_numeric($confirmedAway)
                    && $incomingHomeGoals === (int) $confirmedHome
                    && count($incomingGoals) - $incomingHomeGoals === (int) $confirmedAway) {
                    foreach ($events as $id => $event) {
                        if ((($event['kind'] ?? '') === 'goal' && ! array_key_exists($id, $incomingGoals))
                            || ($cardHistory && in_array($event['kind'] ?? '', ['yellow', 'red'], true) && ! array_key_exists($id, $incomingCards))) {
                            unset($events[$id]);
                            if (! in_array($event['source'] ?? '', ['score', 'statistics'], true)) {
                                $timeline['removed'][$id] = $receivedAt;
                            }
                        }
                    }
                    if ($cardHistory) {
                        $cardCounts = array_count_values($incomingCards);
                        foreach (['home_yellow_cards', 'away_yellow_cards', 'home_red_cards', 'away_red_cards'] as $group) {
                            $timeline['reported_counts'][$group] = $payload['timeline_counts'][$group] = $cardCounts[$group] ?? 0;
                        }
                    }
                }
                $timeline['events'] = $events;
                $timeline['initialized'] = ($timeline['initialized'] ?? false) || $frameType === 'snapshot';
                $timeline['finalized'] = ($timeline['finalized'] ?? false)
                    || ($frameType === 'snapshot' && $requestedAt !== null && (($timeline['clock']['finished'] ?? false) || ($match['result_status'] ?? '') === 'Finished'))
                    || array_any($incidents, static fn (mixed $incident): bool => is_array($incident)
                        && ($incident['incidentType'] ?? null) === 'period'
                        && in_array(strtoupper((string) ($incident['text'] ?? '')), ['FT', 'AET', 'AP'], true)
                        && ($incident['isLive'] ?? null) === false);
                if ($events !== $beforeEvents || ! isset($timeline['incidents_updated_at'])) {
                    $timeline['incidents_updated_at'] = max($receivedAt, $timeline['incidents_updated_at'] ?? 0);
                }
            } elseif (! $isIncidents && ($timeline['clock_updated_at'] ?? 0) <= $receivedAt) {
                $read = static fn (string $path): mixed => array_key_exists($path, $payload) ? $payload[$path] : data_get($payload, $path);
                $clock = array_filter([
                    'status_code' => $read('status.code'),
                    'status_type' => $read('status.type'),
                    'status_description' => $read('status.description'),
                    'period_start' => $read('time.currentPeriodStartTimestamp'),
                    'initial' => $read('time.initial'),
                    'max' => $read('time.max'),
                    'last_period_timestamp' => $read('time.lastPeriodTimestamp'),
                ], static fn (mixed $value): bool => $value !== null);
                foreach (['period_start', 'last_period_timestamp'] as $field) {
                    if (isset($clock[$field]) && (! is_numeric($clock[$field]) || (int) $clock[$field] <= 0)) {
                        unset($clock[$field]);
                    }
                }
                foreach (['initial', 'max'] as $field) {
                    if (isset($clock[$field]) && (! is_numeric($clock[$field]) || (int) $clock[$field] < ($field === 'initial' ? 0 : 1))) {
                        unset($clock[$field]);
                    }
                }
                if (in_array((int) ($clock['status_code'] ?? 0), [6, 7], true)) {
                    $clock['initial'] ??= (int) $clock['status_code'] === 7 ? 2700 : 0;
                    $clock['max'] ??= (int) $clock['status_code'] === 7 ? 5400 : 2700;
                }
                if ($clock === []) {
                    return false;
                }
                $clock['finished'] = ($timeline['clock']['finished'] ?? false)
                    || in_array(strtoupper((string) ($clock['status_code'] ?? '')), ['FT', '100'], true)
                    || ($clock['status_type'] ?? null) === 'finished';
                $timeline['clock'] = array_replace($timeline['clock'] ?? [], $clock);
                if (in_array($clock['status_type'] ?? '', ['interrupted', 'willcontinue', 'postponed', 'suspended'], true)) {
                    $timeline['clock']['paused_at'] ??= (int) $receivedAt;
                } elseif (($clock['status_type'] ?? '') === 'inprogress' && in_array((int) ($clock['status_code'] ?? 0), [6, 7], true)) {
                    unset($timeline['clock']['paused_at']);
                }
                $timeline['clock_updated_at'] = $receivedAt;
            }
        }
        if (($timeline['clock']['finished'] ?? false) || ($timeline['finalized'] ?? false)) {
            $match['result_status'] = 'Finished';
            $match['minutes_status'] = 'Finished';
            $match['provider_lifecycle']['finished_pending'] = true;
            unset($match['internal_backup']);
            $period = array_find($payload['incidents'] ?? [], static fn (mixed $incident): bool => is_array($incident) && ($incident['incidentType'] ?? '') === 'period' && in_array(strtoupper((string) ($incident['text'] ?? '')), ['FT', 'AET', 'AP'], true));
            foreach (['home', 'away'] as $side) {
                $score = $period[$side.'Score'] ?? data_get($payload, $side.'Score.current');
                if ($score !== null) {
                    $match[$side.'_score'] = $score;
                }
            }
        }
        $events = $timeline['events'] ?? [];
        foreach ($groups as $group) {
            $timeline[$group] = [];
        }
        $pendingGoalGroups = [];
        $confirmedGoalCounts = ['home' => 0, 'away' => 0];
        uasort($events, static fn (array $a, array $b): int => [($a['minute'] ?? 0), ($a['added_time'] ?? 0), (string) ($a['id'] ?? '')] <=> [($b['minute'] ?? 0), ($b['added_time'] ?? 0), (string) ($b['id'] ?? '')]);
        foreach ($events as $event) {
            $kind = ['goal' => 'goals', 'yellow' => 'yellow_cards', 'red' => 'red_cards', 'corner' => 'corners'][$event['kind'] ?? ''] ?? null;
            if ($kind === null || ! isset($event['is_home'])) {
                continue;
            }
            $group = ($event['is_home'] ? 'home_' : 'away_').$kind;
            if ($kind === 'goals' && ($event['source'] ?? '') === 'score') {
                $pendingGoalGroups[$group] = true;
            } elseif ($kind === 'goals') {
                $confirmedGoalCounts[$event['is_home'] ? 'home' : 'away']++;
            }
            unset($event['kind'], $event['is_home']);
            $timeline[$group][] = $event;
        }
        foreach (['home', 'away'] as $side) {
            if (! is_numeric($match[$side.'_score'] ?? null)) {
                continue;
            }
            $group = $side.'_goals';
            $score = max(0, (int) $match[$side.'_score']);
            $previousScore = $timeline['reported_counts'][$group] ?? null;
            $canAddPending = ! in_array($frameType, ['restore', 'read'], true) && ! ($isIncidents ?? false)
                && (($match['result_status'] ?? '') === 'Live' || (($match['result_status'] ?? '') === 'Finished' && $previousScore !== null && $score > (int) $previousScore))
                && (($timeline['removed'] ?? []) === [] || $previousScore === null || $score > (int) $previousScore);
            if (isset($pendingGoalGroups[$group]) || ($canAddPending && count($timeline[$group]) < $score)) {
                $pendingGoals = [];
                $confirmedGoals = [];
                $knownScores = [];
                foreach ($timeline[$group] as $event) {
                    if (($event['source'] ?? '') === 'score') {
                        $pendingGoals[$event['id']] = $event;
                    } else {
                        $confirmedGoals[] = $event;
                        if (is_numeric($event[$side.'_score'] ?? null)) {
                            $knownScores[(int) $event[$side.'_score']] = true;
                        }
                    }
                }
                $otherSide = $side === 'home' ? 'away' : 'home';
                $unmatchedGoals = is_numeric($match[$otherSide.'_score'] ?? null) ? max(0, $confirmedGoalCounts[$otherSide] - (int) $match[$otherSide.'_score']) : 0;
                $remaining = max(0, $score - count($confirmedGoals) - $unmatchedGoals);
                for ($number = 1; $number <= $score && $remaining > 0; $number++) {
                    if (isset($knownScores[$number])) {
                        continue;
                    }
                    $id = 'score-'.$side.'-goal-'.$number;
                    if (isset($pendingGoals[$id]) || $canAddPending) {
                        $pendingGoals[$id] ??= [
                            'id' => $id, 'minute' => 0, 'added_time' => 0, 'seconds' => 0,
                            'time_known' => false, 'time_estimated' => false, 'source' => 'score',
                            $side.'_score' => $number,
                        ] + [
                            'timestamp' => null, 'player' => '', 'player_surname' => null,
                            'home_score' => null, 'away_score' => null,
                        ];
                        if (! ($pendingGoals[$id]['time_known'] ?? false) && ($match['result_status'] ?? '') === 'Live' && ! in_array($frameType, ['restore', 'read'], true)) {
                            $eventClock ??= app(FootballController::class)->footballTimeline($match, $timeline);
                            if (! $eventClock['finished'] && ($eventClock['minute'] > 0 || $eventClock['running'])) {
                                $minute = min($eventClock['period_limit'], $eventClock['minute']);
                                $added = max(0, $eventClock['minute'] - $minute);
                                $pendingGoals[$id] = array_replace($pendingGoals[$id], ['minute' => $minute, 'added_time' => $added, 'seconds' => ($minute + $added) * 60, 'time_known' => true, 'time_estimated' => true]);
                            }
                        }
                        $confirmedGoals[] = $pendingGoals[$id];
                        $remaining--;
                    }
                }
                $timeline[$group] = $confirmedGoals;
            }
            usort($timeline[$group], static fn (array $first, array $second): int => [$first['minute'], $first['added_time'], $first['id']] <=> [$second['minute'], $second['added_time'], $second['id']]);
            if (count($timeline[$group]) >= $score || ($previousScore !== null && $score <= (int) $previousScore)) {
                $timeline['reported_counts'][$group] = $score;
            }
        }
        foreach (['yellow_cards', 'red_cards', 'corners'] as $stat) {
            foreach (['home', 'away'] as $side) {
                $group = $side.'_'.$stat;
                $previousCount = $timeline['reported_counts'][$group] ?? (($timeline['initialized'] ?? false) && $timeline[$group] !== [] ? count($timeline[$group]) : null);
                $reported = $payload['timeline_counts'][$group] ?? $match['card_stats'][$stat][$side] ?? $match['live_stats'][$stat][$side] ?? ($stat === 'corners' ? ($match['corners'][$side] ?? null) : ($match['card_counts'][$side][$stat === 'yellow_cards' ? 'yellow' : 'total_red'] ?? null));
                if ($reported !== null) {
                    $timeline['reported_counts'][$group] = (int) $reported;
                }
                if ($stat !== 'corners') {
                    $actualCount = count(array_filter($timeline[$group], static fn (array $event): bool => ($event['source'] ?? '') !== 'statistics'));
                    $remainingEstimates = max(0, (int) ($timeline['reported_counts'][$group] ?? 0) - $actualCount);
                    $timeline[$group] = array_values(array_filter($timeline[$group], static function (array $event) use (&$remainingEstimates): bool {
                        return ($event['source'] ?? '') !== 'statistics' || $remainingEstimates-- > 0;
                    }));
                    if ($reported !== null && $previousCount !== null && (int) $reported > $previousCount && count($timeline[$group]) < (int) $reported && $canEstimateCards) {
                        $eventClock ??= app(FootballController::class)->footballTimeline($match, $timeline);
                        if (! $eventClock['finished'] && ($eventClock['minute'] > 0 || $eventClock['running'])) {
                            for ($index = max($previousCount, count($timeline[$group])); $index < (int) $reported; $index++) {
                                $minute = min($eventClock['period_limit'], $eventClock['minute']);
                                $added = max(0, $eventClock['minute'] - $minute);
                                $timeline[$group][] = ['id' => 'stats-'.$side.'-'.($stat === 'yellow_cards' ? 'yellow' : 'red').'-'.$index,
                                    'minute' => $minute, 'added_time' => $added, 'seconds' => ($minute + $added) * 60,
                                    'time_known' => true, 'time_estimated' => true, 'source' => 'statistics', 'timestamp' => null,
                                    'player' => '', 'player_surname' => null, 'home_score' => null, 'away_score' => null];
                            }
                        }
                    }
                    if ($actualCount > 0) {
                        $timeline['reported_counts'][$group] = max((int) ($timeline['reported_counts'][$group] ?? 0), $actualCount);
                    }
                }
                if (isset($timeline['reported_counts'][$group]) && ($stat === 'corners' || $timeline['reported_counts'][$group] > 0)
                    && $timeline['reported_counts'][$group] <= count(array_filter($timeline[$group], static fn (array $event): bool => ($event['time_known'] ?? false) && ! ($event['time_estimated'] ?? false))) && ($timeline['initialized'] ?? false)) {
                    unset($timeline['reported_counts'][$group]);
                }
            }
            unset($match['live_stats'][$stat], $match['live_stats_meta'][$stat], $match['live_stats_sources'][$stat]);
        }
        foreach ($match['statistics_periods'] ?? [] as $period => $sections) {
            foreach ($sections as $section => $stats) {
                unset($match['statistics_periods'][$period][$section]['yellow_cards'], $match['statistics_periods'][$period][$section]['red_cards'], $match['statistics_periods'][$period][$section]['corners']);
            }
        }
        unset($timeline['events'], $timeline['unplaced_card_counts']);
        $match['timeline'] = $timeline;
        unset($match['timeline_state'], $match['cards'], $match['card_counts'], $match['card_stats'], $match['corners'], $match['cards_updated_at'], $match['card_counts_updated_at'], $match['card_stats_updated_at'], $match['card_stats_fallback_at'], $match['incidents_initialized'], $match['events'], $match['goals'], $match['goal_counts']);
        if ($validMatchId && ! in_array($frameType, ['restore', 'read'], true) && $storedTimeline !== $match['timeline']) {
            if ($frameType === 'normalize') {
                return $this->storeTimeline($match, frameType: 'persist', previous: $previous);
            }
            $row = ['match_id' => (string) $matchId, 'timeline' => json_encode($match['timeline']), 'created_at' => now(), 'updated_at' => now()];
            foreach (array_slice($groups, 0, 6) as $group) {
                $minutes = [];
                foreach ($match['timeline'][$group] as $event) {
                    if ($event['time_known'] ?? false) {
                        $minutes[] = (string) $event['minute'].(! empty($event['added_time']) ? '+'.$event['added_time'] : '');
                    }
                }
                $row[$group] = json_encode(array_values(array_unique($minutes)));
            }
            try {
                DB::table('football_timeline')->upsert([$row], ['match_id'], array_merge(array_slice($groups, 0, 6), ['timeline', 'updated_at']));
            } catch (Throwable $exception) {
                if (DB::transactionLevel() > 0 || $frameType === 'repair') {
                    throw $exception;
                }
                Log::warning('Football timeline persistence failed', ['match_id' => $matchId, 'exception' => $exception::class]);
            }
        }
        $missingTimedEvents = array_any($groups, static fn (string $group): bool => array_any($match['timeline'][$group], static fn (array $event): bool => ! ($event['time_known'] ?? false) || ($event['time_estimated'] ?? false)))
            || array_any(['home', 'away'], static fn (string $side): bool => count($timeline[$side.'_goals']) < (int) ($match[$side.'_score'] ?? 0));
        foreach ($match['timeline']['reported_counts'] ?? [] as $group => $count) {
            if (in_array($group, $groups, true) && count(array_filter($match['timeline'][$group], static fn (array $event): bool => (bool) ($event['time_known'] ?? false))) < (int) $count) {
                $missingTimedEvents = true;
                break;
            }
        }
        if (in_array($frameType, ['history', 'read'], true) || ($frameType === 'update' && $missingTimedEvents)) {
            $matchId = (string) ($match['sportsApiPro_match_id'] ?? '');
            $finished = ($match['result_status'] ?? '') === 'Finished';
            $goalsComplete = count($timeline['home_goals']) === (int) ($match['home_score'] ?? 0) && count($timeline['away_goals']) === (int) ($match['away_score'] ?? 0);
            $needsHistory = ! ($timeline['initialized'] ?? false) || ! $goalsComplete || $missingTimedEvents || ($finished && ! ($timeline['finalized'] ?? false));
            $repairTimeline = $frameType === 'read' && ($timeline !== $storedTimeline || $timeline !== ($original['timeline'] ?? [])) ? $timeline : null;
            if (ctype_digit($matchId) && (int) $matchId > 0 && ($needsHistory || $repairTimeline !== null)) {
                $onlyIncidents = $goalsComplete && ! empty($timeline['clock']);
                $key = 'Football:timeline-backfill:'.$matchId;
                try {
                    $pending = Cache::store('redis')->lock($key.':pending', 3600);
                    if ($pending->get()) {
                        if (! Cache::store('redis')->add($key, true, 60)) {
                            $pending->release();
                        } else {
                            $owner = $pending->owner();
                            $expectedGoals = [];
                            if ($needsHistory && $missingTimedEvents) {
                                foreach (['home', 'away'] as $side) {
                                    $group = $side.'_goals';
                                    $score = (int) ($match[$side.'_score'] ?? 0);
                                    if ($score > count(array_filter($timeline[$group], static fn (array $event): bool => ($event['time_known'] ?? false) && ! ($event['time_estimated'] ?? false)))) {
                                        $expectedGoals[$group] = $score;
                                    }
                                }
                            }
                            dispatch(static function (CallQueuedClosure $job) use ($matchId, $onlyIncidents, $key, $owner, $needsHistory, $repairTimeline, $expectedGoals): void {
                                $retrying = false;
                                $writeTimeline = static function (?string $channel, array $payload, ?float $requestedAt = null) use ($matchId): void {
                                    withRedisLock('Football:matches', static function () use ($matchId, $channel, $payload, $requestedAt): void {
                                        $index = getInRedis('Football:matches', fixtureIndex: true);
                                        $matches = $index === null ? null : getInRedis('Football:matches', readOnly: $channel === null, matchIds: [$matchId], snapshot: $index);
                                        $existingMatches = null;
                                        if ($matches === null) {
                                            $index = null;
                                            $matches = getInRedis('Football:matches', readOnly: $channel === null) ?? [];
                                            $existingMatches = $matches;
                                        }

                                        $found = false;
                                        foreach ($matches as &$cachedMatch) {
                                            if ((string) ($cachedMatch['sportsApiPro_match_id'] ?? '') !== $matchId) {
                                                continue;
                                            }
                                            if ($channel === null) {
                                                app(self::class)->storeTimeline($cachedMatch, frameType: 'repair', previous: ['timeline' => $payload]);
                                            } else {
                                                app(self::class)->storeTimeline($cachedMatch, $channel, $payload, 'snapshot', $requestedAt);
                                            }
                                            $found = true;
                                        }
                                        unset($cachedMatch);

                                        if ($found || $channel !== null) {
                                            storeInRedis('Football:matches', $matches, 10, false, $existingMatches, fixtureSnapshot: $index);
                                        } else {
                                            $uncachedMatch = ['sportsApiPro_match_id' => $matchId];
                                            app(self::class)->storeTimeline($uncachedMatch, frameType: 'repair', previous: ['timeline' => $payload]);
                                        }
                                    }, 30);
                                };
                                try {
                                    if ($repairTimeline !== null) {
                                        $writeTimeline(null, $repairTimeline);
                                    }
                                    foreach (! $needsHistory ? [] : ($onlyIncidents ? ['/incidents'] : ['', '/incidents', '/odds']) as $suffix) {
                                        $overviewKey = $key.':overview:'.$owner;
                                        if ($suffix === '' && Cache::store('redis')->has($overviewKey)) {
                                            continue;
                                        }
                                        if ($suffix === '/odds') {
                                            $current = array_find(getInRedis('Football:matches', matchIds: [$matchId]) ?? [], static fn (array $match): bool => (string) ($match['sportsApiPro_match_id'] ?? '') === $matchId);
                                            if (($current['result_status'] ?? '') !== 'Live' || Cache::store('redis')->has('Football:bet365:live:'.$matchId)) {
                                                continue;
                                            }
                                        }
                                        $requestedAt = microtime(true);
                                        $response = ApiCall('https://api.sportsapipro.com/v2/football/match/'.$matchId.$suffix, ['x-api-key' => GetSportApiProKey()], ['connect_timeout' => 3, 'timeout' => 15]);
                                        if (! is_array($response) || ($response['success'] ?? true) === false) {
                                            continue;
                                        }
                                        $payload = $response['data'] ?? $response;
                                        $payload = $suffix === '' ? ($payload['event'] ?? $payload) : $payload;
                                        if (! is_array($payload)) {
                                            continue;
                                        }
                                        if ($suffix === '/odds') {
                                            app(self::class)->mergeBet365LiveOdds($matchId, $payload, $requestedAt);

                                            continue;
                                        }
                                        $writeTimeline('match:'.$matchId.($suffix === '' ? '' : ':incidents'), $payload, $requestedAt);
                                        if ($suffix === '' && (string) ($payload['id'] ?? '') === $matchId) {
                                            Cache::store('redis')->put($overviewKey, true, 600);
                                        }
                                    }
                                    Event::dispatch(new FootballSportsScoreUpdated);
                                    if ($expectedGoals !== [] && $job->attempts() < 3) {
                                        $stored = json_decode(DB::table('football_timeline')->where('match_id', $matchId)->value('timeline') ?? '[]', true) ?? [];
                                        foreach ($expectedGoals as $group => $score) {
                                            $confirmed = count(array_filter($stored[$group] ?? [], static fn (array $event): bool => ($event['time_known'] ?? false) && ! ($event['time_estimated'] ?? false)));
                                            if ($confirmed < min($score, (int) ($stored['reported_counts'][$group] ?? $score))) {
                                                $job->release(60 * $job->attempts());
                                                $retrying = true;
                                                break;
                                            }
                                        }
                                    }
                                } catch (LockTimeoutException) {
                                    $retrying = releaseSportsJobAfterLockContention($job);
                                } catch (Throwable $exception) {
                                    Log::warning('Football timeline history unavailable', ['match_id' => $matchId, 'exception' => $exception::class]);
                                    throw $exception;
                                } finally {
                                    if (! $retrying) {
                                        Cache::store('redis')->restoreLock($key.':pending', $owner)->release();
                                    }
                                }
                            })->name('Football timeline '.$matchId)->onConnection('sports-realtime')->onQueue('sports-timeline')
                                ->catch(static function (Throwable $exception) use ($key, $owner): void {
                                    Cache::store('redis')->restoreLock($key.':pending', $owner)->release();
                                });
                        }
                    }
                } catch (Throwable $exception) {
                    if (isset($pending)) {
                        $pending->release();
                    }
                    Log::warning('Football timeline history could not be queued', ['match_id' => $matchId, 'exception' => $exception::class]);
                }
            }
        }

        return $match !== $original;
    }

    public function syncBetFairLiveData()
    {
        $url = 'https://betfair-odds.com/betfair/v1/livemap?feed=live&sports=soccer';
        $key = 'x-portal-apikey: '.$this->getBetferKeys;
        $liveData = ApiCall($url, $key, ['connect_timeout' => 3, 'timeout' => 15], $apiDiagnostics);
        $cacheKey = $this->sports.':matches';
        if (! is_array($liveData['liveMap'] ?? null)) {
            Log::warning('Football Betfair live snapshot unavailable; existing matches retained', $apiDiagnostics ?? []);

            return getInRedis($cacheKey) ?? [];
        }

        $matches = withRedisLock(
            $cacheKey,
            fn () => $this->readBetFairData($liveData, $cacheKey, true, 10),
            30
        );
        $this->storeMatchesInDatabase($matches);

        return $matches;
    }

    public function syncBetFairPrematchData()
    {
        $url = 'https://betfair-odds.com/betfair/v1/livemap?feed=prematch&sports=soccer';
        $key = 'x-portal-apikey: '.$this->getBetferKeys;
        $liveData = ApiCall($url, $key, ['connect_timeout' => 3, 'timeout' => 15], $apiDiagnostics);
        $cacheKey = $this->sports.':matches';
        if (! is_array($liveData['liveMap'] ?? null)) {
            Log::warning('Football Betfair prematch snapshot unavailable; existing matches retained', $apiDiagnostics ?? []);

            return getInRedis($cacheKey) ?? [];
        }

        $matches = withRedisLock(
            $cacheKey,
            fn () => $this->readBetFairData($liveData, $cacheKey, false, 30),
            30
        );
        $this->storeMatchesInDatabase($matches);

        return $matches;
    }

    public function syncSportsApiProLiveData()
    {
        $url = 'https://api.sportsapipro.com/v2/football/live/all';
        $key = 'x-api-key: '.$this->getsportsAPIProKeys;
        $liveData = ApiCall($url, $key, ['connect_timeout' => 3, 'timeout' => 15], $apiDiagnostics);
        $liveData = is_array($liveData['data'] ?? null) ? $liveData['data'] : $liveData;
        $cacheKey = $this->sports.':matches';
        if (! is_array($liveData['events'] ?? null)) {
            Log::warning('Football SportsAPI Pro live snapshot unavailable; existing matches retained', $apiDiagnostics ?? []);

            return getInRedis($cacheKey) ?? [];
        }

        $matches = $this->readsportApiData($liveData, $cacheKey, true, 10);
        $this->storeMatchesInDatabase($matches);
        $this->queueBet365Favorites();
        $this->mergePinnacleDrops(getInRedis('Pinnacle:drops')['signals'] ?? []);

        return $matches;
    }

    public function syncSportsApiProPrematchData()
    {
        $cacheKey = $this->sports.':matches';
        $key = 'x-api-key: '.$this->getsportsAPIProKeys;
        $date = Carbon::now('UTC')->format('Y-m-d');
        $scheduleData = ApiCall("https://api.sportsapipro.com/v2/football/schedule/{$date}", $key, ['connect_timeout' => 3, 'timeout' => 15], $apiDiagnostics);
        $scheduleData = is_array($scheduleData['data'] ?? null) ? $scheduleData['data'] : ($scheduleData ?? []);
        if (! is_array($scheduleData['events'] ?? null)) {
            Log::warning('Football SportsAPI Pro schedule snapshot unavailable; existing matches retained', $apiDiagnostics ?? []);

            return;
        }

        $matches = $this->readsportApiData(['events' => $scheduleData['events']], $cacheKey, false, 30);
        $this->storeMatchesInDatabase($matches);
        $this->queueBet365Favorites();
        $this->mergePinnacleDrops(getInRedis('Pinnacle:drops')['signals'] ?? []);

        return $matches;
    }

    public function queueBet365Favorites(?string $date = null, int $page = 1): void
    {
        if ($date === null) {
            $dates = [];
            $candidates = [];
            foreach (getInRedis('Football:matches') ?? [] as $match) {
                if (! empty($match['sportsApiPro_match_id']) && $this->canFetchBet365Odds($match)) {
                    $candidates[$this->bet365FixtureMappingKey($match)] = $match;
                }
            }
            $mappings = $candidates === [] ? [] : Cache::store('redis')->many(array_keys($candidates));
            foreach ($candidates as $mappingKey => $match) {
                $oddsMatchId = $mappings[$mappingKey] ?? (($match['result_status'] ?? '') === 'Live' ? ($match['bet365_prematch']['match_id'] ?? null) : null);
                if ((int) $oddsMatchId > 0) {
                    $this->queueBet365MatchOdds($match, (int) $oddsMatchId);

                    continue;
                }
                $dates[gmdate('Y-m-d', (int) $match['timestamp'])] = true;
            }
            foreach (array_keys($dates) as $matchDate) {
                $this->queueBet365Favorites($matchDate);
            }

            return;
        }
        $cacheKey = 'Football:bet365:catalogue:'.$date;
        $pending = null;
        try {
            if ($page === 1 && Cache::store('redis')->has($cacheKey.':cooldown')) {
                return;
            }
            $pending = Cache::store('redis')->lock($cacheKey.':pending:'.$page, 3600);
            if (! $pending->get()) {
                return;
            }
            $owner = $pending->owner();
            dispatch(static function () use ($date, $page, $cacheKey, $owner): void {
                $lock = Cache::store('redis')->restoreLock($cacheKey.':pending:'.$page, $owner);
                $apiDiagnostics = null;
                try {
                    if (! $lock->isOwnedByCurrentProcess()) {
                        return;
                    }
                    $controller = app(self::class);
                    $matchesByKickoff = [];
                    $mappingKeys = [];
                    foreach (getInRedis('Football:matches') ?? [] as $match) {
                        $timestamp = (int) ($match['timestamp'] ?? 0);
                        if (gmdate('Y-m-d', $timestamp) !== $date || empty($match['sportsApiPro_match_id']) || ! $controller->canFetchBet365Odds($match)) {
                            continue;
                        }
                        $matchesByKickoff[$timestamp][] = $match;
                        if (($match['result_status'] ?? '') !== 'Live' || empty($match['bet365_prematch']['match_id'])) {
                            $mappingKeys[] = $controller->bet365FixtureMappingKey($match);
                        }
                    }
                    $unmapped = $mappingKeys === [] ? [] : array_filter(
                        Cache::store('redis')->many($mappingKeys),
                        static fn (mixed $id): bool => (int) $id <= 0,
                    );
                    if ($unmapped === []) {
                        return;
                    }
                    $response = ApiCall('https://api.sportsapipro.com/v4/football/matches', ['x-api-key' => GetSportApiProKey()], [
                        'query' => ['sport' => 'football', 'status' => 'all', 'perPage' => 150, 'page' => $page, 'startDate' => $date.'T00:00:00Z', 'endDate' => $date.'T23:59:59Z'],
                        'connect_timeout' => 3, 'timeout' => 15,
                    ], $apiDiagnostics);
                    $payload = $response['data'] ?? $response;
                    if (! is_array($payload['matches'] ?? null)) {
                        throw new \RuntimeException('SportsAPI Pro V4 Football catalogue unavailable.');
                    }
                    foreach ($payload['matches'] as $fixture) {
                        if (! is_array($fixture) || (int) ($fixture['sportId'] ?? 1) !== 1 || empty($fixture['id'])
                            || ! is_string($fixture['homeTeam'] ?? null) || ! is_string($fixture['awayTeam'] ?? null) || empty($fixture['matchDate'])) {
                            continue;
                        }
                        try {
                            $timestamp = Carbon::parse($fixture['matchDate'], 'UTC')->timestamp;
                        } catch (Throwable) {
                            continue;
                        }
                        $candidates = array_filter($matchesByKickoff[$timestamp] ?? [], static fn (array $match): bool => $controller->teamSimilarity($match['team1'] ?? '', $fixture['homeTeam']) >= 80
                            && $controller->teamSimilarity($match['team2'] ?? '', $fixture['awayTeam']) >= 80);
                        if (count($candidates) === 1) {
                            $match = reset($candidates);
                            $mappingKey = $controller->bet365FixtureMappingKey($match);
                            if (array_key_exists($mappingKey, $unmapped)) {
                                $oddsMatchId = (int) $fixture['id'];
                                if ($oddsMatchId > 0) {
                                    Cache::store('redis')->put($mappingKey, $oddsMatchId, 3600);
                                    unset($unmapped[$mappingKey]);
                                    $controller->queueBet365MatchOdds($match, $oddsMatchId);
                                }
                            }
                        }
                    }
                    if ($unmapped !== [] && $page < (int) ($payload['totalPages'] ?? 1) && $page < 50) {
                        $controller->queueBet365Favorites($date, $page + 1);
                    }
                    Cache::store('redis')->put($cacheKey.':cooldown', true, 900);
                } catch (Throwable $e) {
                    Cache::store('redis')->put($cacheKey.':cooldown', true, 300);
                    Log::warning('Football bet365 catalogue failed', ['date' => $date, 'page' => $page, 'api' => $apiDiagnostics, 'exception' => $e::class, 'message' => $e->getMessage()]);
                } finally {
                    $lock->release();
                }
            })->name('Football bet365 catalogue '.$date.' page '.$page)->onQueue('default')->catch(static function (Throwable $e) use ($cacheKey, $owner, $page): void {
                Cache::store('redis')->restoreLock($cacheKey.':pending:'.$page, $owner)->release();
                Cache::store('redis')->put($cacheKey.':cooldown', true, 300);
            });
        } catch (Throwable $e) {
            $pending?->release();
            Log::warning('Football bet365 catalogue could not be queued', ['date' => $date, 'exception' => $e::class]);
        }
    }

    private function bet365FixtureMappingKey(array $match): string
    {
        $identity = [$match['sportsApiPro_match_id'], $match['timestamp'] ?? null, $match['team1'] ?? '', $match['team2'] ?? ''];

        return 'Football:bet365:fixture:'.hash('sha256', serialize($identity));
    }

    public function queueBet365MatchOdds(array $match, int $oddsMatchId): void
    {
        $matchId = (string) $match['sportsApiPro_match_id'];
        $cacheKey = 'Football:bet365:'.$matchId;
        $unavailableKey = 'Football:bet365:unavailable:'.$oddsMatchId;
        $cooldownKey = $cacheKey.(($match['result_status'] ?? '') === 'Live' ? ':final-cooldown' : ':cooldown');
        $pending = null;
        try {
            if ($oddsMatchId <= 0 || ! $this->canFetchBet365Odds($match)
                || Cache::store('redis')->has($cooldownKey) || Cache::store('redis')->has($unavailableKey)) {
                return;
            }
            $pending = Cache::store('redis')->lock($cacheKey.':pending', 3600);
            if (! $pending->get()) {
                return;
            }
            $owner = $pending->owner();
            dispatch(static function (CallQueuedClosure $job) use ($matchId, $oddsMatchId, $cacheKey, $cooldownKey, $unavailableKey, $owner): void {
                $lock = Cache::store('redis')->restoreLock($cacheKey.':pending', $owner);
                $retrying = false;
                $status = null;
                $apiDiagnostics = null;
                try {
                    if (! $lock->isOwnedByCurrentProcess() || Cache::store('redis')->has($unavailableKey)) {
                        return;
                    }
                    $controller = app(self::class);
                    $match = array_find(getInRedis('Football:matches', matchIds: [$matchId]) ?? [], static fn (array $match): bool => (string) ($match['sportsApiPro_match_id'] ?? '') === $matchId && $controller->canFetchBet365Odds($match));
                    if ($match === null) {
                        return;
                    }
                    $requestedAt = microtime(true);
                    $response = ApiCall('https://api.sportsapipro.com/v4/football/match/'.$oddsMatchId.'/odds', ['x-api-key' => GetSportApiProKey()], ['query' => ['oddGroupId' => 1], 'connect_timeout' => 3, 'timeout' => 15, 'raw_response' => true], $apiDiagnostics);
                    if (is_array($response) && array_key_exists('body', $response)) {
                        $status = (int) $response['status'];
                        $response = json_decode($response['body'], true);
                    }
                    $payload = is_array($response) ? ($response['data'] ?? $response) : [];
                    if ((int) ($payload['matchId'] ?? 0) !== $oddsMatchId) {
                        throw new \RuntimeException('SportsAPI Pro V4 odds unavailable or returned a different match ID'.($status !== null ? ' (HTTP '.$status.')' : '').'.');
                    }
                    $favorite = $controller->bet365Favorite($payload);
                    if ($favorite === null) {
                        Cache::store('redis')->put($cooldownKey, true, 900);

                        return;
                    }
                    $changed = withRedisLock('Football:matches', static function () use ($matchId, $oddsMatchId, $favorite, $requestedAt): array {
                        $matches = getInRedis('Football:matches') ?? [];
                        $existingMatches = $matches;
                        foreach ($matches as $key => $match) {
                            if ((string) ($match['sportsApiPro_match_id'] ?? '') !== $matchId || ! in_array($match['result_status'] ?? '', ['Live', 'Prematch'], true)
                                || (($match['result_status'] ?? '') === 'Live' && ($match['bet365_prematch']['finalized'] ?? false))
                                || ($match['bet365_prematch']['fetched_at'] ?? 0) > $requestedAt) {
                                continue;
                            }
                            $openings = [];
                            foreach (['home_odds', 'draw_odds', 'away_odds'] as $selection) {
                                $previous = (int) ($match['bet365_prematch']['match_id'] ?? 0) === $oddsMatchId
                                    ? ($match['bet365_prematch']['opening_odds'][$selection] ?? []) : [];
                                $openings[$selection] = updateOddsMonitoring(['backOdds' => $favorite[$selection] ?? null], $previous, $requestedAt);
                            }
                            $matches[$key]['bet365_prematch'] = array_merge($favorite, [
                                'bookmaker_id' => 126, 'match_id' => $oddsMatchId, 'fetched_at' => $requestedAt,
                                'opening_odds' => $openings,
                                'finalized' => $match['result_status'] === 'Live',
                                'previous_odds' => array_intersect_key($match['bet365_prematch'] ?? [], array_flip(['home_odds', 'draw_odds', 'away_odds'])),
                            ]);
                            storeInRedis('Football:matches', $matches, 30, true, $existingMatches);

                            return [$key => $matches[$key]];
                        }

                        return [];
                    });
                    Cache::store('redis')->put($cooldownKey, true, 900);
                    if ($changed !== []) {
                        $controller->storeMatchesInDatabase($changed);
                        Event::dispatch(new FootballSportsScoreUpdated);
                    }
                } catch (LockTimeoutException) {
                    $retrying = releaseSportsJobAfterLockContention($job);
                } catch (Throwable $e) {
                    $retryAfter = in_array($status, [404, 410], true) ? 3600 : 300;
                    if (in_array($status, [404, 410], true)) {
                        Cache::store('redis')->put($unavailableKey, true, $retryAfter);
                    }
                    Cache::store('redis')->put($cooldownKey, true, $retryAfter);
                    Log::warning('Football bet365 odds failed', ['match_id' => $matchId, 'odds_match_id' => $oddsMatchId, 'http_status' => $status, 'api' => $apiDiagnostics, 'exception' => $e::class, 'message' => $e->getMessage()]);
                } finally {
                    if (! $retrying) {
                        $lock->release();
                    }
                }
            })->name('Football bet365 prematch odds '.$matchId)->onQueue('default')->catch(static function (Throwable $e) use ($cacheKey, $cooldownKey, $owner): void {
                Cache::store('redis')->restoreLock($cacheKey.':pending', $owner)->release();
                Cache::store('redis')->put($cooldownKey, true, 300);
            });
        } catch (Throwable $e) {
            $pending?->release();
            Log::warning('Football bet365 odds could not be queued', ['match_id' => $matchId, 'exception' => $e::class]);
        }
    }

    /** @param array<string, mixed> $match */
    private function canFetchBet365Odds(array $match): bool
    {
        return in_array($match['result_status'] ?? '', ['Live', 'Prematch'], true)
            && (int) ($match['timestamp'] ?? 0) >= time() - 86400
            && (int) ($match['timestamp'] ?? 0) <= time() + 86400
            && ! ($match['timeline']['clock']['finished'] ?? false)
            && ($match['timeline']['clock']['status_type'] ?? '') !== 'finished'
            && ! (($match['result_status'] ?? '') === 'Live' && ($match['bet365_prematch']['finalized'] ?? false));
    }

    /** @return array{home_odds: float, draw_odds: ?float, away_odds: float, favorite_side: string, favorite_odds: float}|null */
    private function bet365Favorite(array $payload): ?array
    {
        foreach ($payload['prematch'] ?? [] as $market) {
            if (! is_array($market) || (int) ($market['og'] ?? $market['id'] ?? 0) !== 1) {
                continue;
            }
            foreach ($market['periods'] ?? [] as $period) {
                if (! is_array($period) || preg_replace('/[^a-z]/', '', strtolower((string) ($period['name'] ?? ''))) !== 'fulltime') {
                    continue;
                }
                foreach ($period['odds'] ?? [] as $row) {
                    if (! is_array($row) || (int) ($row['bid'] ?? 0) !== 126 || filter_var($row['suspended'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        continue;
                    }
                    $home = $row['o1'] ?? null;
                    $away = $row['o3'] ?? $row['o2'] ?? null;
                    $draw = $row['oX'] ?? (isset($row['o3']) ? ($row['o2'] ?? null) : null);
                    if (! is_numeric($home) || ! is_numeric($away) || ! is_finite((float) $home) || ! is_finite((float) $away) || (float) $home <= 1 || (float) $away <= 1) {
                        continue;
                    }

                    return [
                        'home_odds' => (float) $home,
                        'draw_odds' => is_numeric($draw) && is_finite((float) $draw) && (float) $draw > 1 ? (float) $draw : null,
                        'away_odds' => (float) $away,
                        'favorite_side' => (float) $home <= (float) $away ? 'home' : 'away',
                        'favorite_odds' => min((float) $home, (float) $away),
                    ];
                }
            }
        }

        return null;
    }

    public function mergeBet365LiveOdds(int|string $matchId, array $payload, ?float $receivedAt = null, ?int $sourceTimestamp = null, ?Cancellation $cancellation = null): bool
    {
        if (! is_array($payload['markets'] ?? null) || (isset($payload['eventId']) && (string) $payload['eventId'] !== (string) $matchId)
            || (isset($payload['providerId']) && (int) $payload['providerId'] !== 1)) {
            return false;
        }
        $markets = [];
        foreach ($payload['markets'] as $market) {
            if (! is_array($market) || ! filter_var($market['isLive'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            $suspended = filter_var($market['suspended'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $choices = [];
            foreach ($market['choices'] ?? [] as $choice) {
                if (! is_array($choice)) {
                    continue;
                }
                $choices[] = [
                    'id' => $choice['sourceId'] ?? $choice['id'] ?? null,
                    'name' => (string) ($choice['name'] ?? ''),
                    'decimal_odds' => $suspended ? null : $this->bet365DecimalOdds($choice['fractionalValue'] ?? null),
                ];
            }
            $markets[] = [
                'id' => $market['id'] ?? $market['marketId'] ?? null,
                'market_id' => $market['marketId'] ?? null, 'name' => (string) ($market['marketName'] ?? ''),
                'period' => $market['marketPeriod'] ?? null, 'suspended' => $suspended, 'choices' => $choices,
            ];
        }
        $receivedAt ??= microtime(true);

        $cacheKey = 'Football:bet365:live:'.$matchId;

        $lock = Cache::store('redis')->lock($cacheKey.':write', 15);
        $store = static function () use ($matchId, $markets, $receivedAt, $sourceTimestamp, $cacheKey): bool {
            $match = array_find(getInRedis('Football:matches', matchIds: [(string) $matchId]) ?? [], static fn (array $match): bool => (string) ($match['sportsApiPro_match_id'] ?? '') === (string) $matchId);
            $latest = Cache::store('redis')->get($cacheKey, []);
            if (($match['result_status'] ?? '') !== 'Live' || ($latest['updated_at'] ?? 0) > $receivedAt
                || ($sourceTimestamp !== null && ($latest['source_timestamp'] ?? 0) > $sourceTimestamp)) {
                return false;
            }
            $openings = $latest['openings'] ?? [];
            foreach ($markets as &$market) {
                foreach ($market['choices'] as &$choice) {
                    $selection = hash('sha256', json_encode([$market['id'], $market['market_id'], $market['period'], $market['name'], $choice['id'], $choice['name']], JSON_THROW_ON_ERROR));
                    $opening = updateOddsMonitoring(['backOdds' => $choice['decimal_odds']], $openings[$selection] ?? [], $receivedAt, ! $market['suspended']);
                    $openings[$selection] = $opening;
                    $choice['initial_decimal_odds'] = $opening['initialBackOdds'];
                    $choice['opening_odds'] = $opening;
                }
                unset($choice);
            }
            unset($market);
            Cache::store('redis')->put($cacheKey, ['provider_id' => 1, 'markets' => $markets, 'openings' => $openings, 'updated_at' => $receivedAt, 'source_timestamp' => $sourceTimestamp], 86400);

            return true;
        };
        if ($cancellation === null) {
            return $lock->block(2, $store);
        }
        while (! $lock->get()) {
            \Amp\delay(0.2, cancellation: $cancellation);
        }
        try {
            return $store();
        } finally {
            $lock->release();
        }
    }

    private function bet365DecimalOdds(mixed $fraction): ?float
    {
        if (! is_string($fraction) || preg_match('/^\s*(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)\s*$/', $fraction, $parts) !== 1 || (float) $parts[2] <= 0) {
            return null;
        }
        $decimal = (float) $parts[1] / (float) $parts[2] + 1;

        return is_finite($decimal) && $decimal > 1 ? $decimal : null;
    }

    /** @param array<string, mixed> $match
     * @param  array<string, mixed>  $payload
     */
    public function applyMatchStatistics(array &$match, array $payload, ?float $requestedAt = null): bool
    {
        $receivedAt = $requestedAt ?? microtime(true);
        $changeTimestamp = data_get($payload, 'changes.changeTimestamp', $payload['changes.changeTimestamp'] ?? null);
        if (($requestedAt !== null && ($match['statistics_updated_at'] ?? 0) > $requestedAt)
            || ($requestedAt === null && is_numeric($changeTimestamp) && $changeTimestamp < ($match['statistics_change_timestamp'] ?? 0))) {
            return false;
        }

        $statKeys = [
            'attacks' => 'attacks', 'dangerousattacks' => 'dangerous_attacks',
            'ballpossession' => 'possession', 'possession' => 'possession',
            'totalshotsongoal' => 'total_shots', 'totalshots' => 'total_shots',
            'shotsongoal' => 'shots_on_target', 'shotsontarget' => 'shots_on_target',
            'shotsoffgoal' => 'shots_off_target', 'shotsofftarget' => 'shots_off_target',
            'blockedscoringattempt' => 'blocked_shots', 'blockedshots' => 'blocked_shots',
            'passes' => 'total_passes', 'totalpasses' => 'total_passes',
            'accuratepasses' => 'accurate_passes', 'totaltackle' => 'tackles', 'tackles' => 'tackles',
            'interceptionwon' => 'interceptions', 'interceptions' => 'interceptions',
            'fouls' => 'fouls', 'cornerkicks' => 'corners', 'corners' => 'corners',
            'yellowcards' => 'yellow_cards', 'redcards' => 'red_cards', 'offsides' => 'offsides',
        ];
        $liveStats = [];
        $meta = [];
        $periods = $match['statistics_periods'] ?? [];
        $stack = [[$payload, 'ALL', 'Match statistics']];
        $hasItems = false;
        while ($stack !== []) {
            [$data, $period, $group] = array_pop($stack);
            $period = strtoupper((string) ($data['period'] ?? $data['periodName'] ?? $period));
            $group = (string) ($data['groupName'] ?? $group);
            $providerKey = (string) ($data['key'] ?? $data['name'] ?? $data['statisticName'] ?? '');
            if ($providerKey !== '' && (array_key_exists('home', $data) || array_key_exists('homeValue', $data))
                && (array_key_exists('away', $data) || array_key_exists('awayValue', $data))) {
                $name = strtolower(preg_replace('/[^a-z0-9]/i', '', $providerKey));
                $key = $statKeys[$name] ?? Str::snake(Str::camel($providerKey));
                $homeDisplay = $data['home'] ?? $data['homeValue'] ?? null;
                $awayDisplay = $data['away'] ?? $data['awayValue'] ?? null;
                if ((is_scalar($homeDisplay) || $homeDisplay === null) && (is_scalar($awayDisplay) || $awayDisplay === null)) {
                    $hasItems = true;
                    $periods[$period][$group][$key] = [
                        'label' => (string) ($data['name'] ?? $data['statisticName'] ?? Str::headline($providerKey)),
                        'home' => $homeDisplay, 'away' => $awayDisplay,
                    ];
                }
                if (! is_scalar($homeDisplay) && $homeDisplay !== null || ! is_scalar($awayDisplay) && $awayDisplay !== null) {
                    continue;
                }
                $isPercent = preg_match('/^-?\d+(?:\.\d+)?%$/', trim((string) $homeDisplay)) === 1
                    && preg_match('/^-?\d+(?:\.\d+)?%$/', trim((string) $awayDisplay)) === 1;
                $home = $isPercent ? rtrim(trim((string) $homeDisplay), '%') : ($data['homeValue'] ?? $homeDisplay);
                $away = $isPercent ? rtrim(trim((string) $awayDisplay), '%') : ($data['awayValue'] ?? $awayDisplay);
                if ($period === 'ALL' && ! in_array($key, ['attacks', 'dangerous_attacks'], true) && is_numeric($home) && is_numeric($away)) {
                    $liveStats[$key] = ['home' => (float) $home, 'away' => (float) $away];
                    $meta[$key] = ['label' => $periods[$period][$group][$key]['label'], 'percent' => $isPercent];
                }
            }
            foreach (array_reverse($data, true) as $value) {
                if (is_array($value)) {
                    $stack[] = [$value, $period, $group];
                }
            }
        }
        if (! $hasItems) {
            return false;
        }
        $match['statistics_periods'] = $periods;
        $match['statistics_updated_at'] = $receivedAt;
        if ($requestedAt === null && is_numeric($changeTimestamp)) {
            $match['statistics_change_timestamp'] = $changeTimestamp;
        }
        $match['live_stats'] = array_replace($match['live_stats'] ?? [], $liveStats);
        $match['live_stats_meta'] = array_replace($match['live_stats_meta'] ?? [], $meta);
        foreach (array_keys($liveStats) as $stat) {
            $match['live_stats_sources'][$stat] = 'v2';
        }
        $this->storeTimeline($match);

        return true;
    }

    /** @return list<string> */
    public static function prematchStatisticsSections(?string $status): array
    {
        return $status === 'Prematch'
            ? self::PREMATCH_STATISTICS_SECTIONS
            : array_values(array_diff(self::PREMATCH_STATISTICS_SECTIONS, ['pregame-form', 'h2h']));
    }

    public function queueMatchStatistics(): void
    {
        $queued = [];
        $index = getInRedis('Football:matches', fixtureIndex: true, marketActivityIndex: true);
        $indexed = ($index['statistics_schedule'] ?? null) === 2;
        foreach ($indexed ? $index['matches'] : (getInRedis('Football:matches', readOnly: true) ?? []) as $match) {
            $matchId = (string) ($match['sportsApiPro_match_id'] ?? '');
            if (! ctype_digit($matchId) || (int) $matchId <= 0 || isset($queued[$matchId]) || ! in_array($match['result_status'] ?? '', ['Prematch', 'Live'], true) || MatchBackup::isHidden($match, 'football')) {
                continue;
            }
            $queued[$matchId] = true;
            $due = $indexed ? ($match['_statistics_due_at'] ?? 0) <= microtime(true) : array_any(self::prematchStatisticsSections($match['result_status'] ?? null), static function (string $section) use ($match): bool {
                $attempt = $match['statistics_requests'][$section] ?? [];

                return ($attempt['at'] ?? 0) <= microtime(true) - (($attempt['available'] ?? false) ? 1800 : 120);
            });
            if (! $due) {
                continue;
            }
            $key = 'Football:statistics:'.$matchId.':pending';
            $lock = Cache::store('redis')->lock($key, 3600);
            if (! $lock->get()) {
                continue;
            }
            $owner = $lock->owner();
            try {
                dispatch(static function (CallQueuedClosure $job) use ($matchId, $key, $owner): void {
                    $lock = Cache::store('redis')->restoreLock($key, $owner);
                    $retrying = false;
                    try {
                        app(self::class)->loadMatchStatistics($matchId);
                    } catch (LockTimeoutException) {
                        $retrying = releaseSportsJobAfterLockContention($job);
                    } finally {
                        if (! $retrying) {
                            $lock->release();
                        }
                    }
                })->name('Football pre-match statistics '.$matchId)->onConnection('sports-realtime')->onQueue('sports-prematch-statistics')->delay(abs(crc32($matchId)) % 50)
                    ->catch(static function (Throwable $exception) use ($key, $owner): void {
                        Cache::store('redis')->restoreLock($key, $owner)->release();
                        Log::warning('Football pre-match statistics job failed', ['exception' => $exception::class]);
                    });
            } catch (Throwable $exception) {
                $lock->release();
                throw $exception;
            }
        }
    }

    /** @return array<string, mixed>|null */
    public function loadMatchStatistics(string $matchId, bool $includePrematch = true): ?array
    {
        $match = array_find(getInRedis('Football:matches', readOnly: true, matchIds: [$matchId]) ?? [], static fn (array $match): bool => (string) ($match['sportsApiPro_match_id'] ?? '') === $matchId && ! MatchBackup::isHidden($match, 'football'));
        if ($match === null || ! app()->runningInConsole()) {
            return $match;
        }
        $lock = Cache::store('redis')->lock('Football:statistics:'.$matchId.':fetch', 60);
        if (! $lock->get()) {
            return $match;
        }
        $seasonLocks = [];
        try {
            $responses = [];
            $attempts = [];
            $sections = $includePrematch ? self::prematchStatisticsSections($match['result_status'] ?? null) : [];
            if (! $includePrematch && ($match['result_status'] ?? '') !== 'Prematch') {
                $sections[] = 'statistics';
            }
            foreach (array_chunk($sections, 4) as $batch) {
                $requests = [];
                $requestedTimes = [];
                foreach ($batch as $section) {
                    $lastAttempt = $match['statistics_requests'][$section] ?? [];
                    $ttl = ($lastAttempt['available'] ?? false)
                        ? ($section === 'statistics' ? (($match['result_status'] ?? '') === 'Finished' ? 86400 : 30) : 1800)
                        : 120;
                    if (($lastAttempt['at'] ?? 0) > microtime(true) - $ttl
                        || ($section === 'statistics' && ($match['statistics_updated_at'] ?? 0) > microtime(true) - 30)) {
                        continue;
                    }
                    $requestedAt = microtime(true);
                    $diagnostics = null;
                    $url = 'https://api.sportsapipro.com/v2/football/match/'.$matchId.($section === 'overview' ? '' : '/'.$section);
                    if (in_array($section, ['home-season', 'away-season'], true)) {
                        $overview = $responses['overview']['data'] ?? $match['prematch_statistics']['overview'] ?? [];
                        $side = $section === 'home-season' ? 'home' : 'away';
                        $teamId = $overview[$side.'Team']['id'] ?? $match[$side.'_team_id'] ?? null;
                        $tournamentId = $overview['tournament']['uniqueTournament']['id'] ?? null;
                        $seasonId = $overview['season']['id'] ?? null;
                        if (! is_numeric($teamId) || (int) $teamId <= 0 || ! is_numeric($tournamentId) || (int) $tournamentId <= 0 || ! is_numeric($seasonId) || (int) $seasonId <= 0) {
                            $attempts[$section] = ['at' => $requestedAt, 'available' => false];

                            continue;
                        }
                        $url = 'https://api.sportsapipro.com/v2/football/teams/'.(int) $teamId.'/tournament/'.(int) $tournamentId.'/season/'.(int) $seasonId.'/statistics?type=overall';
                        $seasonKey = 'Football:season-statistics:'.hash('sha256', $url);
                        $cached = Cache::store('redis')->get($seasonKey);
                        if ($cached === null && ! isset($seasonLocks[$url])) {
                            $seasonLock = Cache::store('redis')->lock($seasonKey.':fetch', 60);
                            if ($seasonLock->get()) {
                                $seasonLocks[$url] = $seasonLock;
                                $cached = Cache::store('redis')->get($seasonKey);
                            }
                        }
                        if ($cached !== null || ! isset($seasonLocks[$url])) {
                            $available = isset($cached['data']);
                            if ($available) {
                                $responses[$section] = $cached;
                            }
                            $attempts[$section] = ['at' => $requestedAt, 'available' => $available];

                            continue;
                        }
                    }
                    $requests[$section] = $url;
                    $requestedTimes[$section] = $requestedAt;
                }
                $uniqueRequests = array_unique($requests);
                $batchDiagnostics = null;
                $batchResponses = $uniqueRequests === [] ? [] : $this->requestFootballStatistics($uniqueRequests, $batchDiagnostics);
                $requestSections = array_flip($uniqueRequests);
                foreach ($requests as $section => $url) {
                    $requestedAt = $requestedTimes[$section];
                    $requestSection = $requestSections[$url];
                    $diagnostics = null;
                    try {
                        if (($batchResponses[$requestSection]['exception'] ?? null) instanceof Throwable) {
                            throw $batchResponses[$requestSection]['exception'];
                        }
                        $response = $batchResponses[$requestSection]['response'] ?? null;
                        $diagnostics = $batchResponses[$requestSection]['diagnostics'] ?? null;
                        $payload = $response['data'] ?? $response;
                        if ($section === 'overview') {
                            $payload = $response['match'] ?? $payload['match'] ?? $payload['event'] ?? $payload;
                        } elseif (in_array($section, ['home-season', 'away-season'], true)) {
                            $payload = $payload['statistics'] ?? [];
                        }
                        $expectedKeys = match ($section) {
                            'overview' => ['venue', 'referee', 'startTimestamp', 'roundInfo', 'Info', 'season'],
                            'home-season', 'away-season' => ['avgRating', 'matches', 'awardedMatches'],
                            'statistics' => ['statistics'],
                            'pregame-form' => ['homeTeam', 'awayTeam'],
                            'h2h' => ['teamDuel', 'managerDuel'],
                            'streaks' => ['general', 'head2head'],
                        };
                        $available = is_array($response) && ($response['success'] ?? true) !== false
                            && is_array($payload) && array_any($expectedKeys, static fn (string $key): bool => array_key_exists($key, $payload))
                            && (! isset($response['matchId']) || (string) $response['matchId'] === $matchId)
                            && ($section !== 'overview' || ! isset($payload['id']) || (string) $payload['id'] === $matchId)
                            && (! isset($diagnostics['http_status']) || ($diagnostics['http_status'] >= 200 && $diagnostics['http_status'] < 300));
                        if ($available) {
                            if ($section === 'overview') {
                                $payload = array_intersect_key($payload, array_flip(['venue', 'referee', 'startTimestamp', 'roundInfo', 'Info', 'season', 'tournament', 'homeTeam', 'awayTeam']));
                                foreach (['homeTeam', 'awayTeam'] as $team) {
                                    $payload[$team] = array_intersect_key(is_array($payload[$team] ?? null) ? $payload[$team] : [], array_flip(['id', 'name']));
                                }
                            } elseif (in_array($section, ['home-season', 'away-season'], true)) {
                                $payload = array_intersect_key($payload, array_flip($expectedKeys));
                            }
                            $responses[$section] = ['data' => $payload, 'at' => $requestedAt];
                        } elseif (($diagnostics['skipped'] ?? null) !== 'quota_reserve') {
                            Log::warning('Football statistics response unavailable', ['match_id' => $matchId, 'section' => $section, 'api' => $diagnostics]);
                        }
                    } catch (Throwable $exception) {
                        $available = false;
                        Log::warning('Football statistics request failed', ['match_id' => $matchId, 'section' => $section, 'api' => $diagnostics, 'exception' => $exception::class]);
                    }
                    if (isset($seasonLocks[$url]) && $requestSection === $section) {
                        $seasonKey = 'Football:season-statistics:'.hash('sha256', $url);
                        if ($available) {
                            Cache::store('redis')->put($seasonKey, $responses[$section], 21600);
                        } elseif (in_array($diagnostics['http_status'] ?? null, [404, 410], true)) {
                            Cache::store('redis')->put($seasonKey, ['unavailable' => true], 21600);
                        } elseif (($diagnostics['skipped'] ?? null) === null && ($diagnostics['http_status'] ?? null) !== 429) {
                            Cache::store('redis')->put($seasonKey, ['unavailable' => true], 300);
                        }
                    }
                    $attempts[$section] = ['at' => $requestedAt, 'available' => $available];
                }
            }
            if ($attempts === []) {
                return $match;
            }

            $updatedMatch = withRedisLock('Football:matches', function () use ($matchId, $responses, $attempts): ?array {
                $index = getInRedis('Football:matches', fixtureIndex: true);
                $matches = $index === null ? null : getInRedis('Football:matches', readOnly: true, matchIds: [$matchId], snapshot: $index);
                if ($matches === null) {
                    $index = null;
                    $matches = getInRedis('Football:matches', readOnly: true) ?? [];
                }
                $key = array_find_key($matches, static fn (array $match): bool => (string) ($match['sportsApiPro_match_id'] ?? '') === $matchId && ! MatchBackup::isHidden($match, 'football'));
                if ($key === null) {
                    return null;
                }
                $beforeUpdate = $matches;
                foreach ($responses as $section => $response) {
                    if ($section === 'statistics') {
                        $this->applyMatchStatistics($matches[$key], $response['data'], $response['at']);
                    } else {
                        $matches[$key]['prematch_statistics'][$section] = $response['data'];
                    }
                }
                $matches[$key]['statistics_requests'] = array_replace($matches[$key]['statistics_requests'] ?? [], $attempts);
                storeInRedis('Football:matches', $matches, 10, false, $beforeUpdate, replace: true, fixtureSnapshot: $index);

                return $matches[$key];
            });
            if ($updatedMatch !== null) {
                Event::dispatch(new FootballSportsScoreUpdated);
            }

            return $updatedMatch;
        } finally {
            foreach ($seasonLocks as $seasonLock) {
                $seasonLock->release();
            }
            $lock->release();
        }
    }

    protected function requestFootballStatistics(string|array $url, ?array &$diagnostics = null, int $timeout = 6): mixed
    {
        if (is_array($url)) {
            $requests = [];
            foreach ($url as $section => $endpoint) {
                $requests[$section] = ['url' => $endpoint, 'key' => ['x-api-key' => GetSportApiProKey()], 'options' => [
                    'connect_timeout' => 3, 'timeout' => in_array($section, ['overview', 'home-season', 'away-season'], true) ? 3 : 6,
                ]];
            }

            return ApiCallPool($requests, (int) config('services.sports_api_pro.http_concurrency', 2));
        }

        return ApiCall($url, ['x-api-key' => GetSportApiProKey()], ['connect_timeout' => 3, 'timeout' => $timeout], $diagnostics);
    }

    /** @param array<string, mixed> $payload */
    public function mergeV6TeamStats(int|string $matchId, string $v6Id, array $payload, float $requestedAt, ?string $dictionaryVersion = null): bool
    {
        $fullMatch = $payload['matchStats'][0]['stats'] ?? null;
        if (! is_array($fullMatch) || (isset($payload['matchId']) && (string) $payload['matchId'] !== $v6Id)) {
            return false;
        }
        $stats = [];
        foreach (['123' => 'attacks', '124' => 'dangerous_attacks', '103' => 'yellow_cards', '104' => 'red_cards', '102' => 'corners'] as $id => $name) {
            $values = $fullMatch[$id]['values'] ?? [];
            $pair = ['home' => null, 'away' => null];
            foreach (['home' => 0, 'away' => 1] as $side => $index) {
                if (is_numeric($values[$index] ?? null) && is_finite((float) $values[$index]) && (float) $values[$index] >= 0) {
                    $pair[$side] = (int) $values[$index];
                }
            }
            if (in_array($name, ['attacks', 'dangerous_attacks'], true) || ($pair['home'] !== null && $pair['away'] !== null)) {
                $stats[$name] = $pair;
            }
        }
        $changed = withRedisLock('Football:matches', static function () use ($matchId, $v6Id, $stats, $requestedAt, $dictionaryVersion): array {
            $index = getInRedis('Football:matches', fixtureIndex: true);
            $matches = $index === null ? null : getInRedis('Football:matches', matchIds: [$matchId], snapshot: $index);
            $existingMatches = null;
            if ($matches === null) {
                $index = null;
                $matches = getInRedis('Football:matches') ?? [];
                $existingMatches = $matches;
            }
            $persist = [];
            $updated = false;
            $liveStats = app(FootballV6Stats::class);
            foreach ($matches as $key => &$match) {
                if ((string) ($match['sportsApiPro_match_id'] ?? '') !== (string) $matchId || ! $liveStats->isLive($match)
                    || ($match['v6_stats']['updated_at'] ?? 0) > $requestedAt) {
                    continue;
                }
                $before = $match['live_stats'] ?? [];
                $newMapping = ($match['sportsApiPro_v6_match_id'] ?? null) !== $v6Id;
                $match['sportsApiPro_v6_match_id'] = $v6Id;
                foreach (['attacks' => 'Attacks', 'dangerous_attacks' => 'Dangerous Attacks'] as $stat => $label) {
                    $match['live_stats'][$stat] = $stats[$stat];
                    $match['live_stats_sources'][$stat] = 'v6';
                    $match['live_stats_meta'][$stat] = ['label' => $label, 'percent' => false, 'source' => 'v6'];
                }
                $counts = [];
                foreach (['yellow_cards', 'red_cards', 'corners'] as $stat) {
                    foreach ($stats[$stat] ?? [] as $side => $count) {
                        $counts[$side.'_'.$stat] = $count;
                    }
                }
                app(self::class)->storeTimeline($match, payload: ['timeline_counts' => $counts]);
                $match['v6_stats'] = [
                    'updated_at' => $requestedAt, 'last_attempt_at' => (int) $requestedAt,
                    'next_poll_at' => (int) $requestedAt + 60, 'dictionary_version' => $dictionaryVersion,
                ];
                $updated = true;
                if ($newMapping || $before !== $match['live_stats']) {
                    $persist[$key] = $match;
                    unset($persist[$key]['markets']);
                }
            }
            unset($match);
            if ($updated) {
                storeInRedis('Football:matches', $matches, 30, true, $existingMatches, fixtureSnapshot: $index);
            }

            return $persist;
        });
        if ($changed !== []) {
            $this->storeMatchesInDatabase($changed);
            Event::dispatch(new FootballSportsScoreUpdated);
        }

        return $changed !== [];
    }

    public static function allowsFootballCompetition(array $match): bool
    {
        static $controller = null;
        $controller ??= app(FootballController::class);

        return $controller->footballLeaguePriority(
            (string) ($match['tournament'] ?? ''),
            $match['tournament_country'] ?? null,
        ) !== PHP_INT_MAX;
    }

    /** @return array{string, string}|null */
    public static function pinnacleCompetition(string $country, string $league): ?array
    {
        static $competitions = null;
        if ($competitions === null) {
            $competitions = [];
            foreach (config('services.pinnodds.football_priority', []) as [$name, $competition, $aliases]) {
                foreach ([$name.' - '.$competition, ...$aliases] as $label) {
                    $competitions[mb_strtolower($label)] = [$name, $competition];
                }
            }
        }

        return $competitions[mb_strtolower($country.' - '.$league)] ?? null;
    }

    /** @param list<array<string, mixed>> $alerts */
    public function mergePinnacleDrops(array $alerts, bool $deferIfBusy = false): bool
    {
        $now = time();
        $alerts = array_filter($alerts, static fn (array $alert): bool => strtolower((string) ($alert['sport'] ?? '')) === 'soccer'
            && ! ($alert['is_live'] ?? true) && (int) ($alert['period'] ?? 0) === 0 && empty($alert['special'])
            && (int) ($alert['alerted'] ?? 0) >= $now - 10800 && (int) ($alert['alerted'] ?? 0) <= $now + 30);
        if ($alerts === []) {
            return true;
        }
        $changed = withRedisLock('Football:matches', function () use ($alerts): array {
            $matches = getInRedis('Football:matches') ?? [];
            $existingMatches = $matches;
            $changed = [];
            $byKickoff = [];
            foreach ($matches as $key => $match) {
                if (($match['result_status'] ?? '') === 'Prematch' && ! empty($match['sportsApiPro_match_id'])) {
                    $byKickoff[intdiv((int) ($match['timestamp'] ?? 0), 300)][] = $key;
                }
            }
            foreach ($alerts as $alert) {
                $starts = $alert['starts'] ?? null;
                $starts = is_numeric($starts) ? (int) $starts : (is_string($starts) ? strtotime($starts) : false);
                if ($starts > 100_000_000_000) {
                    $starts = (int) floor($starts / 1000);
                }
                if (! $starts || ! is_numeric($alert['from_price'] ?? null) || ! is_numeric($alert['to_price'] ?? null)) {
                    continue;
                }
                $from = (float) $alert['from_price'];
                $to = (float) $alert['to_price'];
                if (! is_finite($from) || ! is_finite($to) || $from <= 1 || $to <= 1 || $to >= $from) {
                    continue;
                }
                $key = null;
                $kickoffBucket = intdiv($starts, 300);
                for ($bucket = $kickoffBucket - 1; $bucket <= $kickoffBucket + 1; $bucket++) {
                    foreach ($byKickoff[$bucket] ?? [] as $candidateKey) {
                        $candidate = $matches[$candidateKey];
                        if (abs((int) ($candidate['timestamp'] ?? 0) - $starts) > 300
                            || $this->teamSimilarity($candidate['team1'] ?? '', (string) ($alert['home'] ?? '')) < 80
                            || $this->teamSimilarity($candidate['team2'] ?? '', (string) ($alert['away'] ?? '')) < 80) {
                            continue;
                        }
                        if ($key !== null) {
                            continue 3;
                        }
                        $key = $candidateKey;
                    }
                }
                if ($key === null) {
                    continue;
                }
                $selection = hash('sha256', implode('|', [$alert['id'], $alert['period'] ?? 0, $alert['sect'] ?? '', $alert['outcome'] ?? '', $alert['point'] ?? $alert['points'] ?? $alert['hdp'] ?? '']));
                $state = $matches[$key]['pinnacle_markets'][$selection] ?? [];
                $at = (int) $alert['alerted'];
                if ($at < (int) ($state['updated_at'] ?? 0)) {
                    continue;
                }
                $display = [
                    'sect' => (string) ($alert['sect'] ?? ''), 'outcome' => (string) ($alert['outcome'] ?? ''),
                    'period' => (int) ($alert['period'] ?? 0),
                    'point' => $alert['point'] ?? $alert['points'] ?? $alert['hdp'] ?? null, 'from_price' => $from,
                ];
                if (($state['alert_key'] ?? null) === ($alert['alert_key'] ?? json_encode($alert))) {
                    if (array_diff_key($display, $state) !== []) {
                        $matches[$key]['pinnacle_markets'][$selection] = array_replace($display, $state);
                        $changed[$key] = $matches[$key];
                    }

                    continue;
                }
                $opening = hasMeaningfulOpeningOdds($state) ? $state : updateOddsMonitoring(['backOdds' => $from], [], $at);
                $opening = updateOddsMonitoring(['backOdds' => $to], $opening, $at);
                $baseline = (float) $opening['initialBackOdds'];
                $dropTriggered = ($state['drop_triggered'] ?? false) || round(($baseline - $to) / $baseline * 100, 8) >= 1;
                $snapshots = array_values(array_filter($state['snapshots'] ?? [], static fn (array $snapshot): bool => $snapshot['timestamp'] >= $at - 180 && $snapshot['timestamp'] <= $at));
                $interval = $alert['interval'] ?? null;
                if (is_numeric($interval) && (float) $interval >= 0 && (float) $interval <= 180) {
                    $snapshots[] = ['timestamp' => $at - (int) ceil((float) $interval), 'odds' => $from];
                }
                $section = strtolower(trim((string) ($alert['sect'] ?? '')));
                $outcome = strtolower(trim((string) ($alert['outcome'] ?? '')));
                $line = $alert['point'] ?? $alert['points'] ?? $alert['hdp'] ?? null;
                if (! is_numeric($line) && preg_match('/(?:over|under|total|o\/u)\s*([0-9]+(?:\.[0-9]+)?)/i', $section.' '.$outcome, $lineMatch)) {
                    $line = $lineMatch[1];
                }
                $shockMarket = in_array($section, ['moneyline', 'money line', '1x2', 'match odds', 'both teams to score', 'btts'], true)
                    || (preg_match('/^(total|totals|o\/u)/', $section) === 1 && is_numeric($line) && in_array((float) $line, [1.5, 2.5], true));
                $shockAt = (int) ($state['shock_detected_at'] ?? 0);
                if ($shockMarket && $snapshots !== []) {
                    $comparison = max(array_column($snapshots, 'odds'));
                    $threshold = $comparison >= 1.20 && $comparison <= 1.99 ? 5
                        : ($comparison >= 2.00 && $comparison <= 3.99 ? 8 : ($comparison >= 4.00 && $comparison <= 20.00 ? 12 : null));
                    if ($threshold !== null && round(($comparison - $to) / $comparison * 100, 8) >= $threshold && $shockAt < $at - 1200) {
                        $shockAt = $at;
                    }
                }
                $snapshots[] = ['timestamp' => $at, 'odds' => $to];
                $matches[$key]['pinnacle_markets'][$selection] = array_merge($opening, $display, [
                    'monitoring_baseline_version' => 1,
                    'baseline_odds' => $baseline, 'current_odds' => $to, 'drop_triggered' => $dropTriggered,
                    'shock_detected_at' => $shockAt, 'snapshots' => array_slice($snapshots, -7),
                    'updated_at' => $at, 'alert_key' => $alert['alert_key'] ?? json_encode($alert),
                ]);
                $matches[$key]['pinnacle_drop_triggered'] = ($matches[$key]['pinnacle_drop_triggered'] ?? false) || $dropTriggered;
                $matches[$key]['pinnacle_market_shock_detected_at'] = max((int) ($matches[$key]['pinnacle_market_shock_detected_at'] ?? 0), $shockAt);
                $changed[$key] = $matches[$key];
            }
            if ($changed !== []) {
                storeInRedis('Football:matches', $matches, 30, true, $existingMatches);
            }

            return $changed;
        }, $deferIfBusy ? 0 : 5);
        if ($changed === null) {
            return false;
        }
        if ($changed !== []) {
            $this->storeMatchesInDatabase($changed);
            Event::dispatch(new FootballSportsScoreUpdated);
        }

        return true;
    }

    private function readBetFairData(array $payloads, string $cacheKey, bool $isLiveApi, int $ttlSeconds)
    {
        $getExistingRecords = getInRedis($cacheKey) ?? [];
        $mainArray = array_filter($getExistingRecords, static fn (array $match): bool => ($match['result_status'] ?? null) === 'Finished');
        $databaseMarkets = [];
        $databaseLookupKeys = [];
        $payloadLookups = [];

        foreach (($payloads['liveMap'] ?? []) as $payloadKey => $payload) {
            if (! isset($payload['openTimestamp'], $payload['team1'], $payload['team2'])) {
                continue;
            }

            $payloadTimestamp = Carbon::parse($payload['openTimestamp'])->timestamp;
            $payloadUniqueKey = $this->generateUniqueKey($payload['team1'], $payload['team2'], $payloadTimestamp);
            $existingKey = $this->checkIfRecordExistsOnAnotherApi(
                $payloadUniqueKey,
                $payload['team1'],
                $payload['team2'],
                $payloadTimestamp,
                $getExistingRecords,
                betfairMatchId: $payloadKey
            );
            $payloadLookups[$payloadKey] = [$payloadTimestamp, $payloadUniqueKey, $existingKey];
            if (($getExistingRecords[$existingKey ?? $payloadUniqueKey]['result_status'] ?? null) === 'Finished') {
                continue;
            }
            $existingMarkets = $getExistingRecords[$existingKey ?? $payloadUniqueKey]['markets'] ?? [];

            if ($existingMarkets === []) {
                $databaseLookupKeys[$payloadUniqueKey] = true;
                if ($existingKey !== null) {
                    $databaseLookupKeys[$existingKey] = true;
                }
            }
        }

        if ($databaseLookupKeys !== []) {
            $databaseRows = FootballMarket::query()
                ->whereIn('football_match_unique_key', array_keys($databaseLookupKeys))
                ->get(['football_match_unique_key', 'payload']);

            foreach ($databaseRows as $databaseRow) {
                $storedMarket = $databaseRow->payload;
                if (is_array($storedMarket)) {
                    $databaseMarkets[$databaseRow->football_match_unique_key][] = $storedMarket;
                }
            }
        }

        foreach (($payloads['liveMap'] ?? []) as $payloadKey => $payload) {
            if (! isset($payload['openTimestamp'], $payload['team1'], $payload['team2'])) {
                continue;
            }
            [$currentTimestamp, $uniqueKey, $checkIsExists] = $payloadLookups[$payloadKey];
            $team1 = $payload['team1'];
            $team2 = $payload['team2'];
            $uniqueKey = $checkIsExists ?? $uniqueKey;
            if ($checkIsExists !== null && ($getExistingRecords[$checkIsExists]['result_status'] ?? null) === 'Finished') {
                $mainArray[$checkIsExists] = $getExistingRecords[$checkIsExists];

                continue;
            }
            if ($checkIsExists) {
                $tempArray = $getExistingRecords[$checkIsExists];
                $data_sync_complete = isset($getExistingRecords[$checkIsExists]['sportsApiPro_match_id']);
                $tempArray['data_sync_complete'] = $data_sync_complete;
            } else {
                $tempArray = [];
                $tempArray['data_sync_complete'] = false;
            }

            $getSingleRecords = $checkIsExists ? ($getExistingRecords[$checkIsExists] ?? []) : ($getExistingRecords[$uniqueKey] ?? []);

            $currentStatus = $isLiveApi == true || ($getSingleRecords['result_status'] ?? '') === 'Live' ? 'Live' : 'Prematch';
            $result_status = $currentStatus;
            $tempArray['unique_key'] = $uniqueKey;
            $tempArray['betfair_match_id'] = $payloadKey;
            $tempArray['team1'] = $team1;
            $tempArray['team2'] = $team2;
            $tempArray['tournament'] = $payload['tournament'];
            $tempArray['result_status'] = $result_status;
            $tempArray['minutes_status'] = $this->alignMinuteStatusWithResult(
                $tempArray['minutes_status'] ?? null,
                $result_status
            );
            $tempArray['timestamp'] = $currentTimestamp;
            $markets = $this->filterSupportedMarkets($payload['markets']);
            $tempMarketArray = [];
            $marketLiquidity = [];
            $now = time();

            foreach ($markets as $market) {
                $liquidityKey = $market['marketId'].'_'.$market['marketType'];
                $marketLiquidity[$liquidityKey] = ($marketLiquidity[$liquidityKey] ?? 0)
                    + (float) ($market['backSize'] ?? 0)
                    + (float) ($market['laySize'] ?? 0);
            }

            foreach ($markets as $market) {
                $sortPriority = implode('|', [$market['runnerId'] ?? $market['runner'] ?? '', $market['handicap'] ?? 0]);
                $marketKey = $market['marketId'].'_'.$market['marketType'];

                $previousMarket = $getSingleRecords['markets'][$marketKey][$sortPriority] ?? [];

                if ($previousMarket === []) {
                    foreach (($getSingleRecords['markets'] ?? []) as $previousMarketRunners) {
                        foreach (($previousMarketRunners ?? []) as $candidate) {
                            $sameRunner = isset($market['runnerId'], $candidate['runnerId'])
                                ? (string) $candidate['runnerId'] === (string) $market['runnerId']
                                : strcasecmp((string) ($candidate['runner'] ?? ''), (string) ($market['runner'] ?? '')) === 0;

                            if (
                                ($candidate['marketId'] ?? null) === ($market['marketId'] ?? null)
                                && ($candidate['marketType'] ?? null) === ($market['marketType'] ?? null)
                                && $sameRunner
                                && (float) ($candidate['handicap'] ?? 0) === (float) ($market['handicap'] ?? 0)
                            ) {
                                $previousMarket = $candidate;
                                break 2;
                            }
                        }
                    }
                }

                if ($previousMarket === []) {
                    foreach (array_merge(
                        $databaseMarkets[$checkIsExists] ?? [],
                        $databaseMarkets[$uniqueKey] ?? []
                    ) as $candidate) {
                        $sameRunner = isset($market['runnerId'], $candidate['runnerId'])
                            ? (string) $candidate['runnerId'] === (string) $market['runnerId']
                            : strcasecmp((string) ($candidate['runner'] ?? ''), (string) ($market['runner'] ?? '')) === 0;

                        if (
                            ($candidate['marketId'] ?? null) === ($market['marketId'] ?? null)
                            && ($candidate['marketType'] ?? null) === ($market['marketType'] ?? null)
                            && $sameRunner
                            && (float) ($candidate['handicap'] ?? 0) === (float) ($market['handicap'] ?? 0)
                        ) {
                            $previousMarket = $candidate;
                            break;
                        }
                    }
                }

                $previousMarket = prepareOddsMonitoring($previousMarket);
                $market = updateOddsMonitoring($market, $previousMarket, $now);
                $previousBackOdds = $market['previousBackOdds'];
                $currentOdds = $market['currentBackOdds'];
                $initialBackOdds = (float) ($market['initialBackOdds'] ?? 0);

                if (! $isLiveApi && $currentOdds > 0 && $initialBackOdds > 1) {
                    $baselineOdds = (float) ($previousMarket['prematch_drop_baseline_odds'] ?? 0);
                    if ($baselineOdds <= 0 && ($marketLiquidity[$marketKey] ?? 0) >= 1000) {
                        $baselineOdds = $initialBackOdds;
                    }

                    $market['prematch_drop_baseline_odds'] = $baselineOdds ?: null;
                    $market['prematch_drop_triggered'] = (bool) ($previousMarket['prematch_drop_triggered'] ?? false);
                    $market['prematch_drop_detected_at'] = $previousMarket['prematch_drop_detected_at'] ?? null;

                    if ($baselineOdds > 0 && ! $market['prematch_drop_triggered']) {
                        $previousDrop = (($baselineOdds - (float) $previousBackOdds) / $baselineOdds) * 100;
                        $currentDrop = (($baselineOdds - (float) $currentOdds) / $baselineOdds) * 100;
                        if ($previousDrop < 1 && $currentDrop >= 1) {
                            $market['prematch_drop_triggered'] = true;
                            $market['prematch_drop_detected_at'] = $now;
                        }
                    }

                    if (in_array($market['marketType'], ['MATCH_ODDS', 'OVER_UNDER_15', 'OVER_UNDER_25', 'BOTH_TEAMS_TO_SCORE'], true)) {
                        $snapshots = array_values(array_filter(
                            $previousMarket['market_shock_snapshots'] ?? [],
                            static fn (array $snapshot): bool => (int) ($snapshot['timestamp'] ?? 0) >= ($now - 180)
                        ));
                        $shockDetectedAt = (int) ($previousMarket['market_shock_detected_at'] ?? 0);

                        if (empty($snapshots)) {
                            $snapshots[] = ['timestamp' => $now, 'odds' => (float) $previousBackOdds];
                        }

                        if ((float) $previousBackOdds !== (float) $currentOdds) {
                            $comparisonOdds = max(array_column($snapshots, 'odds'));
                            $requiredDrop = $comparisonOdds >= 1.20 && $comparisonOdds <= 1.99
                                ? 5
                                : ($comparisonOdds >= 2.00 && $comparisonOdds <= 3.99
                                    ? 8
                                    : ($comparisonOdds >= 4.00 && $comparisonOdds <= 20.00 ? 12 : null));
                            $shockDrop = $comparisonOdds > 0
                                ? (($comparisonOdds - (float) $currentOdds) / $comparisonOdds) * 100
                                : 0;

                            if ($requiredDrop !== null && $shockDrop >= $requiredDrop && $shockDetectedAt < ($now - 1200)) {
                                $shockDetectedAt = $now;
                            }

                            $snapshots[] = ['timestamp' => $now, 'odds' => (float) $currentOdds];
                        }

                        $market['market_shock_snapshots'] = array_slice($snapshots, -7);
                        $market['market_shock_detected_at'] = $shockDetectedAt ?: null;
                    }
                }

                $tempMarketArray[$marketKey][$sortPriority] = $market;
                $tempArray['markets'] = $tempMarketArray;
            }

            if (! $isLiveApi) {
                $favoriteOdds = null;
                $favoriteRunner = null;
                $favoriteSide = null;
                $homeName = $this->normalizeTeamName($team1);
                $awayName = $this->normalizeTeamName($team2);

                foreach ($tempMarketArray as $marketRunners) {
                    foreach ($marketRunners as $runner) {
                        $runnerName = $this->normalizeTeamName((string) ($runner['runner'] ?? ''));
                        $runnerOdds = (float) ($runner['currentBackOdds'] ?? 0);
                        if (
                            ($runner['marketType'] ?? null) === 'MATCH_ODDS'
                            && in_array($runnerName, [$homeName, $awayName], true)
                            && $runnerOdds > 0
                            && ($favoriteOdds === null || $runnerOdds < $favoriteOdds)
                        ) {
                            $favoriteOdds = $runnerOdds;
                            $favoriteRunner = $runner['runner'];
                            $favoriteSide = $runnerName === $homeName ? 'home' : 'away';
                        }
                    }
                }

                $tempArray['prematch_favorite_runner'] = $favoriteRunner;
                $tempArray['prematch_favorite_odds'] = $favoriteOdds;
                $tempArray['prematch_favorite_side'] = $favoriteSide;
            }
            unset($tempArray['events'], $tempArray['goals'], $tempArray['goal_counts'], $tempArray['status_description']);
            $mainArray[$uniqueKey] = $tempArray;
        }
        unset($databaseMarkets, $databaseLookupKeys, $getSingleRecords, $previousMarkets, $previousMarket, $candidate, $market, $marketRunners, $tempArray);
        storeInRedis($cacheKey, $mainArray, $ttlSeconds, true, $getExistingRecords);

        return $mainArray;
    }

    private function readsportApiData(array $payloads, string $cacheKey, bool $isLive, int $ttlSeconds): array
    {
        $payloads['events'] = array_filter($payloads['events'] ?? [], static fn (array $event): bool => self::allowsFootballCompetition([
            'tournament' => $event['tournament']['name'] ?? '',
            'tournament_country' => $event['tournament']['category']['name'] ?? null,
        ]));
        if (empty($payloads)) {
            return [];
        }
        $sportsImages = [];
        $getSportsImage = static function (string $type, mixed $entityId) use (&$sportsImages): ?string {
            $imageKey = $type.':'.(int) $entityId;
            if (! array_key_exists($imageKey, $sportsImages)) {
                $sportsImages[$imageKey] = getSportsApiProImage('football', $type, $entityId);
            }

            return $sportsImages[$imageKey];
        };

        foreach (($payloads['events'] ?? []) as $payload) {
            $homeTeam = is_array($payload['homeTeam'] ?? null)
                ? ($payload['homeTeam']['name'] ?? null)
                : ($payload['homeTeam'] ?? null);
            $awayTeam = is_array($payload['awayTeam'] ?? null)
                ? ($payload['awayTeam']['name'] ?? null)
                : ($payload['awayTeam'] ?? null);
            $startTimestamp = (int) ($payload['startTimestamp'] ?? 0);
            $statusType = is_array($payload['status'] ?? null) ? ($payload['status']['type'] ?? null) : null;

            if (
                ! isset($payload['id']) || ! is_string($homeTeam) || ! is_string($awayTeam) || $startTimestamp <= 0
                || (! $isLive && ($startTimestamp < time() || ($statusType !== null && $statusType !== 'notstarted')))
            ) {
                continue;
            }

            $getSportsImage('teams', is_array($payload['homeTeam']) ? ($payload['homeTeam']['id'] ?? null) : null);
            $getSportsImage('teams', is_array($payload['awayTeam']) ? ($payload['awayTeam']['id'] ?? null) : null);
            $getSportsImage('tournaments', is_array($payload['tournament'] ?? null) ? ($payload['tournament']['id'] ?? null) : null);
        }

        return withRedisLock($cacheKey, function () use ($payloads, $cacheKey, $isLive, $ttlSeconds, $sportsImages): array {
            $getExistingRecords = getInRedis($cacheKey) ?? [];
            $existingMatches = $getExistingRecords;
            $databaseMatches = array_filter($getExistingRecords, static fn (array $match): bool => ($match['result_status'] ?? null) === 'Finished');

            if (! $isLive) {
                $knownIds = array_filter(array_merge(array_column($getExistingRecords, 'sportsApiPro_match_id'), array_column($payloads['events'] ?? [], 'id')), static fn (mixed $id): bool => is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0);
                $storedMatches = FootballMatch::query()->with('markets')
                    ->where('result_status', 'Prematch')->where('start_timestamp', '>=', time())
                    ->where('sports_api_pro_match_id', '>', 0)->whereNotIn('sports_api_pro_match_id', $knownIds)->get();
                foreach ($storedMatches as $storedMatch) {
                    $existing = $getExistingRecords[$storedMatch->unique_key] ?? [];
                    if ($existing !== [] && (($existing['result_status'] ?? null) !== 'Prematch' || ! empty($existing['sportsApiPro_match_id']))) {
                        continue;
                    }
                    $restored = array_replace($storedMatch->payload ?? [], [
                        'unique_key' => $storedMatch->unique_key, 'sportsApiPro_match_id' => $storedMatch->sports_api_pro_match_id,
                        'betfair_match_id' => $storedMatch->betfair_match_id, 'team1' => $storedMatch->team1, 'team2' => $storedMatch->team2,
                        'tournament' => $storedMatch->tournament, 'result_status' => $storedMatch->result_status,
                        'minutes_status' => $storedMatch->minutes_status, 'timestamp' => $storedMatch->start_timestamp,
                        'data_sync_complete' => $storedMatch->data_sync_complete, 'markets' => [],
                    ]);
                    foreach ($storedMatch->markets->sortBy('sort_priority') as $storedMarket) {
                        $restored['markets'][$storedMarket->market_id.'_'.$storedMarket->market_type][] = $storedMarket->payload;
                    }
                    if (empty($existing['markets'])) {
                        unset($existing['markets']);
                    }
                    $restored = array_replace($restored, $existing, ['sportsApiPro_match_id' => $storedMatch->sports_api_pro_match_id]);
                    if (! MatchBackup::isHidden($restored, 'football')) {
                        $getExistingRecords[$storedMatch->unique_key] = $restored;
                        $databaseMatches[$storedMatch->unique_key] = $restored;
                    }
                }
                unset($storedMatches, $storedMatch, $storedMarket, $restored, $existing, $knownIds);
            }

            foreach (($payloads['events'] ?? []) as $payloadKey => $payload) {
                $homeTeam = is_array($payload['homeTeam'] ?? null)
                    ? ($payload['homeTeam']['name'] ?? null)
                    : ($payload['homeTeam'] ?? null);
                $awayTeam = is_array($payload['awayTeam'] ?? null)
                    ? ($payload['awayTeam']['name'] ?? null)
                    : ($payload['awayTeam'] ?? null);
                $startTimestamp = (int) ($payload['startTimestamp'] ?? 0);
                $status = $payload['status'] ?? null;
                $statusCode = is_array($status) ? ($status['code'] ?? null) : null;
                $statusType = is_array($status) ? ($status['type'] ?? null) : null;

                if (! isset($payload['id']) || ! is_string($homeTeam) || ! is_string($awayTeam) || $startTimestamp <= 0) {
                    continue;
                }

                if (! $isLive && ($startTimestamp < time() || ($statusType !== null && $statusType !== 'notstarted'))) {
                    continue;
                }

                $tempArray = [];
                $uniqueKey = $this->generateUniqueKey($homeTeam, $awayTeam, (string) $startTimestamp);
                $homeShortName = $payload['homeTeam']['shortName'] ?? null;
                $awayShortName = $payload['awayTeam']['shortName'] ?? null;
                $checkIsExists = $this->checkIfRecordExistsOnAnotherApi(
                    $uniqueKey,
                    $homeTeam,
                    $awayTeam,
                    $startTimestamp,
                    $getExistingRecords,
                    sportsApiMatchId: $payload['id'],
                    homeShortName: is_string($homeShortName) ? $homeShortName : null,
                    awayShortName: is_string($awayShortName) ? $awayShortName : null,
                );
                $existingRecord = $checkIsExists === null ? [] : ($getExistingRecords[$checkIsExists] ?? []);

                if (($existingRecord['result_status'] ?? null) === 'Finished') {
                    $databaseMatches[$checkIsExists] = $existingRecord;

                    continue;
                }

                $minutesStatus = $this->sportsApiMinuteStatus(
                    $statusCode,
                    $existingRecord['minutes_status'] ?? ($isLive ? 'Live' : 'Prematch')
                );
                if ($minutesStatus === 'Live' && (int) floor((time() - $startTimestamp) / 60) >= 60) {
                    $minutesStatus = '2nd half';
                }
                $resultStatus = $isLive
                    ? $this->sportsApiResultStatus($statusCode, $statusType, $existingRecord['result_status'] ?? 'Live')
                    : 'Prematch';
                $minutesStatus = $this->alignMinuteStatusWithResult($minutesStatus, $resultStatus);
                $homeScore = is_array($payload['homeScore'] ?? null)
                    ? ($payload['homeScore']['current'] ?? 0)
                    : ($payload['homeScore'] ?? 0);
                $awayScore = is_array($payload['awayScore'] ?? null)
                    ? ($payload['awayScore']['current'] ?? 0)
                    : ($payload['awayScore'] ?? 0);
                $homeTeamId = is_array($payload['homeTeam'] ?? null) ? ($payload['homeTeam']['id'] ?? null) : null;
                $awayTeamId = is_array($payload['awayTeam'] ?? null) ? ($payload['awayTeam']['id'] ?? null) : null;
                $tournamentData = is_array($payload['tournament'] ?? null) ? $payload['tournament'] : [];
                $tournament = $tournamentData !== [] ? ($tournamentData['name'] ?? '') : ($payload['tournament'] ?? '');
                $tournamentId = $tournamentData['id'] ?? null;
                $countryId = $tournamentData['category']['country']['id'] ?? null;
                $timeData = is_array($payload['time'] ?? null) ? $payload['time'] : [];
                $currentPeriodStartTimestamp = $timeData['currentPeriodStartTimestamp'] ?? null;
                $lastPeriodEndTimestamp = $timeData['lastPeriodEndTimestamp'] ?? null;
                $addedTime = array_filter([
                    'first_half' => isset($timeData['injuryTime1']) && is_numeric($timeData['injuryTime1'])
                        ? (int) $timeData['injuryTime1']
                        : null,
                    'second_half' => isset($timeData['injuryTime2']) && is_numeric($timeData['injuryTime2'])
                        ? (int) $timeData['injuryTime2']
                        : null,
                ], static fn (mixed $value): bool => $value !== null);
                $homeTeamLogo = $existingRecord['home_team_logo'] ?? null;
                $awayTeamLogo = $existingRecord['away_team_logo'] ?? null;
                $tournamentLogo = $existingRecord['tournament_logo'] ?? null;
                $homeTeamLogo = is_string($homeTeamLogo) && File::isFile(public_path(ltrim($homeTeamLogo, '/')))
                    ? $homeTeamLogo
                    : ($sportsImages['teams:'.(int) $homeTeamId] ?? null);
                $awayTeamLogo = is_string($awayTeamLogo) && File::isFile(public_path(ltrim($awayTeamLogo, '/')))
                    ? $awayTeamLogo
                    : ($sportsImages['teams:'.(int) $awayTeamId] ?? null);
                $tournamentLogo = is_string($tournamentLogo) && File::isFile(public_path(ltrim($tournamentLogo, '/')))
                    ? $tournamentLogo
                    : ($sportsImages['tournaments:'.(int) $tournamentId] ?? null);

                if ($checkIsExists) {
                    $data_sync_complete = isset($getExistingRecords[$checkIsExists]['betfair_match_id']);
                    $additionalData = array_filter([
                        'data_sync_complete' => $data_sync_complete,
                        'home_score' => $homeScore,
                        'sportsApiPro_match_id' => $payload['id'],
                        'timeline' => $existingRecord['timeline'] ?? [],
                        'away_score' => $awayScore,
                        'minutes_status' => $minutesStatus,
                        'result_status' => $resultStatus,
                        'home_team_id' => $homeTeamId,
                        'away_team_id' => $awayTeamId,
                        'home_team_logo' => $homeTeamLogo,
                        'away_team_logo' => $awayTeamLogo,
                        'tournament_logo' => $tournamentLogo,
                        'tournament_id' => $tournamentId,
                        'tournament_country' => $tournamentData['category']['name'] ?? null,
                        'country_id' => $countryId,
                        'current_period_start_timestamp' => is_numeric($currentPeriodStartTimestamp) && (int) $currentPeriodStartTimestamp > 0
                            ? (int) $currentPeriodStartTimestamp
                            : null,
                        'last_period_end_timestamp' => is_numeric($lastPeriodEndTimestamp) && (int) $lastPeriodEndTimestamp > 0
                            ? (int) $lastPeriodEndTimestamp
                            : null,
                        'added_time' => $addedTime !== [] ? array_replace($getExistingRecords[$checkIsExists]['added_time'] ?? [], $addedTime) : null,
                        // 'status_type' => $payload['status']['type'] ?? null,
                    ], static fn (mixed $value): bool => $value !== null);
                    $existingLive = ($existingRecord['result_status'] ?? '') === 'Live';
                    $staleLiveSnapshot = $isLive && $resultStatus !== 'Finished'
                        && (MatchBackup::isOlderEvent($existingRecord, $payload) || ($existingLive && $statusType === 'notstarted'));
                    if ((! $isLive && $existingLive) || $staleLiveSnapshot) {
                        $additionalData = array_diff_key($additionalData, array_flip([
                            'home_score', 'away_score', 'minutes_status', 'result_status',
                            'current_period_start_timestamp', 'last_period_end_timestamp', 'added_time',
                        ]));
                    }
                    $getExistingRecords[$checkIsExists] = array_merge($getExistingRecords[$checkIsExists], $additionalData);
                    unset($getExistingRecords[$checkIsExists]['status_description']);
                    unset($getExistingRecords[$checkIsExists]['events'], $getExistingRecords[$checkIsExists]['goals'], $getExistingRecords[$checkIsExists]['goal_counts']);
                    $databaseMatches[$checkIsExists] = $getExistingRecords[$checkIsExists];
                } else {
                    if (isset($getExistingRecords[$uniqueKey])) {
                        $uniqueKey .= '|'.$payload['id'];
                    }
                    $tempArray['unique_key'] = $uniqueKey;
                    $tempArray['sportsApiPro_match_id'] = $payload['id'];
                    $tempArray['timeline'] = [];
                    $tempArray['timestamp'] = $startTimestamp;
                    $tempArray['data_sync_complete'] = false;
                    $tempArray['home_score'] = $homeScore;
                    $tempArray['away_score'] = $awayScore;
                    $tempArray['minutes_status'] = $minutesStatus;
                    $tempArray['result_status'] = $resultStatus;
                    // $tempArray['status_type'] = $payload['status']['type'] ?? null;
                    $tempArray['team1'] = $homeTeam;
                    $tempArray['team2'] = $awayTeam;
                    $tempArray['tournament'] = $tournament;
                    $tempArray['home_team_id'] = $homeTeamId;
                    $tempArray['away_team_id'] = $awayTeamId;
                    $tempArray['home_team_logo'] = $homeTeamLogo;
                    $tempArray['away_team_logo'] = $awayTeamLogo;
                    $tempArray['tournament_logo'] = $tournamentLogo;
                    $tempArray['tournament_id'] = $tournamentId;
                    $tempArray['tournament_country'] = $tournamentData['category']['name'] ?? null;
                    $tempArray['country_id'] = $countryId;
                    if (is_numeric($currentPeriodStartTimestamp) && (int) $currentPeriodStartTimestamp > 0) {
                        $tempArray['current_period_start_timestamp'] = (int) $currentPeriodStartTimestamp;
                    }
                    if (is_numeric($lastPeriodEndTimestamp) && (int) $lastPeriodEndTimestamp > 0) {
                        $tempArray['last_period_end_timestamp'] = (int) $lastPeriodEndTimestamp;
                    }
                    if ($addedTime !== []) {
                        $tempArray['added_time'] = $addedTime;
                    }
                    $getExistingRecords[$uniqueKey] = $tempArray;
                    $databaseMatches[$uniqueKey] = $tempArray;
                }
            }
            storeInRedis($cacheKey, $getExistingRecords, $ttlSeconds, true, $existingMatches);

            return $databaseMatches;
        }, 30);
    }

    /** @param array<string, array<string, mixed>> $matches */
    public function persistOddsSnapshots(array $matches): void
    {
        $this->storeMatchesInDatabase($matches);
    }

    private function storeMatchesInDatabase(array $matches): void
    {
        $matches = $this->mergeDuplicateMatches($matches);
        if ($matches === []) {
            return;
        }

        $now = Carbon::now();
        $matchRows = [];
        $marketRows = [];
        $matchKeys = [];
        $marketSnapshotKeys = [];

        foreach ($matches as $match) {
            if (! is_array($match) || empty($match['unique_key'])) {
                continue;
            }

            $matchKey = (string) $match['unique_key'];
            $matchKeys[] = $matchKey;
            $matchPayload = $match;
            unset($matchPayload['markets'], $matchPayload['home_team_logo'], $matchPayload['away_team_logo'], $matchPayload['tournament_logo']);

            $matchRows[] = [
                'unique_key' => $match['unique_key'],
                'betfair_match_id' => $match['betfair_match_id'] ?? null,
                'sports_api_pro_match_id' => $match['sportsApiPro_match_id'] ?? null,
                'team1' => $match['team1'] ?? '',
                'team2' => $match['team2'] ?? '',
                'tournament' => $match['tournament'] ?? null,
                'result_status' => $match['result_status'] ?? null,
                'minutes_status' => $match['minutes_status'] ?? null,
                'start_timestamp' => (int) ($match['timestamp'] ?? 0),
                'data_sync_complete' => (bool) ($match['data_sync_complete'] ?? false),
                'payload' => json_encode($matchPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (array_key_exists('markets', $match) && is_array($match['markets'])) {
                $marketSnapshotKeys[$matchKey] = true;
            }

            foreach (($match['markets'] ?? []) as $marketSelections) {
                if (! is_array($marketSelections)) {
                    continue;
                }

                foreach ($marketSelections as $sortPriority => $market) {
                    if (! is_array($market)) {
                        continue;
                    }

                    $selectionKey = hash('sha256', implode('|', [
                        $matchKey,
                        $market['marketId'] ?? '',
                        $market['marketType'] ?? '',
                        $market['runnerId'] ?? '',
                        $market['handicap'] ?? 0,
                        $sortPriority,
                    ]));

                    $marketRows[$selectionKey] = [
                        'selection_key' => $selectionKey,
                        'football_match_unique_key' => $matchKey,
                        'market_id' => $market['marketId'] ?? null,
                        'market_type' => $market['marketType'] ?? null,
                        'runner_id' => $market['runnerId'] ?? null,
                        'runner' => $market['runner'] ?? null,
                        'sort_priority' => is_numeric($sortPriority) ? (int) $sortPriority : null,
                        'handicap' => isset($market['handicap']) ? (float) $market['handicap'] : null,
                        'previous_back_odds' => (float) ($market['previousBackOdds'] ?? $market['backOdds'] ?? 0),
                        'current_back_odds' => (float) ($market['currentBackOdds'] ?? $market['backOdds'] ?? 0),
                        'back_size' => (float) ($market['backSize'] ?? 0),
                        'lay_odds' => (float) ($market['layOdds'] ?? 0),
                        'lay_size' => (float) ($market['laySize'] ?? 0),
                        'payload' => json_encode($market, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if ($matchRows === []) {
            return;
        }

        $matchKeys = array_values(array_unique($matchKeys));
        $marketSnapshotKeys = array_keys($marketSnapshotKeys);

        Cache::store('redis')
            ->lock('Football:database-write-lock', 120)
            ->get(function () use ($matchRows, $marketRows, $matchKeys, $marketSnapshotKeys): void {
                $cleaned = array_fill_keys(Redis::zrangebyscore('Football:matches:cleaned', time(), '+inf'), true);
                if ($cleaned !== []) {
                    $matchRows = array_filter($matchRows, static fn (array $row): bool => ! isset($cleaned[$row['unique_key']]));
                    $marketRows = array_filter($marketRows, static fn (array $row): bool => ! isset($cleaned[$row['football_match_unique_key']]));
                    $matchKeys = array_values(array_diff($matchKeys, array_keys($cleaned)));
                    $marketSnapshotKeys = array_values(array_diff($marketSnapshotKeys, array_keys($cleaned)));
                }
                $existingMatches = FootballMatch::query()
                    ->whereIn('unique_key', $matchKeys)
                    ->get([
                        'unique_key',
                        'betfair_match_id',
                        'sports_api_pro_match_id',
                        'team1',
                        'team2',
                        'tournament',
                        'result_status',
                        'minutes_status',
                        'start_timestamp',
                        'data_sync_complete',
                        'payload',
                    ])
                    ->keyBy('unique_key');

                $changedMatchRows = [];
                foreach ($matchRows as $row) {
                    $existing = $existingMatches->get($row['unique_key']);
                    if ($existing?->result_status === 'Finished') {
                        $marketSnapshotKeys = array_values(array_diff($marketSnapshotKeys, [$row['unique_key']]));
                        $marketRows = array_filter($marketRows, static fn (array $market): bool => $market['football_match_unique_key'] !== $row['unique_key']);

                        continue;
                    }
                    $storedPayload = $existing?->payload;
                    $incomingPayload = json_decode($row['payload'], true);
                    $incomingPayload = MatchBackup::merge($incomingPayload, $storedPayload ?? [], 'football');
                    $row['payload'] = json_encode($incomingPayload, JSON_THROW_ON_ERROR);
                    if (
                        $existing === null
                        || (string) $existing->betfair_match_id !== (string) $row['betfair_match_id']
                        || (string) $existing->sports_api_pro_match_id !== (string) $row['sports_api_pro_match_id']
                        || $existing->team1 !== $row['team1']
                        || $existing->team2 !== $row['team2']
                        || $existing->tournament !== $row['tournament']
                        || $existing->result_status !== $row['result_status']
                        || $existing->minutes_status !== $row['minutes_status']
                        || (int) $existing->start_timestamp !== $row['start_timestamp']
                        || (bool) $existing->data_sync_complete !== $row['data_sync_complete']
                        || $storedPayload != $incomingPayload
                    ) {
                        $changedMatchRows[] = $row;
                    }
                }

                $existingMarkets = collect();
                if ($marketSnapshotKeys !== []) {
                    $existingMarkets = FootballMarket::query()
                        ->whereIn('football_match_unique_key', $marketSnapshotKeys)
                        ->get(['selection_key', 'football_match_unique_key', 'payload'])
                        ->keyBy('selection_key');
                }

                $changedMarketRows = [];
                foreach ($marketRows as $selectionKey => $row) {
                    $existing = $existingMarkets->get($selectionKey);
                    $storedPayload = $existing?->payload;
                    $incomingPayload = json_decode($row['payload'], true);
                    if (
                        $existing === null
                        || $existing->football_match_unique_key !== $row['football_match_unique_key']
                        || $storedPayload != $incomingPayload
                    ) {
                        $changedMarketRows[] = $row;
                    }
                }

                $incomingSelectionKeys = array_fill_keys(array_keys($marketRows), true);
                $staleSelectionKeys = [];
                foreach ($existingMarkets as $selectionKey => $existing) {
                    if (! isset($incomingSelectionKeys[$selectionKey])) {
                        $staleSelectionKeys[] = $selectionKey;
                    }
                }

                if ($changedMatchRows === [] && $changedMarketRows === [] && $staleSelectionKeys === []) {
                    return;
                }

                if ($changedMatchRows !== []) {
                    usort($changedMatchRows, static fn (array $left, array $right): int => $left['unique_key'] <=> $right['unique_key']);
                    foreach (array_chunk($changedMatchRows, 50) as $matchChunk) {
                        FootballMatch::query()->upsert(
                            $matchChunk,
                            ['unique_key'],
                            [
                                'betfair_match_id',
                                'sports_api_pro_match_id',
                                'team1',
                                'team2',
                                'tournament',
                                'result_status',
                                'minutes_status',
                                'start_timestamp',
                                'data_sync_complete',
                                'payload',
                                'updated_at',
                            ]
                        );
                    }
                }

                if ($staleSelectionKeys !== []) {
                    sort($staleSelectionKeys);
                    foreach (array_chunk($staleSelectionKeys, 500) as $selectionChunk) {
                        FootballMarket::query()
                            ->whereIn('selection_key', $selectionChunk)
                            ->delete();
                    }
                }

                if ($changedMarketRows !== []) {
                    usort($changedMarketRows, static fn (array $left, array $right): int => $left['selection_key'] <=> $right['selection_key']);
                    foreach (array_chunk($changedMarketRows, 200) as $marketChunk) {
                        FootballMarket::query()->upsert(
                            $marketChunk,
                            ['selection_key'],
                            [
                                'football_match_unique_key',
                                'market_id',
                                'market_type',
                                'runner_id',
                                'runner',
                                'sort_priority',
                                'handicap',
                                'previous_back_odds',
                                'current_back_odds',
                                'back_size',
                                'lay_odds',
                                'lay_size',
                                'payload',
                                'updated_at',
                            ]
                        );
                    }
                }
            });
    }

    private function sportsApiMinuteStatus(int|string|null $statusCode, string $currentStatus): string
    {
        return match ((int) $statusCode) {
            6 => '1st half',
            7 => '2nd half',
            31 => 'Half Time',
            100 => 'Finished',
            default => in_array($currentStatus, ['Live', 'Prematch', 'Finished', 'Half Time', '1st half', '2nd half'], true)
                ? $currentStatus
                : 'Live',
        };
    }

    private function sportsApiResultStatus(int|string|null $statusCode, ?string $statusType, string $currentStatus): string
    {
        if ($currentStatus === 'Finished') {
            return 'Finished';
        }

        if ((int) $statusCode === 100 || $statusType === 'finished') {
            return 'Finished';
        }

        if (in_array((int) $statusCode, [6, 7, 31], true) || $statusType === 'inprogress') {
            return 'Live';
        }

        return in_array($currentStatus, ['Live', 'Prematch', 'Finished'], true)
            ? $currentStatus
            : 'Live';
    }

    private function alignMinuteStatusWithResult(?string $minuteStatus, string $resultStatus): string
    {
        if ($resultStatus === 'Finished') {
            return 'Finished';
        }

        if ($resultStatus === 'Prematch') {
            return 'Prematch';
        }

        return in_array($minuteStatus, ['Live', 'Half Time', '1st half', '2nd half'], true)
            ? $minuteStatus
            : 'Live';
    }

    public function getCacheStoreData()
    {
        $data = getInRedis($this->sports.':matches');
        foreach ($data as $key => $value) {
            dump($value);
        }
        dd('1');
    }

    private function matchDirection(float $previousOdds, float $currentOdds)
    {
        if ($currentOdds == $previousOdds) {
            return 'same';
        } elseif ($currentOdds < $previousOdds) {
            return 'down';
        } elseif ($currentOdds > $previousOdds) {
            return 'up';
        }
    }

    private function filterSupportedMarkets(array $markets)
    {
        return $markets;
        $storeOnlyMarkets = ['MATCH_ODDS', 'OVER_UNDER_05', 'OVER_UNDER_25', 'OVER_UNDER_35', 'OVER_UNDER_45', 'OVER_UNDER_55', 'OVER_UNDER_65', 'OVER_UNDER_75', 'OVER_UNDER_85', 'BOTH_TEAMS_TO_SCORE', 'CORRECT_SCORE', 'HALF_TIME', 'HALF_TIME_FULL_TIME', 'DRAW_NO_BET,HALF_TIME_SCORE', 'OVER_UNDER_15'];

        return array_values(array_filter($markets, fn ($market) => in_array($market['marketType'], $storeOnlyMarkets, true)));
    }

    private function generateUniqueKey(string $teamA, string $teamB, string $timestamp)
    {
        $teamA = $this->normalizeTeamName($teamA);
        $teamB = $this->normalizeTeamName($teamB);
        $generatedName = $teamA.'|'.$teamB.'|'.$timestamp;

        return $generatedName;
    }

    private function normalizeTeamName(string $name): string
    {
        $name = Str::ascii($name);
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9\s]/', ' ', $name);
        $replacements = [
            ' utd ' => ' united ',
            ' ath ' => ' athletic ',
            ' st ' => ' saint ',
        ];
        $name = ' '.$name.' ';
        foreach ($replacements as $from => $to) {
            $name = str_replace($from, $to, $name);
        }
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return str_replace(' ', '_', $name);
    }

    private function checkIfRecordExistsOnAnotherApi(
        string $uniqueKey,
        string $team1,
        string $team2,
        string|int $timestamp,
        ?array $existingRecords = null,
        int|string|null $sportsApiMatchId = null,
        int|string|null $betfairMatchId = null,
        ?string $homeShortName = null,
        ?string $awayShortName = null,
    ): string|int|null {
        $existingRecords ??= getInRedis($this->sports.':matches') ?? [];

        foreach ($existingRecords as $key => $record) {
            if (($sportsApiMatchId !== null && (string) ($record['sportsApiPro_match_id'] ?? '') === (string) $sportsApiMatchId)
                || ($betfairMatchId !== null && (string) ($record['betfair_match_id'] ?? '') === (string) $betfairMatchId)) {
                return $key;
            }
        }
        if (array_key_exists($uniqueKey, $existingRecords)
            && ($sportsApiMatchId === null || empty($existingRecords[$uniqueKey]['sportsApiPro_match_id']) || (string) $existingRecords[$uniqueKey]['sportsApiPro_match_id'] === (string) $sportsApiMatchId)
            && ($betfairMatchId === null || empty($existingRecords[$uniqueKey]['betfair_match_id']) || (string) $existingRecords[$uniqueKey]['betfair_match_id'] === (string) $betfairMatchId)) {
            return $uniqueKey;
        }

        $homeShortName = $homeShortName === null ? '' : $this->normalizeTeamName($homeShortName);
        $awayShortName = $awayShortName === null ? '' : $this->normalizeTeamName($awayShortName);
        $shortNameMatch = null;
        $ambiguousShortNameMatch = false;
        foreach ($existingRecords as $key => $record) {
            if ($sportsApiMatchId !== null && ! empty($record['sportsApiPro_match_id']) && (string) $record['sportsApiPro_match_id'] !== (string) $sportsApiMatchId) {
                continue;
            }
            if ($betfairMatchId !== null && ! empty($record['betfair_match_id']) && (string) $record['betfair_match_id'] !== (string) $betfairMatchId) {
                continue;
            }
            $recordTimestamp = (int) ($record['timestamp'] ?? 0);
            $currentTimestamp = (int) $timestamp;

            if ($recordTimestamp !== $currentTimestamp) {
                continue;
            }
            $team1Score = $this->teamSimilarity($record['team1'] ?? '', $team1);
            $team2Score = $this->teamSimilarity($record['team2'] ?? '', $team2);

            if ($team1Score >= 80 && $team2Score >= 80) {
                return $key;
            }

            if (! $ambiguousShortNameMatch && $homeShortName !== '' && $awayShortName !== ''
                && ! empty($record['betfair_match_id'])
                && $this->normalizeTeamName($record['team1'] ?? '') === $homeShortName
                && $this->normalizeTeamName($record['team2'] ?? '') === $awayShortName) {
                $ambiguousShortNameMatch = $shortNameMatch !== null;
                $shortNameMatch = $key;
            }
        }

        return $ambiguousShortNameMatch ? null : $shortNameMatch;
    }

    /** @param array<string, array<string, mixed>> $matches
     * @return array<string, array<string, mixed>>
     */
    public function mergeDuplicateMatches(array $matches): array
    {
        $matchKeys = [];
        foreach ($matches as $key => $match) {
            $id = (string) ($match['sportsApiPro_match_id'] ?? '');
            if (! preg_match('/^[1-9][0-9]*$/', $id)) {
                continue;
            }
            if (! isset($matchKeys[$id])) {
                $matchKeys[$id] = $key;

                continue;
            }
            $previousKey = $matchKeys[$id];
            $previous = $matches[$previousKey];
            $canonicalKey = ! empty($match['betfair_match_id']) && empty($previous['betfair_match_id']) ? $key : $previousKey;
            $canonical = $matches[$canonicalKey];
            $previousVersion = [($previous['result_status'] ?? '') === 'Finished', max([$previous['provider_lifecycle']['sequence'] ?? 0, ...array_values($previous['websocket_change_timestamps'] ?? [])]), $previous['timeline']['clock_updated_at'] ?? 0];
            $version = [($match['result_status'] ?? '') === 'Finished', max([$match['provider_lifecycle']['sequence'] ?? 0, ...array_values($match['websocket_change_timestamps'] ?? [])]), $match['timeline']['clock_updated_at'] ?? 0];
            $newer = $version > $previousVersion ? $match : $previous;
            $older = $version > $previousVersion ? $previous : $match;
            $merged = array_replace($older, $newer, array_intersect_key($canonical, array_flip(['unique_key', 'team1', 'team2', 'betfair_match_id'])));
            $merged['unique_key'] = $canonicalKey;
            if (isset($previous['markets']) || isset($match['markets'])) {
                $merged['markets'] = array_replace($previous['markets'] ?? [], $match['markets'] ?? [], $canonical['markets'] ?? []);
            }
            $merged['websocket_change_timestamps'] = $newer['websocket_change_timestamps'] ?? [];
            foreach ($older['websocket_change_timestamps'] ?? [] as $channel => $sequence) {
                $merged['websocket_change_timestamps'][$channel] = max($sequence, $merged['websocket_change_timestamps'][$channel] ?? 0);
            }
            $merged['data_sync_complete'] = ! empty($merged['sportsApiPro_match_id']) && ! empty($merged['betfair_match_id']);
            $this->storeTimeline($merged, frameType: 'restore', previous: $older, storedTimeline: []);
            $matches[$canonicalKey] = $merged;
            unset($matches[$canonicalKey === $key ? $previousKey : $key]);
            $matchKeys[$id] = $canonicalKey;
        }

        return $matches;
    }

    public function teamSimilarity(string $teamA, string $teamB): float
    {
        $teamA = $this->normalizeTeamName($teamA);
        $teamB = $this->normalizeTeamName($teamB);

        if ($teamA === $teamB) {
            return 100;
        }

        similar_text($teamA, $teamB, $percentage);

        return $percentage;
    }
}
