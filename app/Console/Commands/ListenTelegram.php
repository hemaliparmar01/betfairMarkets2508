<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;

class ListenTelegram extends Command
{
    protected $signature = 'telegram:listen {--source : Run the passive source-channel listener} {--login : Authorize the source account interactively} {--once : Read one batch of bot updates}';

    protected $description = 'Receive Telegram bot commands locally or run the read-only source listener';

    public function handle(AlertService $alerts): int
    {
        if (! config('services.telegram.enabled')) {
            $this->error('Configure Telegram secrets and enable BF_ALERTS_ENABLED first.');

            return self::FAILURE;
        }
        if ($this->option('source')) {
            if (blank(config('services.telegram.api_id')) || blank(config('services.telegram.api_hash')) || blank(config('services.telegram.source_secret'))) {
                $this->error('Configure TELEGRAM_API_ID, TELEGRAM_API_HASH and TELEGRAM_SOURCE_SECRET first.');

                return self::FAILURE;
            }
            $process = new Process(array_filter([config('services.telegram.python'), base_path('telegram-listener.py'), $this->option('login') ? '--login' : null]), base_path(), [
                'TELEGRAM_API_ID' => (string) config('services.telegram.api_id'), 'TELEGRAM_API_HASH' => config('services.telegram.api_hash'),
                'TELEGRAM_SOURCE_CHANNEL' => config('services.telegram.source_channel'), 'TELEGRAM_SESSION_FILE' => config('services.telegram.session_file'),
                'TELEGRAM_SOURCE_SECRET' => config('services.telegram.source_secret'), 'BF_SOURCE_URL' => rtrim(config('app.url'), '/').'/notifications/telegram/source',
            ]);
            $process->setTimeout(null);
            if ($this->option('login')) {
                if (! Process::isTtySupported()) {
                    $this->error('Run source login in an interactive terminal.');

                    return self::FAILURE;
                }
                $process->setTty(true);
            }

            return $process->run(fn (string $type, string $output) => $this->output->write($output));
        }
        if (blank(config('services.telegram.bot_token'))) {
            $this->error('Configure TELEGRAM_BOT_TOKEN first.');

            return self::FAILURE;
        }
        $lock = Cache::lock('alerts:bot-listener', 60);
        if (! $lock->get()) {
            $this->error('Another bot listener is active.');

            return self::FAILURE;
        }
        try {
            do {
                try {
                    $response = Http::connectTimeout(5)->timeout(30)->post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/getUpdates', [
                        'offset' => Cache::get('alerts:bot-offset', 0), 'timeout' => $this->option('once') ? 0 : 20, 'allowed_updates' => ['message'],
                    ]);
                    if ($response->json('ok') !== true) {
                        $this->error('Bot updates unavailable. Verify bot configuration; webhook and getUpdates cannot run together.');

                        return self::FAILURE;
                    }
                    foreach ($response->json('result', []) as $update) {
                        $alerts->bind($update);
                        Cache::forever('alerts:bot-offset', (int) $update['update_id'] + 1);
                    }
                } catch (Throwable) {
                    $this->error('Bot update connection failed. No credentials or update content were logged.');
                    if ($this->option('once')) {
                        return self::FAILURE;
                    }
                    sleep(5);
                }
                $lock->release();
                $lock = Cache::lock('alerts:bot-listener', 60);
                if (! $lock->get()) {
                    return self::FAILURE;
                }
            } while (! $this->option('once'));
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
