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
            // Restore missing column
            if (!Schema::hasColumn('attendance_records', 'overtime_amount')) {
                $table->decimal('overtime_amount', 8, 2)->default(0)->after('overtime_hours');
            }

            // Standardize Split Columns: Drop old versions
            $columnsToDrop = array_filter([
                'ot_amount_basic',
                'ot_amount_minimum',
                'overtime_amount_minimum',
                'shortfall_amount_basic',
                'shortfall_amount_minimum'
            ], function($col) {
                return Schema::hasColumn('attendance_records', $col);
            });

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            // Re-add with consistent naming and 8,2 precision
            if (!Schema::hasColumn('attendance_records', 'overtime_amount_basic')) {
                $table->decimal('overtime_amount_basic', 8, 2)->default(0)->after('overtime_amount');
            }
            if (!Schema::hasColumn('attendance_records', 'overtime_amount_minimum')) {
                $table->decimal('overtime_amount_minimum', 8, 2)->default(0)->after('overtime_amount_basic');
            }
            if (!Schema::hasColumn('attendance_records', 'shortfall_amount_basic')) {
                $table->decimal('shortfall_amount_basic', 8, 2)->default(0)->after('shortfall_amount');
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
                'overtime_amount_basic',
                'overtime_amount_minimum',
                'shortfall_amount_basic',
                'shortfall_amount_minimum'
            ]);
        });
    }
};
