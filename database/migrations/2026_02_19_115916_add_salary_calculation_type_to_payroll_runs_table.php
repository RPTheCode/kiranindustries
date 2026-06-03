<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('payroll_runs', 'salary_calculation_type')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->string('salary_calculation_type')->default('basic_pay')->after('payroll_frequency');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn('salary_calculation_type');
        });
    }
};
