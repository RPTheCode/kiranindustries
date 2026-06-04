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
        if (!Schema::hasColumn('users', 'last_active_branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('last_active_branch_id')->nullable()->after('id');
                // Optional: Add foreign key constraint if needed, but given the dynamic nature of SaaS/Company structure, keeping it loose might be safer for now unless strictly required.
                // $table->foreign('last_active_branch_id')->references('id')->on('branches')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_active_branch_id');
        });
    }
};
