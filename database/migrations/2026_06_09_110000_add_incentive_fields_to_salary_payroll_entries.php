<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_payroll_entries', 'ot_enabled')) {
                $table->boolean('ot_enabled')->default(false)->after('has_mispunch');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'incentive_days')) {
                $table->decimal('incentive_days', 5, 2)->default(0)->after('paid_days');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'incentive_amount')) {
                $table->decimal('incentive_amount', 12, 2)->default(0)->after('incentive_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            foreach (['ot_enabled', 'incentive_days', 'incentive_amount'] as $column) {
                if (Schema::hasColumn('salary_payroll_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
