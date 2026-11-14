<?php

namespace App\Http\Controllers\Users\Football;

use App\Http\Controllers\Controller;
use App\Models\PredictionVote;
use App\Services\AlertService;
use App\Services\MatchBackup;
use App\Traits\FootballTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\AcceptHeader;

class FootballController extends Controller
{
    use FootballTrait;

    public function football()
    {
        $data = $this->getFootballMatches();
        $hasMore = $this->footballHasMore;
        $nextPage = $this->footballNextPage;

        return view('users.sports.football.football', compact('data', 'hasMore', 'nextPage'));
    }

    /** @param array{alerts: list<array<string, mixed>>, signals?: list<array<string, mixed>>}|null $pinnacleSnapshot */
    public function marketActivity(?array $pinnacleSnapshot = null, bool $currentPinnacleQuote = false): View|JsonResponse|Response|array
    {
        $capture = $pinnacleSnapshot !== null;
        $checkAuthAndSubscription = $capture ? null : checkAuthAndSubscription();
        if (! $capture && ! $checkAuthAndSubscription['display_odds']) {
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => __('Register and activate your 7-day free trial (first-time users), or purchase a paid subscription to access Market Activity and Pinnacle Drops.'),
                    'access_required' => true,
                    'data' => [],
                ], 403);
            }

            return view('users.sports.football.marketActivity', compact('checkAuthAndSubscription'));
        }

        if ($capture || request()->routeIs('football.pinnacle-odds')) {
            $pinnacleArchive = ! $capture && request()->boolean('archive');
            $pinnacleArchiveHasMore = false;
            $pinnacleArchiveCursor = null;
            $pinnacleArchiveStart = now('Asia/Tbilisi')->subHours(6)->startOfDay()->addHours(6);
            $pinnacleArchiveEnd = $pinnacleArchiveStart->copy()->addDay();
            $pinnacleArchiveStats = [];
            if ($capture) {
                $pinnaclePayload = $pinnacleSnapshot;
            } elseif ($pinnacleArchive) {
                $filters = request()->validate([
                    'sport' => ['sometimes', 'string', 'max:32'],
                    'mode' => ['sometimes', 'in:all,prematch,live'],
                    'view' => ['sometimes', 'in:simple,advanced'],
                    'market_filter' => ['sometimes', 'in:main,handicap,all'],
                    'time_filter' => ['sometimes', 'in:all,today,1h,3h,future'],
                    'before_time' => ['nullable', 'required_with:before_key', 'integer', 'min:0'],
                    'before_key' => ['nullable', 'required_with:before_time', 'regex:/^[a-f0-9]{64}$/'],
                ]);
                $archiveNow = now();
                $query = DB::table('pinnacle_drop_archives')
                    ->where('alerted_at', '>=', $pinnacleArchiveStart->timestamp)
                    ->where('alerted_at', '<', $pinnacleArchiveEnd->timestamp);
                if (($filters['mode'] ?? 'all') !== 'all') {
                    $query->where('is_live', $filters['mode'] === 'live');
                }
                if (! isset($filters['before_key'])) {
                    $sports = DB::table('pinnacle_drop_archives')->where('alerted_at', '>=', $pinnacleArchiveStart->timestamp)
                        ->where('alerted_at', '<', $pinnacleArchiveEnd->timestamp)->groupBy('sport')->orderBy('sport')
                        ->selectRaw('sport, COUNT(*) AS total, COUNT(betfair_higher) AS compared, SUM(CASE WHEN betfair_higher = 1 THEN 1 ELSE 0 END) AS higher')->get();
                    $pinnacleArchiveStats = ['sports' => $sports, 'total' => (int) $sports->sum('total'),
                        'compared' => (int) $sports->sum('compared'), 'higher' => (int) $sports->sum('higher')];
                }
                if (($filters['sport'] ?? 'all') !== 'all') {
                    $query->where('sport', $filters['sport']);
                }
                if (($filters['market_filter'] ?? 'all') === 'main') {
                    $query->where('market_group', '<', 3);
                } elseif (($filters['market_filter'] ?? 'all') === 'handicap') {
                    $query->where('market_group', 3);
                }
                $timeFilter = $filters['time_filter'] ?? 'all';
                if ($timeFilter === 'today') {
                    $today = $archiveNow->copy()->setTimezone(getCurrentUserTimezone())->startOfDay();
                    $query->where('start_timestamp', '>=', $today->timestamp)->where('start_timestamp', '<', $today->copy()->addDay()->timestamp);
                } elseif (in_array($timeFilter, ['1h', '3h', 'future'], true)) {
                    $query->where('is_live', false)->where('start_timestamp', '>=', $archiveNow->timestamp);
                    if ($timeFilter === 'future') {
                        $query->where('start_timestamp', '>=', $archiveNow->copy()->setTimezone(getCurrentUserTimezone())->addDay()->startOfDay()->timestamp);
                    } else {
                        $query->where('start_timestamp', '<=', $archiveNow->copy()->addHours($timeFilter === '1h' ? 1 : 3)->timestamp);
                    }
                }
                if (isset($filters['before_time'], $filters['before_key'])) {
                    $query->where(fn ($query) => $query->where('alerted_at', '<', $filters['before_time'])
                        ->orWhere(fn ($query) => $query->where('alerted_at', $filters['before_time'])->where('alert_key', '<', $filters['before_key'])));
                }
                $rows = $query->orderByDesc('alerted_at')->orderByDesc('alert_key')->limit(101)->get(['alerted_at', 'alert_key', 'payload']);
                $pinnacleArchiveHasMore = $rows->count() > 100;
                if ($pinnacleArchiveHasMore) {
                    $rows->pop();
                    $last = $rows->last();
                    $pinnacleArchiveCursor = ['time' => (int) $last->alerted_at, 'key' => $last->alert_key];
                }
                $pinnaclePayload = ['alerts' => [], 'signals' => []];
                foreach ($rows as $row) {
                    $drop = json_decode($row->payload, true);
                    if (! is_array($drop)) {
                        continue;
                    }
                    $pinnaclePayload['alerts'][] = $drop;
                    $pinnaclePayload['signals'][] = $drop;
                    if (is_array($drop['archive_parent'] ?? null)) {
                        $pinnaclePayload['signals'][] = $drop['archive_parent'];
                    }
                }
            } else {
                $pinnaclePayload = getInRedis('Pinnacle:drops');
            }
            $pinnacleAlerts = $pinnaclePayload ?? [];
            $pinnacleAlerts = $pinnacleAlerts['alerts'] ?? $pinnacleAlerts['data'] ?? $pinnacleAlerts;
            $pinnacleAlerts = is_array($pinnacleAlerts) ? array_values($pinnacleAlerts) : [];
            if (! $pinnacleArchive && ! $capture) {
                $cutoff = microtime(true) - 420;
                $pinnacleAlerts = array_values(array_filter($pinnacleAlerts, static fn (array $alert): bool => (float) ($alert['monitor_received_at'] ?? $alert['alerted'] ?? 0) > $cutoff));
            }
            $pinnacleFeeds = [];
            foreach (['live', 'prematch'] as $mode) {
                $feed = $pinnaclePayload['feeds'][$mode] ?? [];
                $pinnacleFeeds[$mode] = time() - (int) ($feed['last_frame_at'] ?? 0) <= 90
                    ? ($feed['status'] ?? 'disconnected')
                    : (($feed['status'] ?? null) === 'connecting' && time() - (int) ($pinnaclePayload['updated_at'] ?? 0) <= 90 ? 'connecting' : 'disconnected');
            }
            $pinnacleFeedConnected = $pinnacleFeeds['prematch'] === 'connected';
            $normalizeName = static function (string $name): string {
                $name = ' '.trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($name)))).' ';

                return trim(str_replace([' utd ', ' ath ', ' st '], [' united ', ' athletic ', ' saint '], $name));
            };
            $sameClubName = static function (string $left, string $right) use ($normalizeName): bool {
                $left = $normalizeName($left);
                $right = $normalizeName($right);
                $suffix = '/ (?:city|town|(?:18|19|20)\d{2})$/';

                return $left !== '' && $right !== '' && ($left === $right
                    || preg_replace($suffix, '', $left) === $right
                    || preg_replace($suffix, '', $right) === $left);
            };
            $sportsmeldKey = $pinnacleArchive || ($capture && ! $currentPinnacleQuote) ? '' : (string) config('services.sportsmeld.key');
            $sportKeys = ['soccer' => 'football', 'tennis' => 'tennis', 'basketball' => 'basketball', 'ice-hockey' => 'ice_hockey', 'american-football' => 'american_football', 'baseball' => 'baseball', 'cricket' => 'cricket'];
            $eventIds = [];
            foreach ($pinnacleAlerts as $alert) {
                $sport = $alert['sport'] ?? '';
                if (isset($sportKeys[$sport], $alert['id'])) {
                    $eventIds[$sport][] = (string) (($alert['parent_id'] ?? null) ?: $alert['id']);
                }
            }
            $eventMappings = $resolvedEvents = $marketSnapshots = $fixtureWindows = [];
            if (! $pinnacleArchive && $eventIds !== []) {
                $eventMappings = DB::table('pinnacle_betfair_events')
                    ->where(function ($query) use ($eventIds): void {
                        foreach ($eventIds as $sport => $ids) {
                            $query->orWhere(fn ($query) => $query->where('sport', $sport)->whereIn('pinnacle_event_id', array_unique($ids)));
                        }
                    })->get()->groupBy('sport')->map->pluck('betfair_event_id', 'pinnacle_event_id')->all();
            }
            $matchingController = $pinnacleArchive ? null : app(FootballSyncController::class);
            $pinnacleEvents = [];
            foreach ($pinnaclePayload['signals'] ?? $pinnacleAlerts as $event) {
                if (! is_array($event) || ! isset($event['id']) || ! is_string($event['home'] ?? null) || ! is_string($event['away'] ?? null)
                    || trim($event['home']) === '' || trim($event['away']) === ''
                    || (in_array(strtolower(trim($event['home'])), ['yes', 'no'], true) && in_array(strtolower(trim($event['away'])), ['yes', 'no'], true))) {
                    continue;
                }
                $eventSport = (string) ($event['sport'] ?? '');
                $existingEvent = $pinnacleEvents[$eventSport][$event['id']] ?? null;
                if ($existingEvent === null || (int) ($event['alerted'] ?? 0) > (int) ($existingEvent['alerted'] ?? 0)) {
                    $pinnacleEvents[$eventSport][$event['id']] = $event;
                }
            }
            $remainingLookups = 1;
            $legacyFootballMatches = ! $pinnacleArchive && (! $capture || $currentPinnacleQuote) && $sportsmeldKey === '' && isset($eventIds['soccer']) ? (getInRedis('Football:matches', readOnly: true) ?? []) : [];
            $footballIndex = ($sportsmeldKey !== '' || $currentPinnacleQuote) && isset($eventIds['soccer']) ? getInRedis('Football:matches', fixtureIndex: true, marketActivityIndex: true) : null;

            foreach ($pinnacleAlerts as &$alert) {
                $alert['bf_back'] = $pinnacleArchive ? ($alert['bf_at_drop'] ?? null) : null;
                $alert['bf_lay'] = $alert['bf_back_size'] = $alert['bf_lay_size'] = null;
                $alert['full_market'] = [];
                $tennisGames = ($alert['sport'] ?? null) === 'tennis' && preg_match('/\(games\)$/i', (string) ($alert['home'] ?? '')) === 1;
                $sport = $alert['sport'] ?? '';
                $selectionHome = strtolower(trim((string) ($alert['home'] ?? '')));
                $selectionAway = strtolower(trim((string) ($alert['away'] ?? '')));
                $selectionNames = in_array($selectionHome, ['yes', 'no'], true) && in_array($selectionAway, ['yes', 'no'], true);
                $parent = ($pinnacleArchive || $capture ? ($alert['archive_parent'] ?? null) : null)
                    ?? $pinnacleEvents[$sport][$alert['parent_id'] ?? ''] ?? ($selectionNames ? ($pinnacleEvents[$sport][$alert['id'] ?? ''] ?? null) : null);
                $hasFixtureNames = ! $selectionNames || $parent !== null;
                if ($parent !== null) {
                    $alert['home'] = $parent['home'];
                    $alert['away'] = $parent['away'];
                    $alert['country'] = $parent['country'] ?? $alert['country'] ?? null;
                    $alert['league'] = $parent['league'] ?? $alert['league'] ?? null;
                }
                $eventId = (string) (($alert['parent_id'] ?? null) ?: ($alert['id'] ?? ''));
                $starts = $parent['starts'] ?? $parent['start'] ?? $alert['starts'] ?? $alert['start'] ?? null;
                unset($parent);
                $startTimestamp = is_numeric($starts) ? (int) $starts : (is_string($starts) ? (int) strtotime($starts) : 0);
                if ($startTimestamp > 100_000_000_000) {
                    $startTimestamp = intdiv($startTimestamp, 1000);
                }
                $alert['start_timestamp'] = $startTimestamp > 0 ? $startTimestamp : null;
                $status = ($alert['is_live'] ?? false) ? 'Live' : 'Prematch';
                $matchedFixture = null;
                if (! $pinnacleArchive && isset($sportKeys[$sport]) && ($sportsmeldKey !== '' || $capture || $sport === 'soccer')
                    && ($hasFixtureNames || isset($eventMappings[$sport][$eventId]))) {
                    $sportTable = $sportKeys[$sport];
                    $resolutionKey = $sport.'|'.$eventId.'|'.$status;
                    if (! array_key_exists($resolutionKey, $resolvedEvents)) {
                        $mappedId = $eventMappings[$sport][$eventId] ?? null;
                        $fixtures = $legacyFootballMatches;
                        if ($mappedId !== null || (($sport === 'soccer' || $sportsmeldKey !== '' || $capture) && $startTimestamp > 0)) {
                            $fixtureWindow = $sportTable.'|'.$status.'|'.($mappedId !== null ? 'id:'.$mappedId : 'start:'.$startTimestamp);
                            $storedFixtures = $fixtureWindows[$fixtureWindow] ??= DB::table($sportTable.'_matches')
                                ->when($mappedId !== null,
                                    fn ($query) => $query->where('betfair_match_id', $mappedId),
                                    fn ($query) => $query->whereBetween('start_timestamp', [$startTimestamp - 3600, $startTimestamp + 3600]))
                                ->where('data_sync_complete', true)
                                ->where('result_status', $status)
                                ->whereNotNull('betfair_match_id')
                                ->when($sport !== 'soccer' || $mappedId !== null, fn ($query) => $query->limit(26))
                                ->get(['unique_key', 'sports_api_pro_match_id', 'betfair_match_id', 'team1', 'team2', 'tournament', 'result_status', 'start_timestamp as timestamp', 'data_sync_complete', 'payload'])
                                ->map(static fn (object $fixture): array => array_replace(array_intersect_key(json_decode($fixture->payload, true) ?? [], ['internal_backup' => true, 'provider_lifecycle' => true]), array_diff_key((array) $fixture, ['payload' => true]), ['data_sync_complete' => true]))
                                ->all();
                            $fixtures = $storedFixtures;
                            if ($mappedId === null && $legacyFootballMatches !== []) {
                                $fixtures = array_values(array_replace(
                                    array_column($storedFixtures, null, 'unique_key'),
                                    array_column($legacyFootballMatches, null, 'unique_key'),
                                ));
                            }
                        }
                        $candidates = [];
                        foreach ($fixtures as $fixture) {
                            $sameTime = $startTimestamp === 0 || abs((int) ($fixture['timestamp'] ?? 0) - $startTimestamp) <= 3600;
                            if (MatchBackup::isHidden($fixture, str_replace('_', '', $sportTable))) {
                                if ($sport === 'soccer' && (($mappedId !== null && (string) ($fixture['betfair_match_id'] ?? '') === $mappedId)
                                    || ($sameTime && $normalizeName((string) $fixture['team1']) === $normalizeName((string) $alert['home'])
                                        && $normalizeName((string) $fixture['team2']) === $normalizeName((string) $alert['away'])))) {
                                    $alert['internal_hidden'] = true;
                                }

                                continue;
                            }
                            if (($fixture['result_status'] ?? null) !== $status || ($fixture['data_sync_complete'] ?? false) !== true) {
                                continue;
                            }
                            if ($mappedId !== null ? (string) ($fixture['betfair_match_id'] ?? '') === $mappedId : $sameTime) {
                                $candidates[] = $fixture;
                            }
                        }
                        $fallback = $mappedId === null;
                        $localCandidates = $candidates;
                        if ($mappedId === null && $sportsmeldKey !== '') {
                            $candidates = array_values(array_filter($candidates, static fn (array $fixture): bool => ! empty($fixture['betfair_match_id'])));
                            $league = $normalizeName((string) ($alert['league'] ?? $alert['league_name'] ?? ''));
                            $tooManyCandidates = $sport !== 'soccer' && count($fixtures) === 26;
                            $leagueMatches = array_values(array_filter($candidates, static fn (array $fixture): bool => $league !== '' && $normalizeName((string) ($fixture['tournament'] ?? '')) === $league));
                            $candidates = $leagueMatches ?: $candidates;
                            if (! $tooManyCandidates && empty($alert['internal_hidden']) && $startTimestamp > 0 && $remainingLookups > 0 && $candidates !== [] && count($candidates) <= 25
                                && Cache::add('sportsmeld:'.$resolutionKey, true, 60)) {
                                $remainingLookups--;
                                try {
                                    $matchedNames = ['team1' => [], 'team2' => []];
                                    $fallback = false;
                                    foreach (['home' => 'team1', 'away' => 'team2'] as $side => $field) {
                                        $reference = $sport === 'tennis' ? preg_replace('/\s+\((?:games|sets)\)$/i', '', $alert[$side]) : $alert[$side];
                                        $response = Http::withToken($sportsmeldKey)->acceptJson()->connectTimeout(1)->timeout(2)
                                            ->post('https://sportsmeld.arbitragex.pro/api/match', [
                                                'reference' => $reference,
                                                'names' => array_values(array_unique(array_column($candidates, $field))),
                                                'sport' => $sport,
                                            ]);
                                        $details = $response->ok() ? $response->json('details') : null;
                                        if (! is_array($details)) {
                                            $fallback = true;
                                            break;
                                        }
                                        $matchedNames[$field] = array_column(array_filter($details, static fn (mixed $detail): bool => is_array($detail) && ($detail['match'] ?? false) === true && ($detail['ambiguous'] ?? true) === false), 'name');
                                    }
                                    $candidates = array_values(array_filter($candidates, static fn (array $fixture): bool => in_array($fixture['team1'], $matchedNames['team1'], true)
                                        && in_array($fixture['team2'], $matchedNames['team2'], true)));
                                    $fallback = $fallback || count($candidates) !== 1;
                                    if (! $fallback) {
                                        DB::table('pinnacle_betfair_events')->insertOrIgnore([
                                            'sport' => $sport,
                                            'pinnacle_event_id' => $eventId,
                                            'betfair_event_id' => (string) $candidates[0]['betfair_match_id'],
                                        ]);
                                    }
                                } catch (ConnectionException) {
                                    $fallback = true;
                                }
                            }
                        }
                        if ($fallback) {
                            $candidates = [];
                            if ($sport === 'soccer' || ($sportsmeldKey === '' && count($fixtures) < 26)) {
                                $exact = $sport === 'soccer' ? array_values(array_filter($localCandidates, static fn (array $fixture): bool => $sameClubName((string) $fixture['team1'], (string) $alert['home'])
                                    && $sameClubName((string) $fixture['team2'], (string) $alert['away']))) : [];
                                $candidates = $exact !== [] ? $exact : array_values(array_filter($localCandidates, static fn (array $fixture): bool => $matchingController->teamSimilarity((string) $fixture['team1'], (string) $alert['home']) >= 80
                                    && $matchingController->teamSimilarity((string) $fixture['team2'], (string) $alert['away']) >= 80));
                            }
                        }
                        $resolvedEvents[$resolutionKey] = [
                            'hidden' => ! empty($alert['internal_hidden']),
                            'fixture' => count($candidates) === 1 ? $candidates[0] : null,
                        ];
                    }
                    if ($resolvedEvents[$resolutionKey]['hidden']) {
                        $alert['internal_hidden'] = true;

                        continue;
                    }
                    $matchedFixture = $resolvedEvents[$resolutionKey]['fixture'];
                    if ($matchedFixture !== null && $sport === 'soccer' && $footballIndex !== null && ! empty($matchedFixture['sports_api_pro_match_id']) && empty($resolvedEvents[$resolutionKey]['snapshot_checked'])) {
                        $rows = getInRedis('Football:matches', readOnly: true, matchIds: [$matchedFixture['sports_api_pro_match_id']], snapshot: $footballIndex);
                        $currentFixture = $rows ? reset($rows) : null;
                        if ($currentFixture !== null && (string) ($currentFixture['betfair_match_id'] ?? '') === (string) $matchedFixture['betfair_match_id']
                            && ($currentFixture['result_status'] ?? null) === $status) {
                            $matchedFixture = array_replace($currentFixture, ['unique_key' => $matchedFixture['unique_key']]);
                        }
                        $resolvedEvents[$resolutionKey]['fixture'] = $matchedFixture;
                        $resolvedEvents[$resolutionKey]['snapshot_checked'] = true;
                    }
                }
                if (! $hasFixtureNames) {
                    $alert['home'] = $matchedFixture['team1'] ?? null;
                    $alert['away'] = $matchedFixture['team2'] ?? null;
                }
                $period = (int) ($alert['period'] ?? 0);
                $section = str_replace('_', ' ', strtolower(trim((string) ($alert['sect'] ?? ''))));
                $outcome = strtolower(trim((string) ($alert['outcome'] ?? '')));
                if ($selectionNames && in_array($section, ['btts', 'both teams to score'], true) && in_array($outcome, ['home', 'away', '1', '2'], true)) {
                    $alert['outcome'] = $outcome = in_array($outcome, ['home', '1'], true) ? $selectionHome : $selectionAway;
                }
                $line = $alert['point'] ?? $alert['points'] ?? $alert['hdp'] ?? null;
                if (! is_numeric($line) && preg_match('/(?:over|under|total|o\/u)\s*([0-9]+(?:\.[0-9]+)?)/i', $section.' '.$outcome, $lineMatch)) {
                    $line = $lineMatch[1];
                }
                $outcome = match ($normalizeName($outcome)) {
                    $normalizeName((string) ($alert['home'] ?? '')) => 'home',
                    $normalizeName((string) ($alert['away'] ?? '')) => 'away',
                    default => $outcome,
                };
                $marketType = match (true) {
                    $period === 0 && in_array($section, ['moneyline', 'money line', '1x2', 'match odds'], true) => 'MATCH_ODDS',
                    $period === 1 && in_array($section, ['moneyline', 'money line', '1x2'], true) => 'HALF_TIME',
                    $period === 0 && preg_match('/^(total|totals|o\/u)/', $section) === 1 && is_numeric($line) && fmod((float) $line, 1.0) === 0.5 => 'OVER_UNDER_'.(int) round((float) $line * 10),
                    $period === 0 && in_array($section, ['both teams to score', 'btts'], true) => 'BOTH_TEAMS_TO_SCORE',
                    $period === 0 && in_array($section, ['draw no bet', 'dnb'], true) => 'DRAW_NO_BET',
                    ($period === 0 || ($sport === 'soccer' && $period === 1)) && in_array($section, ['spread', 'spreads', 'asian handicap'], true) && is_numeric($line) => 'ASIAN_HANDICAP',
                    default => null,
                };
                if ($sport !== 'soccer') {
                    $marketType = match (true) {
                        $tennisGames && $marketType === 'ASIAN_HANDICAP' => 'HANDICAP',
                        ! $tennisGames && $marketType === 'MATCH_ODDS' => 'MATCH_ODDS',
                        default => null,
                    };
                }
                $runners = [];
                $matchedHome = (string) ($alert['home'] ?? '');
                $matchedAway = (string) ($alert['away'] ?? '');
                if ($matchedFixture !== null) {
                    $matchedHome = $alert['bf_home'] = (string) $matchedFixture['team1'];
                    $matchedAway = $alert['bf_away'] = (string) $matchedFixture['team2'];
                    $markets = $matchedFixture['markets'] ?? null;
                    $isHandicap = in_array($marketType, ['ASIAN_HANDICAP', 'HANDICAP'], true);
                    $homeHandicap = isset($alert['point']) && in_array($outcome, ['away', '2'], true) ? -(float) $line : (float) $line;
                    $homeName = $isHandicap ? $normalizeName($matchedHome) : '';
                    $awayName = $isHandicap ? $normalizeName($matchedAway) : '';
                    foreach ([false, true] as $useDatabase) {
                        if ($marketType === null) {
                            break;
                        }
                        if ($useDatabase) {
                            if ($runners !== [] || empty($matchedFixture['unique_key'])
                                || ($markets !== null && ($marketType !== 'ASIAN_HANDICAP' || $status !== 'Prematch' || $sportsmeldKey === ''))) {
                                break;
                            }
                            $marketKey = $resolutionKey.'|'.$marketType;
                            $marketSnapshots[$marketKey] ??= DB::table($sportTable.'_markets')
                                ->where($sportTable.'_match_unique_key', $matchedFixture['unique_key'])
                                ->where('market_type', $marketType)
                                ->get(['market_id', 'payload'])->groupBy('market_id')
                                ->map(static fn ($rows): array => $rows->map(static fn (object $row): array => json_decode($row->payload, true) ?? [])->all())
                                ->all();
                            $markets = $marketSnapshots[$marketKey];
                        }
                        foreach ($markets ?? [] as $marketRunners) {
                            $first = $marketRunners[array_key_first($marketRunners)] ?? [];
                            if (($first['marketType'] ?? null) !== $marketType) {
                                continue;
                            }
                            $marketLabel = (string) ($first['name'] ?? '');
                            if ($marketType === 'HANDICAP' && ! preg_match('/\bgames?\b/i', $marketLabel)) {
                                continue;
                            }
                            if ($marketType === 'ASIAN_HANDICAP') {
                                $firstHalf = preg_match('/\b(?:first|1st)[ -]+half\b|\bhalf[ -]*time\b/i', $marketLabel) === 1;
                                if ($firstHalf !== ($period === 1) || preg_match('/\b(?:second|2nd)[ -]+half\b/i', $marketLabel)) {
                                    continue;
                                }
                            }
                            foreach ($marketRunners as $runner) {
                                if ($isHandicap) {
                                    $expected = match ($normalizeName((string) ($runner['runner'] ?? ''))) {
                                        $homeName => $homeHandicap,
                                        $awayName => -$homeHandicap,
                                        default => null,
                                    };
                                    if ($expected === null || ! is_numeric($runner['handicap'] ?? null) || (float) $runner['handicap'] !== $expected) {
                                        continue;
                                    }
                                }
                                $runners[] = $runner;
                            }
                        }
                    }
                }
                $matchesOutcome = static function (array $runner, string $selection) use ($matchedHome, $matchedAway, $normalizeName, $matchingController): bool {
                    $name = $normalizeName((string) ($runner['runner'] ?? ''));

                    return match (strtolower($selection)) {
                        'home', '1' => $matchingController->teamSimilarity((string) ($runner['runner'] ?? ''), $matchedHome) >= 80,
                        'away', '2' => $matchingController->teamSimilarity((string) ($runner['runner'] ?? ''), $matchedAway) >= 80,
                        'draw', 'x' => in_array($name, ['draw', 'the draw'], true),
                        'over' => $name === 'over' || str_starts_with($name, 'over '),
                        'under' => $name === 'under' || str_starts_with($name, 'under '),
                        'yes', 'no' => $name === $selection,
                        default => $name === $normalizeName($selection),
                    };
                };
                $matchingRunners = array_values(array_filter($runners, static fn (array $runner): bool => $matchesOutcome($runner, $outcome) || (str_starts_with($outcome, 'over ') && $matchesOutcome($runner, 'over')) || (str_starts_with($outcome, 'under ') && $matchesOutcome($runner, 'under'))));
                $selectedRunner = count($matchingRunners) === 1 ? $matchingRunners[0] : null;
                if ($capture && ! $currentPinnacleQuote) {
                    $at = (int) ($alert['alerted'] ?? 0);
                    $quoteKey = 'quote:'.json_encode([$sport, $eventId, $status, $period, $marketType, $line, $outcome, $at]);
                    if (array_key_exists($quoteKey, $marketSnapshots)) {
                        $alert = array_replace($alert, $marketSnapshots[$quoteKey]);

                        continue;
                    }
                    $alert['bf_at_drop'] = $alert['bf_quote_at'] = null;
                    if ($selectedRunner !== null) {
                        $quoteAt = (float) ($selectedRunner['odds_history_processed_at'] ?? 0);
                        if ($quoteAt > 0 && $quoteAt <= $at && ! in_array($selectedRunner['market_status'] ?? 'OPEN', ['SUSPENDED', 'CLOSED'], true)) {
                            $quote = (float) ($selectedRunner['currentBackOdds'] ?? $selectedRunner['backOdds'] ?? 0);
                            if (is_finite($quote) && $quote > 1) {
                                $alert['bf_at_drop'] = $quote;
                                $alert['bf_quote_at'] = $quoteAt;
                            }
                        } elseif ($quoteAt > $at && empty($selectedRunner['odds_history_incomplete'])) {
                            foreach (oddsMovementHistory($selectedRunner) as $point) {
                                if (($point['timestamp'] ?? 0) > 0 && $point['timestamp'] <= $at && $point['timestamp'] >= ($alert['bf_quote_at'] ?? 0)
                                    && is_numeric($point['odds'] ?? null) && is_finite((float) $point['odds']) && (float) $point['odds'] > 1) {
                                    $alert['bf_at_drop'] = (float) $point['odds'];
                                    $alert['bf_quote_at'] = $point['timestamp'];
                                }
                            }
                        }
                    }
                    $marketSnapshots[$quoteKey] = ['bf_at_drop' => $alert['bf_at_drop'], 'bf_quote_at' => $alert['bf_quote_at']];

                    continue;
                }
                $alert['pinnacle_selection'] = match ($outcome) {
                    'home', '1' => $alert['home'],
                    'away', '2' => $alert['away'],
                    default => $alert['outcome'] ?? null,
                };
                if ($selectedRunner !== null && ! in_array($selectedRunner['market_status'] ?? 'OPEN', ['SUSPENDED', 'CLOSED'], true)) {
                    $alert['bf_selection'] = $selectedRunner['runner'] ?? null;
                    $alert['bf_back'] = (float) ($selectedRunner['currentBackOdds'] ?? $selectedRunner['backOdds'] ?? 0) > 1 ? (float) ($selectedRunner['currentBackOdds'] ?? $selectedRunner['backOdds']) : null;
                    $alert['bf_lay'] = (float) ($selectedRunner['layOdds'] ?? 0) > 1 ? (float) $selectedRunner['layOdds'] : null;
                    $alert['bf_back_size'] = $selectedRunner['backSize'] ?? null;
                    $alert['bf_lay_size'] = $selectedRunner['laySize'] ?? null;
                }
                if ($currentPinnacleQuote) {
                    continue;
                }
                $pinnaclePrices = match (true) {
                    in_array($section, ['moneyline', 'money line', '1x2', 'match odds', 'draw no bet', 'dnb', 'spread', 'spreads', 'asian handicap'], true) => ['home' => $alert['price_x'] ?? null, 'away' => $alert['price_y'] ?? null, 'draw' => $alert['price_z'] ?? null],
                    preg_match('/^(total|totals|o\/u|teamtotal)/', $section) === 1 => ['over' => $alert['price_x'] ?? null, 'under' => $alert['price_y'] ?? null],
                    in_array($section, ['both teams to score', 'btts'], true) => ['yes' => $alert['price_x'] ?? null, 'no' => $alert['price_y'] ?? null],
                    default => [],
                };
                $pinnaclePrices = array_filter($pinnaclePrices, static fn (mixed $price, mixed $side): bool => in_array($side, ['home', 'away', 'draw', 'over', 'under', 'yes', 'no'], true) && is_numeric($price) && (float) $price > 1, ARRAY_FILTER_USE_BOTH);
                if ($pinnaclePrices !== []) {
                    $impliedTotal = array_sum(array_map(static fn (mixed $price): float => 1 / (float) $price, $pinnaclePrices));
                    foreach ($pinnaclePrices as $side => $pinnaclePrice) {
                        $sideRunners = array_values(array_filter($runners, static fn (array $runner): bool => $matchesOutcome($runner, $side)));
                        $runner = count($sideRunners) === 1 ? $sideRunners[0] : null;
                        $alert['full_market'][] = ['outcome' => ucfirst($side), 'to_price' => (float) $pinnaclePrice, 'nvp' => strtolower($side) === $outcome ? ($alert['nvp'] ?? null) : (count($pinnaclePrices) > 1 && (float) ($alert['nvp'] ?? 0) > 0 ? (float) $pinnaclePrice * $impliedTotal : null), 'bf_back' => in_array($runner['market_status'] ?? 'OPEN', ['SUSPENDED', 'CLOSED'], true) ? null : ($runner['currentBackOdds'] ?? $runner['backOdds'] ?? null)];
                    }
                } else {
                    $alert['full_market'][] = ['outcome' => $alert['outcome'] ?? '', 'to_price' => $alert['to_price'] ?? null, 'nvp' => $alert['nvp'] ?? null, 'bf_back' => $alert['bf_back']];
                }
            }
            unset($alert);
            if ($capture) {
                return array_values(array_filter($pinnacleAlerts, static fn (array $alert): bool => empty($alert['internal_hidden'])));
            }

            foreach ($pinnacleAlerts as &$alert) {
                [$country, $league, $alert['league_filter_key']] = AlertService::pinnacleLeague($alert);
                $alert['country'] = $country;
                $alert['league'] = $league;
            }
            unset($alert);

            if (request()->expectsJson()) {
                return response()->json([
                    'data' => $pinnacleAlerts,
                    'connected' => $pinnacleFeedConnected,
                    'feeds' => $pinnacleFeeds,
                    ...($pinnacleArchive ? ['archive' => true, 'has_more' => $pinnacleArchiveHasMore, 'next_cursor' => $pinnacleArchiveCursor, 'summary' => $pinnacleArchiveStats] : []),
                ]);
            }

            $pinnacleLeagueOptions = app(AlertService::class)->pinnacleMonitorLeagueOptions();

            return view('users.sports.football.marketActivity', compact('pinnacleLeagueOptions', 'pinnacleAlerts', 'pinnacleFeedConnected', 'pinnacleFeeds', 'pinnacleArchive', 'pinnacleArchiveHasMore', 'pinnacleArchiveCursor', 'pinnacleArchiveStart', 'pinnacleArchiveEnd', 'pinnacleArchiveStats'));
        }

        $marketActivites = $this->getMarketActivites();
        $hasMore = $this->marketActivityHasMore;
        $nextPage = $this->marketActivityNextPage;
        $marketRowsOnly = request()->expectsJson();
        $compactMarketRows = $marketRowsOnly && request()->boolean('_market_rows');
        $acceptsGzip = function_exists('gzencode') && AcceptHeader::fromString(request()->header('Accept-Encoding', ''))->get('gzip')?->getQuality() > 0;
        $marketRowVersions = [];
        if (! $marketRowsOnly || $compactMarketRows) {
            $locale = app()->getLocale();
            foreach ($marketActivites as $event) {
                $marketRowVersions[$event['id']] = hash('xxh3', serialize([$locale, $event]));
            }
        }

        if ($marketRowsOnly) {
            $marketRowOrder = null;
            if ($compactMarketRows) {
                $marketRowOrder = array_column($marketActivites, 'id');
                $known = request()->query('_market_versions', '');
                $known = is_string($known) && strlen($known) <= 4096 ? json_decode($known, true) : null;
                if (count($marketRowVersions) !== count($marketRowOrder)) {
                    $marketRowOrder = null;
                } elseif (is_array($known) && count($known) <= 100) {
                    $marketActivites = array_values(array_filter($marketActivites, static fn (array $event): bool => ($known[$event['id']] ?? null) !== $marketRowVersions[$event['id']]));
                }
            }
            $response = response()->json([
                'data' => $marketActivites,
                'html' => view('users.sports.football.marketActivity', compact('marketActivites', 'hasMore', 'nextPage', 'checkAuthAndSubscription', 'marketRowsOnly', 'compactMarketRows', 'marketRowOrder'))->fragment('footballMarketRows'),
                'has_more' => $hasMore,
                'next_page' => $nextPage,
            ] + ($compactMarketRows ? ['order' => $marketRowOrder, 'versions' => (object) $marketRowVersions, 'server_time' => time()] : []));
            if (! $compactMarketRows) {
                return $response;
            }
            $response->headers->set('Cache-Control', 'private, no-store');
        } else {
            $response = response()->view('users.sports.football.marketActivity', compact('marketActivites', 'hasMore', 'nextPage', 'checkAuthAndSubscription', 'marketRowVersions'));
        }

        $response->setVary('Accept-Encoding', false);
        if ($acceptsGzip && strlen($response->getContent()) >= 1024) {
            $response->setContent(gzencode($response->getContent(), 1));
            $response->headers->set('Content-Encoding', 'gzip');
        }

        return $response;
    }

    public function matchDetail(string $matchId)
    {
        $match = $this->getFootballMatchDetail($matchId);

        abort_if($match === null, 404);

        return view('users.sports.football.matchDetail', compact('match'));
    }

    public function statistics(string $matchId): JsonResponse
    {
        abort_unless(checkAuthAndSubscription()['display_odds'], 403, __('An active 7-day free trial or paid subscription is required to view statistics.'));
        $match = array_find(getInRedis('Football:matches', readOnly: true, matchIds: [$matchId]) ?? [], static fn (array $match): bool => (string) ($match['sportsApiPro_match_id'] ?? '') === $matchId && ! MatchBackup::isHidden($match, 'football'));
        abort_if($match === null, 404);
        $event = array_replace($match, [
            'status' => $match['result_status'],
            'score' => $this->getScore($match),
        ]);

        return response()->json([
            'html' => view('users.sports.football.partials.statisticsContent', compact('event'))->render(),
        ]);
    }

    public function filterFootball(): JsonResponse
    {
        $prediction = request()->input('prediction');
        if (is_array($prediction)) {
            $customerId = request()->user('customers')?->getAuthIdentifier();
            abort_if($customerId === null, 401, __('Please log in to submit predictions.'));
            $matchId = $prediction['match_id'] ?? null;
            $poll = $prediction['poll'] ?? null;
            $value = $prediction['value'] ?? null;
            $allowedValues = ['1x2' => ['1', 'X', '2'], 'uo' => ['UNDER', 'OVER']];
            abort_unless(is_scalar($matchId) && ctype_digit((string) $matchId) && (int) $matchId > 0
                && is_string($poll) && isset($allowedValues[$poll]) && in_array($value, $allowedValues[$poll], true), 422);
            $matchId = (string) $matchId;
            $sessionKey = "football_predictions.{$customerId}.{$matchId}.{$poll}";
            $ownerQuery = PredictionVote::query()->where('customer_id', $customerId)->where('sport', 'football')
                ->where('match_id', $matchId)->where('poll', $poll);
            $storedValue = $ownerQuery->value('value');
            if ($storedValue === null) {
                $match = array_find(getInRedis('Football:matches', readOnly: true, matchIds: [$matchId]) ?? [], static fn (array $match): bool => (string) ($match['sportsApiPro_match_id'] ?? '') === $matchId
                    && ! MatchBackup::isHidden($match, 'football') && ($match['result_status'] ?? '') === 'Prematch');
                abort_unless($match !== null, 422, __('Predictions are only available before the match starts.'));
                unset($match);
                $now = now();
                PredictionVote::query()->insertOrIgnore([
                    'vote_key' => hash('sha256', implode('|', ['user', $customerId, 'football', $matchId, $poll])),
                    'customer_id' => $customerId, 'sport' => 'football', 'match_id' => $matchId,
                    'poll' => $poll, 'value' => $value, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $storedValue = $ownerQuery->value('value');
                abort_if($storedValue === null, 503, __('Please try again.'));
            }
            request()->session()->put($sessionKey, $storedValue);
            $counts = PredictionVote::query()->select('value')->selectRaw('COUNT(*) as total')
                ->where('sport', 'football')->where('match_id', $matchId)->where('poll', $poll)->groupBy('value')
                ->pluck('total', 'value')->map(static fn (mixed $count): int => (int) $count)->all();

            return response()->json(['prediction' => ['match_id' => $matchId, 'poll' => $poll, 'value' => $storedValue, 'counts' => $counts]]);
        }

        return $this->websocketFootball(request()->boolean('append'));
    }

    public function websocketFootball(bool $append = false): JsonResponse
    {
        $data = $this->getFootballMatches();

        $response = response()->json([
            'html' => view('users.sports.football.footballDetails', [
                'data' => $data,
                'append' => $append,
            ])->render(),
            'has_more' => $this->footballHasMore,
            'next_page' => $this->footballNextPage,
        ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response->setVary('Accept-Encoding', false);
        if (function_exists('gzencode') && AcceptHeader::fromString(request()->header('Accept-Encoding', ''))->get('gzip')?->getQuality() > 0) {
            $response->setContent(gzencode($response->getContent(), 1));
            $response->headers->set('Content-Encoding', 'gzip');
        }

        return $response;
    }

    public function tempDataMerge()
    {
        $data = $this->getMarketActivites();
        dd($data);
        dd($this->getFootballMatches());
    }
}
