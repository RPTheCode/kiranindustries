<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            if (!Schema::hasColumn('designations', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('created_by')->constrained('branches')->onDelete('cascade');
            }
        });

        // Backfill branch_id from department's branch_id
        DB::statement('UPDATE designations d JOIN departments dept ON d.department_id = dept.id SET d.branch_id = dept.branch_id WHERE d.branch_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
