<?php

namespace App\Services;

use App\Http\Controllers\Users\Tennis\TennisSyncController;

class TennisMatchCache extends MatchRecordCache
{
    protected const PREFIX = 'Tennis';

    protected const SPORT = 'tennis';

    protected const CATALOG = 'Tennis:matches:catalog';

    protected const IDS = 'Tennis:matches:ids';

    protected const READY = 'Tennis:matches:individual';

    protected function allowed(array $match): bool
    {
        return TennisSyncController::tennisCompetition($match) !== null;
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
            'tournament', 'tournament_country', 'tournament_tour', 'data_sync_complete', 'tennis_competition', 'internal_backup', 'provider_lifecycle',
            'home_score', 'away_score', 'current_set', 'tennis_score_version',
        ]));
        $version = hash('xxh3', $encoded);
        $entry['_fixture_cache_key'] = $key;
        $entry['_fixture_priority'] = TennisSyncController::tennisCompetitionPriority($match);
        $entry['_fixture_source_version'] = ['odds' => $version, 'timeline' => $version];
        $entry['_expires_at'] = $expires;
        $entry['_payload_bytes'] = strlen($encoded);
        $entry['_cache_updated_at'] = microtime(true);

        return $entry;
    }
}
