<?php

namespace App\Jobs;

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
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'alerts:'.$this->task.':'.hash('sha256', json_encode($this->data));
    }

    public function handle(AlertService $alerts): void
    {
        if (! config('services.telegram.enabled')) {
            return;
        }
        if ($this->task === 'scan') {
            $alerts->scan();
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
