<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_payroll_runs') && ! Schema::hasColumn('salary_payroll_runs', 'apply_attendance_extra')) {
            Schema::table('salary_payroll_runs', function (Blueprint $table) {
                $table->boolean('apply_attendance_extra')->default(false)->after('use_attendance');
            });
        }

        if (Schema::hasTable('salary_payroll_entries')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('salary_payroll_entries', 'attendance_extra_days')) {
                    $table->decimal('attendance_extra_days', 5, 2)->default(0)->after('incentive_amount');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'attendance_extra_amount')) {
                    $table->decimal('attendance_extra_amount', 12, 2)->default(0)->after('attendance_extra_days');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'attendance_extra_applied')) {
                    $table->boolean('attendance_extra_applied')->default(false)->after('attendance_extra_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salary_payroll_runs') && Schema::hasColumn('salary_payroll_runs', 'apply_attendance_extra')) {
            Schema::table('salary_payroll_runs', function (Blueprint $table) {
                $table->dropColumn('apply_attendance_extra');
            });
        }

        if (Schema::hasTable('salary_payroll_entries')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                foreach (['attendance_extra_applied', 'attendance_extra_amount', 'attendance_extra_days'] as $column) {
                    if (Schema::hasColumn('salary_payroll_entries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
