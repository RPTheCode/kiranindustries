<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReportDownload;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupOldReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up report downloads older than 3 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $threeDaysAgo = Carbon::now()->subDays(3);

        $oldReports = ReportDownload::where('created_at', '<', $threeDaysAgo)->get();
        $count = 0;

        foreach ($oldReports as $report) {
            if ($report->file_path && str_starts_with($report->file_path, 'reports/')) {
                Storage::disk('public')->delete($report->file_path);
            }
            $report->delete();
            $count++;
        }

        $this->info("Successfully cleaned up {$count} old reports.");
    }
}
