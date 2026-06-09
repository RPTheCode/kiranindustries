<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_payroll_entries')) {
            return;
        }

        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_payroll_entries', 'govt_wage_salary_applied')) {
                $table->boolean('govt_wage_salary_applied')->default(false)->after('govt_min_wage_used');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'actual_paid_days')) {
                $table->decimal('actual_paid_days', 5, 2)->nullable()->after('paid_days');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'govt_wage_equiv_days_raw')) {
                $table->decimal('govt_wage_equiv_days_raw', 8, 4)->nullable()->after('govt_wage_salary_applied');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'govt_wage_paid_days')) {
                $table->decimal('govt_wage_paid_days', 5, 2)->nullable()->after('govt_wage_equiv_days_raw');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'contract_regular_earnings')) {
                $table->decimal('contract_regular_earnings', 12, 2)->nullable()->after('govt_wage_paid_days');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'govt_wage_computed_earnings')) {
                $table->decimal('govt_wage_computed_earnings', 12, 2)->nullable()->after('contract_regular_earnings');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'govt_wage_adjustment_amount')) {
                $table->decimal('govt_wage_adjustment_amount', 12, 2)->default(0)->after('govt_wage_computed_earnings');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'govt_wage_adjustment_type')) {
                $table->string('govt_wage_adjustment_type', 16)->nullable()->after('govt_wage_adjustment_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('salary_payroll_entries')) {
            return;
        }

        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            foreach ([
                'govt_wage_adjustment_type',
                'govt_wage_adjustment_amount',
                'govt_wage_computed_earnings',
                'contract_regular_earnings',
                'govt_wage_paid_days',
                'govt_wage_equiv_days_raw',
                'actual_paid_days',
                'govt_wage_salary_applied',
            ] as $column) {
                if (Schema::hasColumn('salary_payroll_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
