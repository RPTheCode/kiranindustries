<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('employee_salaries', 'per_day_salary')) {
            Schema::table('employee_salaries', function (Blueprint $table) {
                $table->decimal('per_day_salary', 10, 2)->nullable()->after('basic_salary');
                $table->decimal('min_wage_per_day', 10, 2)->nullable()->after('minimum_wages');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropColumn(['per_day_salary', 'min_wage_per_day']);
        });
    }
};
