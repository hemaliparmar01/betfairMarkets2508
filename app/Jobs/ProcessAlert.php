<?php

namespace App\Jobs;

use App\Http\Controllers\Users\Football\FootballController;
use App\Services\AlertService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessAlert implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 30;

    public int $timeout = 45;

    public int $uniqueFor = 60;

    /** @var list<int> */
    public array $backoff = [5, 15, 30, 60];

    /** @param array<string, mixed> $data */
    public function __construct(public string $task = 'scan', public array $data = [])
    {
        $this->onQueue('alerts');
    }

    public function uniqueId(): string
    {
        return 'alerts:'.$this->task.':'.hash('sha256', json_encode($this->data));
    }

    public function handle(AlertService $alerts): void
    {
        if ($this->task === 'pinnacle') {
            $rows = [];
            $now = now();
            $dayStart = $now->copy()->setTimezone('Asia/Tbilisi')->subHours(6)->startOfDay()->addHours(6)->timestamp;
            foreach ($this->data['alerts'] ?? [] as $drop) {
                if (! is_array($drop) || empty($drop['alert_key']) || empty($drop['home']) || empty($drop['away'])) {
                    continue;
                }
                $from = (float) ($drop['from_price'] ?? 0);
                $to = (float) ($drop['to_price'] ?? 0);
                $at = (int) ($drop['alerted'] ?? 0);
                if (! is_finite($from) || ! is_finite($to) || $from <= 1 || $to <= 1 || round(($from - $to) / $from * 100, 8) < 5
                    || $at < $dayStart || $at > $now->timestamp + 30) {
                    continue;
                }
                $parent = $this->data['parents'][$drop['sport'] ?? 'other'][$drop['parent_id'] ?? ''] ?? null;
                if ($parent !== null) {
                    $drop['archive_parent'] = $parent;
                }
                $starts = $parent['starts'] ?? $parent['start'] ?? $drop['starts'] ?? $drop['start'] ?? null;
                $starts = is_numeric($starts) ? (int) $starts : (is_string($starts) ? (int) strtotime($starts) : 0);
                if ($starts > 100_000_000_000) {
                    $starts = intdiv($starts, 1000);
                }
                $section = strtolower(trim((string) ($drop['sect'] ?? '')));
                $line = $drop['point'] ?? $drop['points'] ?? $drop['hdp'] ?? null;
                if (! is_numeric($line) && preg_match('/([0-9]+(?:\.[0-9]+)?)/', (string) ($drop['outcome'] ?? '').' '.$section, $lineMatch)) {
                    $line = $lineMatch[1];
                }
                $period = (int) ($drop['period'] ?? 0);
                $group = match (true) {
                    $period === 0 && preg_match('/^(money[ _]?line|1x2|match odds)$/', $section) === 1 => 0,
                    $period === 0 && preg_match('/^(total|totals|o\/u)(?:\s|$)/', $section) === 1 && is_numeric($line) && in_array((float) $line, [0.5, 1.5, 2.5, 3.5, 4.5], true) => 1,
                    $period === 0 && in_array($section, ['btts', 'both teams to score'], true) => 2,
                    preg_match('/^(spreads?|asian handicap|handicap)(?:\s|$)/', $section) === 1 => 3,
                    default => 4,
                };
                $sport = preg_replace('/\s+/', '-', strtolower(trim((string) ($drop['sport'] ?? 'other'))));
                $rows[$drop['alert_key']] = ['alert_key' => $drop['alert_key'], 'alerted_at' => $at, 'sport' => $sport === 'hockey' ? 'ice-hockey' : $sport,
                    'is_live' => (bool) ($drop['is_live'] ?? false), 'market_group' => $group,
                    'start_timestamp' => $starts > 0 ? $starts : null, 'payload' => $drop];
            }
            if ($rows !== []) {
                $existing = DB::table('pinnacle_drop_archives')->whereIn('alert_key', array_keys($rows))->pluck('alert_key')->all();
                $rows = array_diff_key($rows, array_fill_keys($existing, true));
                $comparisons = $rows === [] ? [] : array_column(app(FootballController::class)->marketActivity(['alerts' => array_column($rows, 'payload')]), null, 'alert_key');
                foreach ($rows as $key => &$row) {
                    $quote = $comparisons[$key]['bf_at_drop'] ?? null;
                    $row['betfair_higher'] = $quote !== null ? $quote > (float) $row['payload']['to_price'] : null;
                    $row['payload']['bf_at_drop'] = $quote;
                    $row['payload']['bf_quote_at'] = $comparisons[$key]['bf_quote_at'] ?? null;
                    $row['payload'] = json_encode($row['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                }
                unset($row);
                foreach (array_chunk($rows, 500) as $batch) {
                    DB::table('pinnacle_drop_archives')->insertOrIgnore($batch);
                }
            }
            unset($rows, $batch, $comparisons, $existing);
        }
        if (! config('services.telegram.enabled')) {
            return;
        }
        if ($this->task === 'scan') {
            $alerts->scan();
        } elseif ($this->task === 'pinnacle') {
            foreach ($this->data['alerts'] ?? [] as $drop) {
                if (is_array($drop)) {
                    $drop['archive_parent'] ??= $this->data['parents'][$drop['sport'] ?? 'other'][$drop['parent_id'] ?? ''] ?? null;
                    $alerts->process('prematch_drops', [], ['pinnacle' => $drop]);
                }
            }
        } elseif ($this->task === 'source') {
            $alerts->source($this->data);
        } elseif ($this->task === 'fanout') {
            $retry = $alerts->fanout((int) $this->data['alert_id']);
            if ($retry > 0) {
                $this->release($retry);
            }
        } elseif ($this->task === 'deliver') {
            $retry = $alerts->deliver((int) $this->data['delivery_id']);
            if ($retry > 0) {
                $this->release($retry);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->task === 'deliver') {
            DB::table('alert_deliveries')->where('id', $this->data['delivery_id'])
                ->whereIn('status', ['pending', 'retry'])->update(['status' => 'failed', 'error' => 'Delivery retries exhausted.', 'updated_at' => now()]);
        }
    }
}
