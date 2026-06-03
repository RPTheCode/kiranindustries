<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultFy = '2026-2027';

        if (Schema::hasTable('payroll_parameters')) {
            DB::table('payroll_parameters')
                ->whereNull('financial_year')
                ->orWhere('financial_year', '')
                ->update(['financial_year' => $defaultFy]);

            Schema::table('payroll_parameters', function (Blueprint $table) {
                $table->unique('financial_year');
            });
        }

        foreach (['professional_tax_slabs', 'income_tax_slabs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->string('financial_year', 20)->nullable()->after('id');
            });

            DB::table($table)->whereNull('financial_year')->update(['financial_year' => $defaultFy]);

            Schema::table($table, function (Blueprint $table) {
                $table->index('financial_year');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_parameters')) {
            Schema::table('payroll_parameters', function (Blueprint $table) {
                $table->dropUnique(['financial_year']);
            });
        }

        foreach (['professional_tax_slabs', 'income_tax_slabs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['financial_year']);
                $table->dropColumn('financial_year');
            });
        }
    }
};
