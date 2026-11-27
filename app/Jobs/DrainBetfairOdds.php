<?php

namespace App\Jobs;

use App\Console\Commands\ListenBetfairOdds;
use App\Exceptions\BetfairCacheBusy;
use App\Services\BetfairPendingOdds;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DrainBetfairOdds implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 60;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    public function __construct(public string $sport)
    {
        $queue = $sport === 'horseracing' ? config('services.horseracing_ingestion_queue', 'realtime-horseracing') : 'realtime-'.$sport;
        $this->onConnection('sports-realtime')->onQueue($queue);
    }

    public function uniqueId(): string
    {
        return 'betfair-pending-'.$this->sport;
    }

    public function uniqueVia(): Repository
    {
        return Cache::store('redis');
    }

    public function handle(BetfairPendingOdds $pending, ListenBetfairOdds $listener): void
    {
        foreach ($pending->batch($this->sport, 1) as $member => $record) {
            $processor = new ProcessBetfairOdds($this->sport, $record['feed'], [$record['id'] => $record['event']], $record['sequence'], $record['latest_received_at']);
            try {
                $processor->handle($listener);
            } catch (BetfairCacheBusy) {
                if (Cache::store('redis')->add('Betfair:busy-warning:'.$this->sport, true, 30)) {
                    Log::warning('Betfair cache writer is busy; latest odds remain pending.', ['sport' => $this->sport]);
                }
                $this->release(1);

                return;
            }
            if (! $processor->completed) {
                $this->release(1);

                return;
            }
            $pending->acknowledge($this->sport, $member, $record['sequence']);
        }
        if ($pending->status($this->sport)['pending_events'] > 0) {
            self::dispatch($this->sport);
        }
    }

    public function backoff(): array
    {
        return [1, 1, 2, 5];
    }
}
