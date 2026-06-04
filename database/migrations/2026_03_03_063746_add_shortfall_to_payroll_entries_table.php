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
        if (!Schema::hasColumn('payroll_entries', 'shortfall_hours')) {
            Schema::table('payroll_entries', function (Blueprint $table) {
                $table->decimal('shortfall_hours', 5, 2)->default(0)->after('overtime_hours');
                $table->decimal('shortfall_amount', 10, 2)->default(0)->after('overtime_amount');
            });
        }
    }

    public function down(): void
    {
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropColumn(['shortfall_hours', 'shortfall_amount']);
        });
    }
};
