<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('assets', 'branch_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('id');
                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            });

            // Backfill branch_id based on current assignment's employee
            $assignments = DB::table('asset_assignments')
                ->join('assets', 'asset_assignments.asset_id', '=', 'assets.id')
                ->join('employees', 'asset_assignments.employee_id', '=', 'employees.user_id')
                ->whereNull('asset_assignments.checkin_date') // Current assignment
                ->select('assets.id as asset_id', 'employees.branch_id')
                ->get();

            foreach ($assignments as $assignment) {
                DB::table('assets')
                    ->where('id', $assignment->asset_id)
                    ->update(['branch_id' => $assignment->branch_id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('assets', 'branch_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
