<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_records', 'ot_amount_basic')) {
                $table->decimal('ot_amount_basic', 8, 2)->default(0)->after('overtime_hours');
            }
            if (!Schema::hasColumn('attendance_records', 'ot_amount_minimum')) {
                $table->decimal('ot_amount_minimum', 8, 2)->default(0)->after('ot_amount_basic');
            }
            if (!Schema::hasColumn('attendance_records', 'shortfall_amount_basic')) {
                $table->decimal('shortfall_amount_basic', 8, 2)->default(0)->after('shortfall_hours');
            }
            if (!Schema::hasColumn('attendance_records', 'shortfall_amount_minimum')) {
                $table->decimal('shortfall_amount_minimum', 8, 2)->default(0)->after('shortfall_amount_basic');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn([
                'ot_amount_basic',
                'ot_amount_minimum',
                'shortfall_amount_basic',
                'shortfall_amount_minimum'
            ]);
        });
    }
};
