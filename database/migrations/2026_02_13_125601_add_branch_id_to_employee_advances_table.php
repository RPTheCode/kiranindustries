<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        $pairs = DB::table('employee_advances')
            ->join('employees', 'employee_advances.employee_id', '=', 'employees.user_id')
            ->select('employee_advances.id', 'employees.branch_id')
            ->get();

        foreach ($pairs as $pair) {
            DB::table('employee_advances')
                ->where('id', $pair->id)
                ->update(['branch_id' => $pair->branch_id]);
        }
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
