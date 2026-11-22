<?php

namespace App\Services;

class MarketActivityTiming
{
    public static function windowSeconds(): int
    {
        $seconds = request()->integer('start_seconds');

        return in_array($seconds, [600, 1200, 1800, 3600, 7200, 10800, 21600], true) ? $seconds : 0;
    }

    public static function until(int $timestamp, ?int $now = null): string
    {
        if ($timestamp <= 0) {
            return '—';
        }
        $minutes = (int) ceil(max(0, $timestamp - ($now ?? time())) / 60);
        if ($minutes >= 1440) {
            $hours = intdiv($minutes % 1440, 60);

            return 'in '.intdiv($minutes, 1440).'d'.($hours > 0 ? ' '.$hours.'h' : '');
        }
        if ($minutes >= 60) {
            return 'in '.intdiv($minutes, 60).'h'.($minutes % 60 > 0 ? ' '.($minutes % 60).'m' : '');
        }

        return 'in '.$minutes.' min';
    }
}
