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
        // Drop unique constraint from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        // Modification for employees table
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_employee_id_unique');
            $table->unique(['employee_id', 'branch_id'], 'unique_employee_branch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('unique_employee_branch');
            $table->unique('employee_id', 'employees_employee_id_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email', 'users_email_unique');
        });
    }
};
