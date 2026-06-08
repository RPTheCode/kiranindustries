<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_payroll_entries', 'week_off_worked_days')) {
                $table->decimal('week_off_worked_days', 5, 2)->default(0)->after('half_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            if (Schema::hasColumn('salary_payroll_entries', 'week_off_worked_days')) {
                $table->dropColumn('week_off_worked_days');
            }
        });
    }
};
