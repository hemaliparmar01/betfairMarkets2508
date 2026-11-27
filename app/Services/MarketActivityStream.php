<?php

namespace App\Services;

use App\Events\MarketActivityUpdated;
use App\Jobs\PublishMarketActivity;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

class MarketActivityStream
{
    public const SPORTS = ['Football' => 'football', 'Tennis' => 'tennis', 'Basketball' => 'basketball',
        'Cricket' => 'cricket', 'IceHockey' => 'icehockey', 'AmericanFootball' => 'americanfootball',
        'Baseball' => 'baseball', 'HorseRacing' => 'horseracing', 'Greyhounds' => 'greyhounds'];

    /** @param array<string|int, array<string, mixed>> $events */
    public function publish(string $prefix, array $events): void
    {
        if ($events === []) {
            return;
        }
        Redis::eval('for i = 1, #ARGV do redis.call("SADD", KEYS[1], ARGV[i]) end; redis.call("EXPIRE", KEYS[1], 300); return 1',
            1, 'MarketActivity:pending:'.$prefix, ...array_map('strval', array_keys($events)));
        PublishMarketActivity::dispatch($prefix);
    }

    /** @param array<string|int, array<string, mixed>> $events */
    public function broadcastCurrent(string $prefix, array $events): void
    {
        $ids = array_fill_keys(array_map('strval', array_keys($events)), true);
        $cache = sportsMatchRecordCache($prefix.':matches');
        $index = $prefix === 'HorseRacing' && $cache !== null
            ? ['matches' => $cache->metadataForKeys(array_map(static fn (string|int $id): string => 'horseracing|'.$id, array_keys($events)))]
            : $cache?->index(ordered: false);
        $entries = $index['matches'] ?? getInRedis($prefix.':matches', readOnly: true) ?? [];
        $keys = [];
        foreach ($entries as $key => $match) {
            if (isset($ids[(string) ($match['betfair_match_id'] ?? '')])
                && (int) ($match['timestamp'] ?? 0) <= time() + MarketActivityTiming::MAX_WINDOW_SECONDS) {
                $keys[] = $key;
            }
        }
        foreach (array_chunk($keys, 10) as $batch) {
            $matches = $cache === null ? array_intersect_key($entries, array_flip($batch))
                : getInRedis($prefix.':matches', readOnly: true, matchKeys: $batch, snapshot: $index);
            $rows = [];
            $bytes = 0;
            foreach ($matches ?? [] as $key => $match) {
                foreach ($this->markets($prefix, (string) $key, $match) as $market) {
                    foreach ($this->packets($market) as $row) {
                        $size = strlen(json_encode($row, JSON_THROW_ON_ERROR));
                        if ($rows !== [] && $bytes + $size > 3500) {
                            Event::dispatch(new MarketActivityUpdated(self::SPORTS[$prefix], $rows, microtime(true)));
                            $rows = [];
                            $bytes = 0;
                        }
                        $rows[] = $row;
                        $bytes += $size;
                    }
                }
            }
            if ($rows !== []) {
                Event::dispatch(new MarketActivityUpdated(self::SPORTS[$prefix], $rows, microtime(true)));
            }
        }
    }

    /** @return array<string, mixed> */
    public function quote(string $prefix, string $matchKey, string $marketKey): array
    {
        $matches = getInRedis($prefix.':matches', readOnly: true, matchKeys: [$matchKey]) ?? [];
        $match = $matches[$matchKey] ?? null;
        if ($match !== null && isset($match['markets'][$marketKey])) {
            $match['markets'] = [$marketKey => $match['markets'][$marketKey]];
            $quotes = $this->markets($prefix, $matchKey, $match);
            if ($quotes !== []) {
                return $quotes[0];
            }
        }

        return ['matchKey' => $matchKey, 'marketKey' => $marketKey, 'removed' => true, 'runners' => [], 'lastMarketUpdate' => microtime(true)];
    }

    /**
     * Keep exchange books below Reverb's default 10 KB request limit, including JSON escaping.
     *
     * @param  array<string, mixed>  $market
     * @return list<array<string, mixed>>
     */
    public function packets(array $market): array
    {
        $chunks = array_chunk($market['runners'], 10);
        if (count($chunks) <= 1) {
            return [$market];
        }
        $revision = hash('sha256', json_encode($market, JSON_THROW_ON_ERROR));
        $packets = [];
        foreach ($chunks as $part => $runners) {
            $packets[] = array_replace($market, ['runners' => $runners, 'part' => $part, 'parts' => count($chunks), 'revision' => $revision]);
        }

        return $packets;
    }

    /**
     * @param  array<string, mixed>  $match
     * @return list<array<string, mixed>>
     */
    public function markets(string $prefix, string $matchKey, array $match): array
    {
        $racing = in_array($prefix, ['HorseRacing', 'Greyhounds'], true);
        $removed = ($match['result_status'] ?? '') !== 'Prematch'
            || ! MarketActivityTiming::withinHorizon((int) ($match['timestamp'] ?? 0)) || MatchBackup::isHidden($match, self::SPORTS[$prefix]);
        if ($racing && (int) ($match['timestamp'] ?? 0) > time() + 300) {
            return [];
        }
        if ($removed) {
            return array_map(static fn (string $key): array => ['matchKey' => $matchKey, 'marketKey' => $key, 'removed' => true, 'lastMarketUpdate' => microtime(true), 'runners' => []], array_map('strval', array_keys($match['markets'] ?? [])));
        }
        $match = prepareMatchOddsMonitoring($match, true);
        $rows = [];
        foreach ($match['markets'] ?? [] as $key => $runners) {
            if ($runners === [] || ($racing && ($runners[array_key_first($runners)]['marketType'] ?? '') !== 'WIN')) {
                continue;
            }
            if ($racing) {
                uasort($runners, static fn (array $a, array $b): int => ($a['sortPriority'] ?? 0) <=> ($b['sortPriority'] ?? 0));
            }
            $indicator = $match['market_indicators'][$key] ?? [];
            $rows[] = [
                'matchKey' => $matchKey, 'marketKey' => (string) $key, 'removed' => $removed,
                'runners' => array_values(array_map(static fn (array $runner): array => [
                    'id' => $runner['runnerId'] ?? null, 'name' => $runner['runner'] ?? '',
                    'back' => (float) ($runner['currentBackOdds'] ?? $runner['backOdds'] ?? 0),
                    'lay' => (float) ($runner['layOdds'] ?? 0),
                    'backSize' => (float) ($runner['backSize'] ?? 0), 'laySize' => (float) ($runner['laySize'] ?? 0),
                ], $runners)),
                'balance' => $indicator['balance'] ?? 'unknown', 'momentum' => $indicator['momentum'] ?? 'unknown',
                'deltaValue' => $indicator['delta'] ?? null, 'deltaWindows' => $indicator['delta_windows'] ?? [],
                'volatility' => $indicator['volatility'] ?? 'unknown', 'lastMarketUpdate' => $indicator['updated_at'] ?? null,
                'freshUntil' => $indicator['fresh_until'] ?? null, 'stale' => $indicator['stale'] ?? true,
                'active' => $indicator['active'] ?? false, 'liquidityValid' => ($indicator['favorite_back_percent'] ?? null) !== null,
            ];
            $last = array_key_last($rows);
            if (! MarketActivityLiquidity::visible($rows[$last])) {
                $rows[$last]['removed'] = true;
                $rows[$last]['runners'] = [];
            }
        }

        return $rows;
    }
}
