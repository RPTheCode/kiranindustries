<?php

namespace App\Console\Commands;

use App\Services\EsslAutoSyncConfig;
use App\Services\EsslAutoSyncRunner;
use Illuminate\Console\Command;

class EsslAutoSyncCommand extends Command
{
    protected $signature = 'essl:auto-sync';

    protected $description = 'Run scheduled ESSL biometric sync inside configured time ranges';

    public function handle(EsslAutoSyncRunner $runner): int
    {
        $companyId = $runner->resolveCompanyId();
        if (! $companyId) {
            return self::SUCCESS;
        }

        EsslAutoSyncConfig::applyCompanyTimezone($companyId);
        EsslAutoSyncRunner::pingScheduler();

        $outcome = $runner->run($companyId);

        if ($outcome['status'] === 'skipped') {
            $this->line('ESSL auto sync skipped — ' . $outcome['message']);

            return self::SUCCESS;
        }

        if ($outcome['status'] === 'error') {
            $this->error($outcome['message']);

            return self::FAILURE;
        }

        $this->info($outcome['message']);

        return self::SUCCESS;
    }
}
