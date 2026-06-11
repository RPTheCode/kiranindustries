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

        $pairs = DB::table('designations')
            ->join('departments', 'designations.department_id', '=', 'departments.id')
            ->whereNull('designations.branch_id')
            ->select('designations.id', 'departments.branch_id')
            ->get();

        foreach ($pairs as $pair) {
            DB::table('designations')
                ->where('id', $pair->id)
                ->update(['branch_id' => $pair->branch_id]);
        }
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
