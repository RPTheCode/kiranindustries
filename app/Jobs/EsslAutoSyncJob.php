<?php

namespace App\Jobs;

use App\Services\EsslAutoSyncRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EsslAutoSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function handle(EsslAutoSyncRunner $runner): void
    {
        $outcome = $runner->run();

        if ($outcome['status'] === 'ok') {
            Log::info($outcome['message']);
        } elseif ($outcome['status'] === 'error') {
            Log::error('ESSL auto sync job failed: ' . $outcome['message']);
            throw new \RuntimeException($outcome['message']);
        } else {
            Log::debug('ESSL auto sync job skipped: ' . $outcome['message']);
        }
    }
}
