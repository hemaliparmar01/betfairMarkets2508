<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MarketActivityUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param list<array<string, mixed>> $markets */
    public function __construct(public string $sport, public array $markets, public float $sentAt) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sports.'.$this->sport.'.market-activity')];
    }

    public function broadcastAs(): string
    {
        return 'market.updated';
    }

    public function broadcastWith(): array
    {
        return ['markets' => $this->markets, 'sent_at' => $this->sentAt];
    }
}
