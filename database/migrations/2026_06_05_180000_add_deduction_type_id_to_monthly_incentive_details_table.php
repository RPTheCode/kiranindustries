<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('monthly_incentive_details') || ! Schema::hasTable('deduction_types')) {
            return;
        }

        if (Schema::hasColumn('monthly_incentive_details', 'deduction_type_id')) {
            return;
        }

        Schema::table('monthly_incentive_details', function (Blueprint $table) {
            $table->unsignedBigInteger('deduction_type_id')->nullable()->after('type_id');
            $table->foreign('deduction_type_id')
                ->references('id')
                ->on('deduction_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('monthly_incentive_details') || ! Schema::hasColumn('monthly_incentive_details', 'deduction_type_id')) {
            return;
        }

        Schema::table('monthly_incentive_details', function (Blueprint $table) {
            $table->dropForeign(['deduction_type_id']);
            $table->dropColumn('deduction_type_id');
        });
    }
};
