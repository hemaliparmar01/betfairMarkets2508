<?php

namespace App\Console\Commands;

use App\Services\SportsMatchCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class MigrateSportsMatchCaches extends Command
{
    protected $signature = 'sports:cache-migrate {--sport=* : Sport prefix, for example HorseRacing} {--status : Read cache status without migrating} {--refresh-index : Rebuild metadata for already active records with writers stopped}';

    protected $description = 'Migrate sport snapshots to individual records with writers stopped; retain legacy snapshots';

    public function handle(): int
    {
        $prefixes = $this->option('sport') ?: ['Football', 'Tennis', 'Basketball', ...array_keys(SportsMatchCache::SPORTS)];
        foreach ($prefixes as $prefix) {
            $key = $prefix.':matches';
            $migrated = false;
            $cache = sportsMatchRecordCache($key, activeOnly: false);
            if ($cache === null) {
                $this->error('Unsupported sport: '.$prefix);

                return self::FAILURE;
            }
            if (! $this->option('status') && ! $cache->active()) {
                if (! Redis::exists($key)) {
                    $this->warn($prefix.': legacy snapshot missing; skipped.');

                    continue;
                }
                if (! config('services.'.strtolower($prefix).'_match_cache')) {
                    $this->warn($prefix.': individual records disabled; skipped.');

                    continue;
                }
                $result = Cache::store('redis')->lock($key.':write-lock', 600)->block(5, fn (): array => $cache->migrate());
                $migrated = true;
                $this->line($prefix.': migrated '.$result['matches'].' records; legacy snapshot retained.');
                $cache->releaseReadSnapshot();
                gc_collect_cycles();
                gc_mem_caches();
            }
            if (! $this->option('status') && $this->option('refresh-index') && ! $migrated && $cache->active()) {
                $refreshed = Cache::store('redis')->lock($key.':write-lock', 600)->block(5, fn (): int => $cache->refreshMetadata());
                $this->line($prefix.': refreshed '.$refreshed.' metadata entries.');
                $cache->releaseReadSnapshot();
                gc_collect_cycles();
                gc_mem_caches();
            }
            $index = $cache->active() ? $cache->index(ordered: false) : null;
            $this->line(json_encode(['sport' => $prefix, 'individual' => $index !== null, 'matches' => count($index['matches'] ?? []),
                'payload_bytes' => array_sum(array_column($index['matches'] ?? [], '_payload_bytes'))], JSON_THROW_ON_ERROR));
            unset($index);
        }

        return self::SUCCESS;
    }
}
