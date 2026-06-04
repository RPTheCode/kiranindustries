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
        if (!Schema::hasColumn('attendance_records', 'shortfall_hours')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->decimal('shortfall_hours', 5, 2)->default(0)->after('overtime_amount');
                $table->decimal('shortfall_amount', 8, 2)->default(0)->after('shortfall_hours');
            });
        }
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['shortfall_hours', 'shortfall_amount']);
        });
    }
};
