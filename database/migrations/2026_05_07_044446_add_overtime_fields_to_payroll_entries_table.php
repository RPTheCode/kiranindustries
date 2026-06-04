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
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->decimal('overtime_days', 8, 2)->nullable()->default(0)->after('absent_days');
            $table->decimal('overtime_salary', 10, 2)->nullable()->default(0)->after('overtime_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropColumn(['overtime_days', 'overtime_salary']);
        });
    }
};
