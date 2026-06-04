<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\BiometricAttendanceSyncController;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BiometricSyncAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'biometric:sync-all {--days=2 : Number of days to look back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download logs from ESSL and process them into attendance records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $fromDate = Carbon::now()->subDays($days)->format('Y-m-d');
        $toDate = Carbon::now()->format('Y-m-d');

        $this->info("Step 1: Downloading logs from ESSL device ($fromDate to $toDate)...");
        
        // Run the existing EsslSyncCommand
        Artisan::call('essl:sync', [
            '--from' => $fromDate,
            '--to' => $toDate
        ]);
        
        $this->info(Artisan::output());

        $this->info("Step 2: Processing logs into attendance records...");
        
        // Instantiate the controller to reuse the sync logic
        // Alternatively, move logic to a Service class (preferred but this is quicker for the user)
        $controller = new BiometricAttendanceSyncController();
        
        // Create a mock request
        $request = new Request([
            'from_date' => $fromDate,
            'to_date' => $toDate
        ]);

        try {
            $result = $controller->runSync($request);
            $this->info("Attendance processing completed: {$result['processed_count']} records ({$result['from_label']} to {$result['to_label']})");
        } catch (\Exception $e) {
            $this->error("Processing failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
