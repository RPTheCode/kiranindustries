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
            $table->unsignedBigInteger('payroll_entry_id')->nullable()->after('employee_id');
            $table->foreign('payroll_entry_id')->references('id')->on('payroll_entries')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_advances', function (Blueprint $table) {
            //
        });
    }
};
