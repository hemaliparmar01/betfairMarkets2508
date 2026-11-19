<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PinnacleArchive
{
    public const RETENTION_SECONDS = 1800;

    private const BATCH_SIZE = 1000;

    public static function dayStart(int $timestamp): int
    {
        return Carbon::createFromTimestampUTC($timestamp)->timezone('Asia/Tbilisi')->subHours(6)->startOfDay()->addHours(6)->timestamp;
    }

    /** @param array<string, array<string, mixed>> $rows */
    public function store(array $rows): void
    {
        $rows = array_map(static function (array $row): array {
            $row['sport'] = AlertService::pinnacleSport($row);
            if (($row['start_timestamp'] ?? 0) > 0 && isset($row['expires_at'])) {
                $row['expires_at'] = min($row['expires_at'], $row['start_timestamp']);
            }

            return $row;
        }, $rows);
        $rows = array_filter($rows, static fn (array $row): bool => AlertService::allowsPinnacleMarket($row + ['starts' => $row['start_timestamp'] ?? null]));
        if ($rows === []) {
            return;
        }
        DB::transaction(function () use ($rows): void {
            $new = $this->account($rows);
            $now = now()->timestamp;
            $details = array_values(array_filter($new, static fn (array $row): bool => $row['expires_at'] > $now));
            foreach (array_chunk($details, self::BATCH_SIZE) as $batch) {
                DB::table('pinnacle_drop_archives')->insertOrIgnore($batch);
            }
        }, 3);
    }

    /** @param array<string, array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function account(array $rows): array
    {
        $batchKey = bin2hex(random_bytes(16));
        $receipts = array_map(static fn (array $row): array => [
            'alert_key' => $row['alert_key'], 'alerted_at' => $row['alerted_at'], 'batch_key' => $batchKey,
        ], array_values($rows));
        DB::table('pinnacle_drop_receipts')->insertOrIgnore($receipts);
        $keys = DB::table('pinnacle_drop_receipts')->where('batch_key', $batchKey)->pluck('alert_key')->all();
        $new = array_intersect_key($rows, array_fill_keys($keys, true));
        $totals = [];
        foreach ($new as $row) {
            $day = self::dayStart((int) $row['alerted_at']);
            $key = $day.'|'.$row['sport'];
            $totals[$key] ??= ['day_start' => $day, 'sport' => $row['sport'], 'total' => 0, 'compared' => 0, 'higher' => 0];
            $totals[$key]['total']++;
            $totals[$key]['compared'] += ($row['betfair_higher'] ?? null) !== null ? 1 : 0;
            $totals[$key]['higher'] += (bool) ($row['betfair_higher'] ?? false) ? 1 : 0;
        }
        ksort($totals);
        foreach ($totals as $total) {
            DB::table('pinnacle_daily_statistics')->insertOrIgnore(['day_start' => $total['day_start'], 'sport' => $total['sport']]);
            DB::table('pinnacle_daily_statistics')->where('day_start', $total['day_start'])->where('sport', $total['sport'])
                ->incrementEach(array_intersect_key($total, array_flip(['total', 'compared', 'higher'])));
        }

        return $new;
    }

    public function backfill(): int
    {
        $count = 0;
        DB::table('pinnacle_drop_archives')->select(['alert_key', 'alerted_at', 'sport', 'betfair_higher'])->orderBy('alert_key')->chunkById(self::BATCH_SIZE, function ($rows) use (&$count): void {
            DB::transaction(function () use ($rows): void {
                $this->account($rows->mapWithKeys(static fn (object $row): array => [$row->alert_key => (array) $row])->all());
                $this->fillExpiry($rows->pluck('alert_key')->all());
            }, 3);
            $count += $rows->count();
        }, 'alert_key', 'alert_key');

        return $count;
    }

    /** @param list<string> $keys */
    private function fillExpiry(array $keys): void
    {
        $received = DB::connection()->getQueryGrammar()->wrap('payload->monitor_received_at');
        DB::table('pinnacle_drop_archives')->whereIn('alert_key', $keys)->whereNull('expires_at')
            ->update(['expires_at' => DB::raw("COALESCE(NULLIF($received, 'null'), alerted_at) + ".self::RETENTION_SECONDS)]);
    }

    private function expired(Builder $query, int $now): Builder
    {
        return $query->where(static function (Builder $query) use ($now): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '<=', $now)
                ->orWhere('is_live', true)->orWhereNotIn('sport', AlertService::PINNACLE_SPORTS)
                ->orWhere(fn (Builder $query): Builder => $query->where('start_timestamp', '>', 0)->where('start_timestamp', '<=', $now));
        });
    }

    public function purge(): int
    {
        $purged = 0;
        $now = now()->timestamp;
        for ($batch = 0; $batch < 20; $batch++) {
            $deleted = DB::transaction(function () use ($now): ?int {
                $rows = $this->expired(DB::table('pinnacle_drop_archives'), $now)
                    ->orderBy('expires_at')->orderBy('alert_key')->limit(self::BATCH_SIZE)->lockForUpdate()
                    ->get(['alert_key', 'alerted_at', 'sport', 'betfair_higher']);
                if ($rows->isEmpty()) {
                    return null;
                }
                $this->account($rows->mapWithKeys(static fn (object $row): array => [$row->alert_key => (array) $row])->all());
                $keys = $rows->pluck('alert_key')->all();
                $this->fillExpiry($keys);

                return $this->expired(DB::table('pinnacle_drop_archives')->whereIn('alert_key', $keys), $now)->delete();
            }, 3);
            if ($deleted === null) {
                break;
            }
            $purged += $deleted;
        }
        $day = self::dayStart($now);
        for ($batch = 0; $batch < 20; $batch++) {
            $keys = DB::table('pinnacle_drop_receipts')->where('alerted_at', '<', $day)
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('pinnacle_drop_archives')
                    ->whereColumn('pinnacle_drop_archives.alert_key', 'pinnacle_drop_receipts.alert_key'))
                ->orderBy('alerted_at')->limit(self::BATCH_SIZE)->pluck('alert_key')->all();
            if ($keys === []) {
                break;
            }
            DB::table('pinnacle_drop_receipts')->whereIn('alert_key', $keys)->delete();
        }

        return $purged;
    }

    /** @return array{sports: Collection, total: int, compared: int, higher: int} */
    public function summary(int $day): array
    {
        $sports = DB::table('pinnacle_daily_statistics')->where('day_start', $day)->whereIn('sport', AlertService::PINNACLE_SPORTS)->orderBy('sport')->get(['sport', 'total', 'compared', 'higher']);

        return ['sports' => $sports, 'total' => (int) $sports->sum('total'), 'compared' => (int) $sports->sum('compared'), 'higher' => (int) $sports->sum('higher')];
    }
}
