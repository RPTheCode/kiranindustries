<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_payroll_entries', 'half_days')) {
                $table->decimal('half_days', 5, 2)->default(0)->after('present_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            if (Schema::hasColumn('salary_payroll_entries', 'half_days')) {
                $table->dropColumn('half_days');
            }
        });
    }
};
