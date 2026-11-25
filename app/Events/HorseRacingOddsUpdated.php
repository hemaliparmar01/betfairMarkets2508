<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class HorseRacingOddsUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param array<string, mixed> $race */
    public function __construct(public array $race) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sports.horseracing.odds.'.str_replace('.', '_', $this->race['id']))];
    }

    public function broadcastAs(): string
    {
        return 'odds.updated';
    }

    public function broadcastWith(): array
    {
        return $this->race;
    }
}
