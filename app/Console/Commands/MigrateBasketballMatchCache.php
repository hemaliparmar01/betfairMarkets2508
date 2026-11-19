<?php

namespace App\Console\Commands;

use App\Services\BasketballMatchCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class MigrateBasketballMatchCache extends Command
{
    protected $signature = 'basketball:cache-migrate {--status : Show current cache state without migrating} {--match= : Inspect a SportsAPI Pro match ID}';

    protected $description = 'Move the basketball snapshot to individual match records; run with basketball writers stopped';

    public function handle(BasketballMatchCache $cache): int
    {
        if ($this->option('status')) {
            $this->info($cache->active() ? 'Individual basketball match records active' : 'Legacy basketball cache active');
            if (! $cache->active()) {
                return self::SUCCESS;
            }
            $index = $cache->index();
            $this->line('Retained matches: '.count($index['matches']));
            $this->line('Serialized match bytes: '.array_sum(array_column($index['matches'], '_payload_bytes')));
            $this->line('Index bytes: '.strlen(json_encode($index, JSON_THROW_ON_ERROR)));
            if ($id = $this->option('match')) {
                $matches = $cache->read([(string) $id]);
                foreach ($matches as $key => $match) {
                    $entry = $index['matches'][$key];
                    $this->line(json_encode([
                        'match_id' => $id, 'redis_key' => $cache->recordKey((string) $key),
                        'status' => $match['result_status'] ?? null,
                        'home_score' => $match['home_score'] ?? null, 'away_score' => $match['away_score'] ?? null,
                        'cache_updated_at_utc' => gmdate('Y-m-d H:i:s', (int) $entry['_cache_updated_at']),
                        'payload_bytes' => strlen(json_encode($match, JSON_THROW_ON_ERROR)),
                        'provider_sequence' => $match['provider_lifecycle']['sequence'] ?? null,
                    ], JSON_THROW_ON_ERROR));
                }
                if ($matches === []) {
                    $this->warn('Match record not found.');
                }
            }

            return self::SUCCESS;
        }
        if (! config('services.basketball_match_cache')) {
            $this->error('Basketball match records are disabled in configuration.');

            return self::FAILURE;
        }
        if (! $cache->active() && ! Redis::exists('Basketball:matches')) {
            $this->error('Legacy basketball snapshot is missing. Resume the feeds to rebuild it before migrating.');

            return self::FAILURE;
        }
        $result = Cache::store('redis')->lock('Basketball:matches:write-lock', 600)->block(5, fn (): array => $cache->migrate());
        $this->info('Migrated '.$result['matches'].' basketball matches from '.$result['legacy_bytes'].' bytes.');

        return self::SUCCESS;
    }
}
