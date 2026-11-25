<?php

namespace App\Services;

use App\Events\HorseRacingOddsUpdated;
use Illuminate\Support\Facades\Event;

class HorseRacingOddsStream
{
    /** @param array<string, array<string, mixed>> $matches */
    public function publish(array $matches): void
    {
        foreach ($matches as $match) {
            foreach ($this->packets($match) as $packet) {
                Event::dispatch(new HorseRacingOddsUpdated($packet));
            }
        }
    }

    /**
     * Full books are fragmented so even large fields stay below Reverb's request limit.
     * History is recovered by HTTP, rather than retransmitted on every quote.
     *
     * @param  array<string, mixed>  $match
     * @return list<array<string, mixed>>
     */
    public function packets(array $match): array
    {
        $runners = [];
        foreach ($match['markets'] ?? [] as $market) {
            foreach ($market as $runner) {
                $runners[] = array_intersect_key($runner, array_flip(['marketId', 'marketType', 'runnerId', 'runner', 'handicap', 'sortPriority', 'number',
                    'initialBackOdds', 'initialBackOddsTimestamp', 'opening_odds_source', 'currentBackOdds', 'previousBackOdds', 'backSize', 'layOdds', 'previousLayOdds', 'laySize', 'odds_direction', 'pressure_type', 'pressure_amount', 'pressure_source']));
            }
        }
        $chunks = [];
        $chunk = [];
        $bytes = 0;
        foreach ($runners as $runner) {
            $size = strlen(json_encode($runner, JSON_THROW_ON_ERROR));
            if ($chunk !== [] && (count($chunk) >= 4 || $bytes + $size > 3000)) {
                $chunks[] = $chunk;
                $chunk = [];
                $bytes = 0;
            }
            $chunk[] = $runner;
            $bytes += $size;
        }
        if ($chunk !== [] || $chunks === []) {
            $chunks[] = $chunk;
        }
        $base = ['id' => (string) $match['betfair_match_id'], 'version' => (int) ($match['_odds_version'] ?? 0),
            'status' => $match['result_status'], 'market_status' => $match['market_status'] ?? 'OPEN',
            'indicators' => array_intersect_key($match['market_indicators'][$match['betfair_match_id'].'_WIN'] ?? [], array_flip(['favorite_id', 'balance', 'momentum', 'delta', 'favorite_back_percent', 'favorite_lay_percent', 'liquidity', 'volatility', 'updated_at', 'stale', 'active'])),
            'received_at' => $match['_odds_received_at'] ?? null,
            'timestamp' => (int) $match['timestamp'], 'sent_at' => microtime(true), 'parts' => count($chunks)];
        $packets = [];
        foreach ($chunks as $part => $runners) {
            $packets[] = $base + ['part' => $part, 'runners' => $runners];
        }

        return $packets;
    }
}
