<?php

namespace App\Services;

use App\Http\Controllers\Users\Football\FootballSyncController;

class FootballMatchCache extends MatchRecordCache
{
    protected function allowed(array $match): bool
    {
        return FootballSyncController::allowsFootballCompetition($match);
    }
}
