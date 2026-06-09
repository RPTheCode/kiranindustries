<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'standard_working_days')) {
                $table->unsignedTinyInteger('standard_working_days')->nullable()->after('wage_zone_id');
            }
            if (! Schema::hasColumn('branches', 'use_government_wage_rules')) {
                $table->boolean('use_government_wage_rules')->default(false)->after('standard_working_days');
            }
            if (! Schema::hasColumn('branches', 'govt_wage_mode')) {
                $table->string('govt_wage_mode', 32)->default('pf_compliance')->after('use_government_wage_rules');
            }
        });

        if (Schema::hasTable('payroll_parameters')) {
            Schema::table('payroll_parameters', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_parameters', 'standard_working_days')) {
                    $table->unsignedTinyInteger('standard_working_days')->nullable()->after('esic_wage_limit');
                }
            });
        }

        if (Schema::hasTable('salary_payroll_entries')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('salary_payroll_entries', 'govt_min_wage_per_day')) {
                    $table->decimal('govt_min_wage_per_day', 10, 2)->nullable()->after('pf_wages');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'govt_min_wage_used')) {
                    $table->decimal('govt_min_wage_used', 12, 2)->nullable()->after('govt_min_wage_per_day');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'working_days_source')) {
                    $table->string('working_days_source', 32)->nullable()->after('working_days');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salary_payroll_entries')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                foreach (['working_days_source', 'govt_min_wage_used', 'govt_min_wage_per_day'] as $column) {
                    if (Schema::hasColumn('salary_payroll_entries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('payroll_parameters')) {
            Schema::table('payroll_parameters', function (Blueprint $table) {
                if (Schema::hasColumn('payroll_parameters', 'standard_working_days')) {
                    $table->dropColumn('standard_working_days');
                }
            });
        }

        Schema::table('branches', function (Blueprint $table) {
            foreach (['govt_wage_mode', 'use_government_wage_rules', 'standard_working_days'] as $column) {
                if (Schema::hasColumn('branches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
