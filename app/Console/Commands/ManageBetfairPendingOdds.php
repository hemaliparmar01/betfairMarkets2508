<?php

namespace App\Console\Commands;

use App\Jobs\DrainBetfairOdds;
use App\Jobs\PersistHorseRacingMatches;
use App\Jobs\ProcessBetfairOdds;
use App\Jobs\PublishHorseRacingOdds;
use App\Services\BetfairPendingOdds;
use App\Services\MarketActivityStream;
use Illuminate\Bus\UniqueLock;
use Illuminate\Console\Command;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class ManageBetfairPendingOdds extends Command
{
    protected $signature = 'betfair:pending {--sport= : Sport name, e.g. football} {--wake : Schedule a small drain job} {--fresh-start : Discard old realtime sports jobs and pending odds; requires paused workers} {--compact-legacy : Convert old ready jobs into the pending buffer} {--apply : Perform legacy conversion} {--workers-paused : Confirm the producer and selected queue worker are stopped} {--limit=100 : Maximum old jobs to convert}';

    protected $description = 'Inspect bounded Betfair pending buffers, wake consumers, or safely compact legacy ready jobs';

    public function handle(BetfairPendingOdds $pending): int
    {
        $sport = $this->option('sport');
        if ($sport !== null && ! in_array($sport, MarketActivityStream::SPORTS, true)) {
            $this->error('Unsupported sport.');

            return self::FAILURE;
        }
        if ($this->option('fresh-start')) {
            if ($sport !== null || $this->option('compact-legacy') || $this->option('wake')) {
                $this->error('--fresh-start cannot be combined with --sport, --compact-legacy, or --wake.');

                return self::FAILURE;
            }

            return $this->freshStart($pending);
        }
        if ($this->option('compact-legacy')) {
            return $this->compact($pending, $sport);
        }
        foreach ($sport === null ? MarketActivityStream::SPORTS : [$sport] as $name) {
            if ($this->option('wake')) {
                DrainBetfairOdds::dispatch($name);
            }
            $this->line(json_encode(['sport' => $name] + $pending->status($name), JSON_THROW_ON_ERROR));
        }

        return self::SUCCESS;
    }

    private function freshStart(BetfairPendingOdds $pending): int
    {
        $queue = Queue::connection('sports-realtime');
        if (! $queue instanceof RedisQueue) {
            $this->error('Fresh cutover requires the sports-realtime Redis queue connection.');

            return self::FAILURE;
        }
        $names = ['ingest-horseracing', 'realtime-horseracing'];
        foreach (MarketActivityStream::SPORTS as $sport) {
            $names[] = (new DrainBetfairOdds($sport))->queue;
        }
        $names = array_values(array_unique($names));
        $counts = [];
        foreach ($names as $name) {
            $counts[$name] = $queue->size($name);
        }
        $this->line(json_encode(['target_queues' => $counts], JSON_THROW_ON_ERROR));
        if (! $this->option('apply')) {
            $this->info('Read-only. Fresh cutover discards old sports jobs and requires --apply --workers-paused.');

            return self::SUCCESS;
        }
        if (! $this->option('workers-paused')) {
            $this->error('Stop all sports producers and workers before applying fresh cutover. Nothing discarded.');

            return self::FAILURE;
        }
        foreach ($names as $name) {
            $removed = $queue->clear($name);
            $this->line(json_encode(['queue' => $name, 'discarded_jobs' => $removed], JSON_THROW_ON_ERROR));
        }
        $locks = new UniqueLock(Cache::store('redis'));
        foreach (MarketActivityStream::SPORTS as $sport) {
            $pending->discard($sport);
            $locks->release(new DrainBetfairOdds($sport));
        }
        Redis::del('HorseRacing:broadcast:pending', 'HorseRacing:persistence:pending');
        $locks->release(new PublishHorseRacingOdds);
        $locks->release(new PersistHorseRacingMatches);
        $this->info('Fresh sports cutover complete. Existing fixture data and non-realtime queues were retained. Start consumers before feeds to receive fresh snapshots.');

        return self::SUCCESS;
    }

    private function compact(BetfairPendingOdds $pending, ?string $sport): int
    {
        if ($sport === null) {
            $this->error('Select one --sport for legacy conversion.');

            return self::FAILURE;
        }
        $queue = (new DrainBetfairOdds($sport))->queue;
        $key = 'queues:'.$queue;
        $redis = $pending->connection();
        $this->line(json_encode(['queue' => $queue, 'ready' => $redis->llen($key), 'reserved' => $redis->zcard($key.':reserved'), 'delayed' => $redis->zcard($key.':delayed')], JSON_THROW_ON_ERROR));
        if (! $this->option('apply')) {
            $this->info('Read-only. Conversion requires --apply --workers-paused; no jobs changed.');

            return self::SUCCESS;
        }
        if (! $this->option('workers-paused') || $redis->zcard($key.':reserved') > 0) {
            $this->error('Stop the producer and selected worker first; reserved jobs must be zero. Nothing removed.');

            return self::FAILURE;
        }
        $limit = max(1, min(10000, (int) $this->option('limit')));
        $converted = 0;
        $index = 0;
        $preserved = [];
        try {
            while ($converted < $limit && ($payload = $redis->lindex($key, $index))) {
                $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
                if (($data['data']['commandName'] ?? '') !== ProcessBetfairOdds::class) {
                    $type = (string) ($data['data']['commandName'] ?? $data['displayName'] ?? $data['job'] ?? 'unknown');
                    $preserved[$type]['jobs'] = ($preserved[$type]['jobs'] ?? 0) + 1;
                    $preserved[$type]['payload_bytes'] = ($preserved[$type]['payload_bytes'] ?? 0) + strlen($payload);
                    $index++;

                    continue;
                }
                $job = unserialize($data['data']['command'], ['allowed_classes' => [ProcessBetfairOdds::class]]);
                if (! $job instanceof ProcessBetfairOdds || $job->sport !== $sport || $job->queue !== $queue) {
                    $this->error('Unexpected legacy job; no removal performed.');

                    return self::FAILURE;
                }
                foreach ($job->events as $id => $event) {
                    $pending->put($sport, $job->feed, (string) $id, $event, $job->sequence, $job->receivedAt);
                }
                $removed = $redis->eval(<<<'LUA'
if redis.call('LINDEX', KEYS[1], tonumber(ARGV[1])) ~= ARGV[2] then return 0 end
redis.call('LSET', KEYS[1], tonumber(ARGV[1]), ARGV[3])
redis.call('LREM', KEYS[1], 1, ARGV[3])
redis.call('LPOP', KEYS[2])
return 1
LUA, 2, $key, $key.':notify', $index, $payload, 'betfair-converted:'.Str::uuid());
                if (! $removed) {
                    $this->error('Queue changed during conversion. Stop all selected consumers before retrying.');

                    return self::FAILURE;
                }
                $converted++;
                if ($converted % 100 === 0) {
                    $this->line('Converted '.$converted.' legacy jobs; other job types retain their order.');
                }
            }
        } catch (Throwable $error) {
            $this->error('Conversion stopped safely: '.$error->getMessage());
            $this->line(json_encode(['converted' => $converted] + $pending->status($sport), JSON_THROW_ON_ERROR));

            return self::FAILURE;
        }
        if ($converted > 0) {
            DrainBetfairOdds::dispatch($sport);
        }
        $this->line(json_encode(['converted' => $converted, 'ready_remaining' => $redis->llen($key), 'preserved_types' => $preserved] + $pending->status($sport), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
