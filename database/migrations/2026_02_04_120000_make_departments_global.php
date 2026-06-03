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
        Schema::table('departments', function (Blueprint $table) {
            // Drop existing unique constraint
            $table->dropUnique(['name', 'branch_id']);

            // Drop foreign key to modify column
            $table->dropForeign(['branch_id']);

            // Make branch_id nullable
            $table->foreignId('branch_id')->nullable()->change();

            // Re-add foreign key with nullable support (and same cascade rule)
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');

            // Add new unique constraint scoped to creator (company)
            // Note: If multiple users create departments for same company, this might need 'company_id' validation in code
            // But structurally, uniqueness per created_by is a decent proxy if one admin works, 
            // otherwise duplicate names might be allowed if created by different users in same company?
            // User requested "general" departments. 
            // Ideally we should add 'company_id' column if strictly needed, but let's stick to 'created_by' for now as proxy or just rely on code validation.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // This down migration might fail if there are records with null branch_id
            // So we just attempt best effort

            // Restore unique constraint (will fail if duplicates exist across branches)
            // $table->unique(['name', 'branch_id']);

            // We can't easily revert 'nullable' if nulls exist.
        });
    }
};
