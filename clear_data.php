<?php

use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// This script should be run via Artisan tinker or by including it in a Laravel command
// To run: php artisan tinker clear_data.php

try {
    echo "Starting full cleanup of employee and user data...\n";

    // Disable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');

    $tablesToTruncate = [
        'employees',
        'employee_documents',
        'employee_work_histories',
        'employee_salaries',
        'employee_nominees',
        'attendance_records',
        'attendance_regularizations',
        'leave_applications',
        'leave_balances',
        'employee_advances',
        'payroll_runs',
        'payroll_entries',
        'monthly_incentive_entries',
        'daily_production_attendance_entries',
        'biometric_attendances',
        'biometric_attendance_logs',
        'essl_logs',
        'announcements',
        'assets',
        'trips',
        'warnings',
        'complaints',
        'resignations',
        'terminations',
        'promotions',
        'transfers',
        'awards',
        'meetings',
        'meeting_minutes',
        'meeting_attendees',
        'action_items',
        'employee_contracts',
        'contract_renewals',
        'document_acknowledgments',
        'media',
        'sessions',
        'password_resets',
    ];

    foreach ($tablesToTruncate as $table) {
        if (Schema::hasTable($table)) {
            echo "Truncating {$table}...\n";
            DB::table($table)->truncate();
        }
    }

    // 2. Delete users except ID 1 (Super Admin)
    echo "Deleting all users except ID 1...\n";

    // First clear related pivot table data for other users
    if (Schema::hasTable('model_has_roles')) {
        DB::table('model_has_roles')->where('model_id', '!=', 1)->delete();
    }
    if (Schema::hasTable('model_has_permissions')) {
        DB::table('model_has_permissions')->where('model_id', '!=', 1)->delete();
    }
    if (Schema::hasTable('branch_user')) {
        DB::table('branch_user')->where('user_id', '!=', 1)->delete();
    }

    User::where('id', '!=', 1)->forceDelete();

    // Enable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    echo "\nCleanup successful!\n";
    echo "1. All employee records and related data (salaries, documents, etc.) cleared.\n";
    echo "2. All users except ID 1 have been deleted.\n";
    echo "3. Table IDs have been reset.\n";

} catch (\Exception $e) {
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    echo "ERROR: " . $e->getMessage() . "\n";
}
