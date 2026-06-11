<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('leave_applications', 'branch_id')) {
            Schema::table('leave_applications', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('employee_id')->constrained('branches')->onDelete('cascade');
            });
        }

        // Backfill branch_id from employee's branch_id (portable across MySQL/SQLite)
        $pairs = DB::table('leave_applications')
            ->join('employees', 'leave_applications.employee_id', '=', 'employees.user_id')
            ->select('leave_applications.id', 'employees.branch_id')
            ->get();

        foreach ($pairs as $pair) {
            DB::table('leave_applications')
                ->where('id', $pair->id)
                ->update(['branch_id' => $pair->branch_id]);
        }
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
