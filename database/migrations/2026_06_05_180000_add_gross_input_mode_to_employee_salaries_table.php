<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employee_salaries', 'gross_input_mode')) {
            Schema::table('employee_salaries', function (Blueprint $table) {
                $table->string('gross_input_mode', 10)->default('month')->after('monthly_gross');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_salaries', 'gross_input_mode')) {
            Schema::table('employee_salaries', function (Blueprint $table) {
                $table->dropColumn('gross_input_mode');
            });
        }
    }
};
