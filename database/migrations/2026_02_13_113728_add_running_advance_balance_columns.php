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
        Schema::table('employee_advances', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->json('advance_allocations')->nullable()->after('deductions_breakdown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_advances', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropColumn('advance_allocations');
        });
    }
};
