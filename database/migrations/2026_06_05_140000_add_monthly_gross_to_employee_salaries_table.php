<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employee_salaries', 'monthly_gross')) {
            Schema::table('employee_salaries', function (Blueprint $table) {
                $table->decimal('monthly_gross', 12, 2)->nullable()->after('employee_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_salaries', 'monthly_gross')) {
            Schema::table('employee_salaries', function (Blueprint $table) {
                $table->dropColumn('monthly_gross');
            });
        }
    }
};
