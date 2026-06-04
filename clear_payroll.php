<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Payslip;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;

try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    Payslip::truncate();
    PayrollEntry::truncate();
    PayrollRun::truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    echo "Payroll data cleared successfully.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
