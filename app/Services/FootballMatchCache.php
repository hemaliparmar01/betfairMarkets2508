<?php

namespace App\Services;

use App\Http\Controllers\Users\Football\FootballSyncController;

class FootballMatchCache extends MatchRecordCache
{
    public static function allowsMarketData(array $match): bool
    {
        return ! empty($match['betfair_match_id']) || FootballSyncController::allowsFootballCompetition($match);
    }

    protected function allowed(array $match): bool
    {
        return self::allowsMarketData($match);
    }
}
