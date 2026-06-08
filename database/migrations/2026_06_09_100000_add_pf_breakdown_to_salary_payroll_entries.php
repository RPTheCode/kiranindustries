<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_payroll_entries', 'pf_wages')) {
                $table->decimal('pf_wages', 12, 2)->default(0)->after('pf_employee');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'pf_eps_employer')) {
                $table->decimal('pf_eps_employer', 12, 2)->default(0)->after('pf_employer');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'pf_epf_employer')) {
                $table->decimal('pf_epf_employer', 12, 2)->default(0)->after('pf_eps_employer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            foreach (['pf_wages', 'pf_eps_employer', 'pf_epf_employer'] as $column) {
                if (Schema::hasColumn('salary_payroll_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
