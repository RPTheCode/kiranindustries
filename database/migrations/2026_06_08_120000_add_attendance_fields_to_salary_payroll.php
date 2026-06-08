<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_payroll_runs') && ! Schema::hasColumn('salary_payroll_runs', 'use_attendance')) {
            Schema::table('salary_payroll_runs', function (Blueprint $table) {
                $table->boolean('use_attendance')->default(true)->after('scope_filters');
            });
        }

        if (Schema::hasTable('salary_payroll_entries')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('salary_payroll_entries', 'working_days')) {
                    $table->decimal('working_days', 5, 2)->default(26)->after('monthly_gross');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'present_days')) {
                    $table->decimal('present_days', 5, 2)->default(0)->after('working_days');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'paid_days')) {
                    $table->decimal('paid_days', 5, 2)->default(0)->after('present_days');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'mispunch_count')) {
                    $table->unsignedSmallInteger('mispunch_count')->default(0)->after('paid_days');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'has_mispunch')) {
                    $table->boolean('has_mispunch')->default(false)->after('mispunch_count');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salary_payroll_runs') && Schema::hasColumn('salary_payroll_runs', 'use_attendance')) {
            Schema::table('salary_payroll_runs', function (Blueprint $table) {
                $table->dropColumn('use_attendance');
            });
        }

        if (Schema::hasTable('salary_payroll_entries')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                $cols = ['working_days', 'present_days', 'paid_days', 'mispunch_count', 'has_mispunch'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('salary_payroll_entries', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
