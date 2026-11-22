<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

test('application file logs remain group writable despite a restrictive process umask', function (string $channel) {
    $directory = sys_get_temp_dir().'/bfmarkets-log-test-'.bin2hex(random_bytes(8));
    File::makeDirectory($directory, 0700, true);
    $previousUmask = umask(0077);
    $logger = null;

    try {
        $logger = Log::build(array_replace(config('logging.channels.'.$channel), ['path' => $directory.'/laravel.log']));
        $logger->info('File log permissions verified.');
        $files = glob($directory.'/*.log');
        $this->assertCount(1, $files);
        clearstatcache(true, $files[0]);
        $this->assertSame(0664, fileperms($files[0]) & 0777);
        $this->assertStringContainsString('File log permissions verified.', file_get_contents($files[0]));
    } finally {
        $logger?->getLogger()->close();
        umask($previousUmask);
        File::deleteDirectory($directory);
    }
})->with(['single', 'daily', 'monthly']);
