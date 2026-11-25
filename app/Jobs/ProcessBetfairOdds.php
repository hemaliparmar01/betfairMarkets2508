<?php

namespace App\Jobs;

use App\Console\Commands\ListenBetfairOdds;
use App\Services\SportsMatchCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProcessBetfairOdds implements ShouldQueue
{
    use Queueable;

    public int $tries = 60;

    public int $timeout = 60;

    /** @param array<string|int, array<string, mixed>> $events */
    public function __construct(public string $sport, public string $feed, public array $events, public int $sequence, public float $receivedAt)
    {
        $queue = $sport === 'horseracing' ? config('services.horseracing_ingestion_queue', 'realtime-horseracing') : 'realtime-'.$sport;
        $this->onConnection('sports-realtime')->onQueue($queue);
    }

    public function handle(ListenBetfairOdds $listener): void
    {
        $store = Cache::store('redis');
        $lock = $store->lock('Betfair:processor:'.$this->sport, 75);
        if (! $lock->get()) {
            $this->release(1);

            return;
        }
        $startedAt = microtime(true);
        try {
            $events = array_filter($this->events, fn (array $event, string|int $id): bool => (int) $store->get($this->cursor((string) $id), 0) < $this->sequence || $this->terminalRace($event), ARRAY_FILTER_USE_BOTH);
            if ($events === []) {
                return;
            }
            foreach ($events as $id => $event) {
                if ((int) $store->get($this->cursor((string) $id), 0) >= $this->sequence && $this->terminalRace($event)) {
                    $events[$id] = ['removed' => true];
                }
            }
            $remaining = $listener->processQueuedChanges($this->feed, $events);
            foreach (array_diff_key($events, $remaining) as $id => $event) {
                $store->put($this->cursor((string) $id), max($this->sequence, (int) $store->get($this->cursor((string) $id), 0)), 86400);
            }
            if ($remaining !== []) {
                throw new RuntimeException('Betfair cache write is busy; pending updates will be retried.');
            }
            $completedAt = microtime(true);
            $health = ['received_at' => $this->receivedAt, 'started_at' => $startedAt, 'completed_at' => $completedAt,
                'queue_delay_ms' => round(($startedAt - $this->receivedAt) * 1000, 1),
                'processing_ms' => round(($completedAt - $startedAt) * 1000, 1), 'memory_mb' => round(memory_get_usage(true) / 1048576, 1), 'sequence' => $this->sequence];
            $store->put('Betfair:processing:'.$this->sport, $health, 86400);
            if ($health['queue_delay_ms'] > 2000 && $store->add('Betfair:processing-warning:'.$this->sport, true, 30)) {
                Log::warning('Betfair sport processing is delayed.', ['sport' => $this->sport] + $health);
            }
        } finally {
            $lock->release();
            foreach (['Football', 'Tennis', 'Basketball', ...array_keys(SportsMatchCache::SPORTS)] as $prefix) {
                sportsMatchRecordCache($prefix.':matches', activeOnly: false)?->releaseReadSnapshot();
            }
        }
    }

    public function backoff(): array
    {
        return [1, 1, 2, 5];
    }

    /** @param array<string, mixed> $event */
    private function terminalRace(array $event): bool
    {
        if (! in_array($this->sport, ['horseracing', 'greyhounds'], true)) {
            return false;
        }
        do {
            if (($event['removed'] ?? false) || ($event['win_market_status'] ?? '') === 'CLOSED') {
                return true;
            }
            $event = $event['_next_event'] ?? [];
        } while ($event !== []);

        return false;
    }

    private function cursor(string $id): string
    {
        return 'Betfair:processed:'.$this->sport.':'.$this->feed.':'.$id;
    }
}
