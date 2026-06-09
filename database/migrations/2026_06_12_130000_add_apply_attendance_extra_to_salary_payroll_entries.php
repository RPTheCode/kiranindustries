<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_payroll_entries') && ! Schema::hasColumn('salary_payroll_entries', 'apply_attendance_extra')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                $table->boolean('apply_attendance_extra')->default(false)->after('attendance_extra_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salary_payroll_entries') && Schema::hasColumn('salary_payroll_entries', 'apply_attendance_extra')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                $table->dropColumn('apply_attendance_extra');
            });
        }
    }
};
