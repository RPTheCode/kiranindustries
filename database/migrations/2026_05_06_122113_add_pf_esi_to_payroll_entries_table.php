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
            $table->decimal('pf_amount_employee', 10, 2)->default(0)->after('shortfall_amount_minimum');
            $table->decimal('pf_amount_employer', 10, 2)->default(0)->after('pf_amount_employee');
            $table->decimal('esi_amount_employee', 10, 2)->default(0)->after('pf_amount_employer');
            $table->decimal('esi_amount_employer', 10, 2)->default(0)->after('esi_amount_employee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropColumn(['pf_amount_employee', 'pf_amount_employer', 'esi_amount_employee', 'esi_amount_employer']);
        });
    }
};
