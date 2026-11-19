<?php

namespace App\Jobs;

use App\Services\MarketActivityStream;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class PublishMarketActivity implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 10;

    public int $uniqueFor = 30;

    public function __construct(public string $prefix)
    {
        $this->onConnection('sports-realtime')->onQueue('realtime-'.MarketActivityStream::SPORTS[$prefix]);
    }

    public function uniqueId(): string
    {
        return 'market-activity:'.$this->prefix;
    }

    public function uniqueVia(): Repository
    {
        return Cache::store('redis');
    }

    public function handle(MarketActivityStream $stream): void
    {
        try {
            $ids = Redis::eval('local ids = redis.call("SMEMBERS", KEYS[1]); redis.call("DEL", KEYS[1]); return ids', 1, 'MarketActivity:pending:'.$this->prefix);
            if ($ids !== []) {
                $stream->broadcastCurrent($this->prefix, array_fill_keys($ids, []));
            }
        } finally {
            sportsMatchRecordCache($this->prefix.':matches', activeOnly: false)?->releaseReadSnapshot();
        }
    }
}
