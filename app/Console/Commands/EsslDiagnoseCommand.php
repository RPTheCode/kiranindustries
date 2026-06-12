<?php

namespace App\Console\Commands;

use App\Services\EsslAutoSyncConfig;
use App\Services\EsslAutoSyncRunner;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EsslDiagnoseCommand extends Command
{
    protected $signature = 'essl:diagnose';

    protected $description = 'Debug ESSL automatic sync — scheduler, timezone, ranges, last run';

    public function handle(EsslAutoSyncRunner $runner): int
    {
        $this->info('=== ESSL Auto Sync Diagnostics ===');
        $this->newLine();

        $companyId = $runner->resolveCompanyId();
        if (! $companyId) {
            $this->error('FAIL: No company user (type=company) in database.');

            return self::FAILURE;
        }
        $this->line("Company user id: {$companyId}");

        $tz = EsslAutoSyncConfig::applyCompanyTimezone($companyId);
        $now = Carbon::now($tz);
        $this->line("PHP timezone: " . date_default_timezone_get());
        $this->line("App timezone: " . config('app.timezone'));
        $this->line("Company timezone: {$tz}");
        $this->line("Now (company): {$now->format('Y-m-d H:i:s')}");
        $this->newLine();

        $settings = EsslAutoSyncConfig::settingsPayload($companyId);
        $this->line('Auto sync enabled: ' . ($settings['enabled'] ? 'YES' : 'NO'));
        $this->line('Scheduler ping: ' . ($settings['scheduler_last_ping'] ?? 'never'));
        $this->line('Scheduler running: ' . ($settings['scheduler_running'] ? 'YES' : 'NO'));
        $this->line('Last auto run: ' . ($settings['last_run_at'] ?? 'never')
            . ($settings['last_run_slot'] ? " [{$settings['last_run_slot']}]" : ''));
        $this->line('Active range now: ' . ($settings['active_range'] ?? 'none'));
        $this->newLine();

        $this->line('Configured ranges:');
        foreach ($settings['ranges'] as $i => $range) {
            $this->line(sprintf(
                '  [%d] %s  %s–%s  every %dm',
                $i,
                $range['label'],
                $range['from'],
                $range['to'],
                $range['interval_minutes'] ?? 15
            ));
        }
        $this->newLine();

        $plan = $runner->evaluate($companyId);
        if ($plan) {
            $this->info("Would RUN now: [{$plan['label']}]");
        } elseif (! $settings['enabled']) {
            $this->warn('Would SKIP: auto sync disabled in settings');
        } elseif (! $settings['active_range']) {
            $this->warn("Would SKIP: outside time ranges (now {$now->format('H:i')})");
        } else {
            $this->warn('Would SKIP: waiting for interval since last run');
        }

        $this->newLine();
        $this->line('Queue: ' . config('queue.default'));
        $this->line('Tick cache: ' . (Cache::get('essl_scheduler_tick_active') ? 'active' : 'inactive'));
        $syncLock = Cache::lock('essl_sync_running', 1);
        if ($syncLock->get()) {
            $syncLock->release();
            $this->line('Sync lock: free');
        } else {
            $this->warn('Sync lock: held (another sync running or stale lock up to 15 min)');
        }

        try {
            DB::connection('essl')->getPdo();
            $this->info('ESSL ODBC connection: OK');
        } catch (\Throwable $e) {
            $this->error('ESSL ODBC connection: FAIL — ' . $e->getMessage());
        }

        $this->newLine();
        if (! $settings['scheduler_running']) {
            $this->error('FIX: Scheduler not running. On server ensure supervisor has worker-scheduler (schedule:work).');
            $this->line('  Local: php artisan schedule:work');
            $this->line('  Or: php artisan queue:work + php artisan essl:ensure-scheduler');
        } elseif (! $settings['enabled']) {
            $this->warn('FIX: Enable Automatic Sync in UI and Save.');
        } else {
            $this->info('Configuration looks OK — wait for next interval inside active range.');
        }

        return self::SUCCESS;
    }
}
