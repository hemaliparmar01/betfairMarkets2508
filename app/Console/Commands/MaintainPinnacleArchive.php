<?php

namespace App\Console\Commands;

use App\Services\PinnacleArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MaintainPinnacleArchive extends Command
{
    protected $signature = 'pinnacle:archive-maintain {--backfill : Account existing archive details before applying retention}';

    protected $description = 'Preserve daily Pinnacle totals and purge expired 30-minute alert details in bounded batches';

    public function handle(PinnacleArchive $archive): int
    {
        $lock = Cache::lock('pinnacle:archive-maintenance', 3600);
        if (! $lock->get()) {
            $this->warn('Pinnacle archive maintenance is already running.');

            return self::FAILURE;
        }
        try {
            if ($this->option('backfill')) {
                $this->info('Backfilling existing archive records in batches of 1000...');
                $this->info('Existing archive records accounted: '.$archive->backfill());
            }
            $this->info('Expired archive details purged: '.$archive->purge());

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
