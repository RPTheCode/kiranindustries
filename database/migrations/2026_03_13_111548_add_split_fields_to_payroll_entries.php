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
        if (!Schema::hasColumn('payroll_entries', 'overtime_amount_basic')) {
            Schema::table('payroll_entries', function (Blueprint $table) {
                $table->decimal('overtime_amount_basic', 10, 2)->default(0)->after('overtime_hours');
                $table->decimal('overtime_amount_minimum', 10, 2)->default(0)->after('overtime_amount_basic');
                $table->decimal('shortfall_amount_basic', 10, 2)->default(0)->after('shortfall_amount');
                $table->decimal('shortfall_amount_minimum', 10, 2)->default(0)->after('shortfall_amount_basic');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropColumn([
                'overtime_amount_basic',
                'overtime_amount_minimum',
                'shortfall_amount_basic',
                'shortfall_amount_minimum'
            ]);
        });
    }
};
