<?php

namespace App\Services;

class BasketballMatchCache extends MatchRecordCache
{
    protected const PREFIX = 'Basketball';

    protected const SPORT = 'basketball';

    protected const CATALOG = 'Basketball:matches:catalog';

    protected const IDS = 'Basketball:matches:ids';

    protected const READY = 'Basketball:matches:individual';

    protected function allowed(array $match): bool
    {
        return true;
    }

    protected function canonicalMatches(array $matches): array
    {
        return $matches;
    }

    protected function persistTimeline(array &$match, array $previous): void {}

    protected function entry(string $key, array $match, string $encoded, int $expires): array
    {
        $entry = array_intersect_key($match, array_flip([
            'unique_key', 'sportsApiPro_match_id', 'betfair_match_id', 'timestamp', 'result_status', 'minutes_status',
            'team1', 'team2', 'home_team_short_name', 'away_team_short_name', 'home_team_id', 'away_team_id', 'tournament_image_id', 'tournament', 'tournament_country', 'data_sync_complete', 'is_virtual',
            'home_score', 'away_score', 'prematch_favorite_odds', 'prematch_favorite_side', 'internal_backup', 'provider_lifecycle',
        ]));
        $version = hash('xxh3', $encoded);
        $entry['_fixture_cache_key'] = $key;
        $entry['_fixture_priority'] = 0;
        $entry['_fixture_source_version'] = ['odds' => $version, 'timeline' => $version];
        $entry['_expires_at'] = $expires;
        $entry['_payload_bytes'] = strlen($encoded);
        $entry['_cache_updated_at'] = microtime(true);
        $entry['_market_activity'] = prepareMatchOddsMonitoring($match, marketActivitySummary: true);
        $entry['_market_options'] = [];
        foreach ($match['markets'] ?? [] as $runners) {
            $runner = $runners[array_key_first($runners)] ?? [];
            if (isset($runner['marketType'])) {
                $type = $runner['marketType'];
                $entry['_market_options'][$type] = ['value' => $type, 'label' => $runner['name'] ?? str_replace('_', ' ', $type), 'oddsCount' => count($runners)];
            }
        }

        return $entry;
    }
}
