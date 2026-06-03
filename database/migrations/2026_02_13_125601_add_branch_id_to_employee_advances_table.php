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
            $table->unsignedBigInteger('branch_id')->nullable()->after('employee_id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        // Optimize: Update existing records with the employee's current branch as a reasonable default
        // This query joins with users -> employees to get the branch_id
        DB::statement("
            UPDATE employee_advances 
            JOIN employees ON employee_advances.employee_id = employees.user_id 
            SET employee_advances.branch_id = employees.branch_id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_advances', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
