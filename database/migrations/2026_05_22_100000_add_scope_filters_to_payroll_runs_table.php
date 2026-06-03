<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_runs', 'scope_filters')) {
                $table->json('scope_filters')->nullable()->after('department_id');
            }
            if (! Schema::hasColumn('payroll_runs', 'scope_filters_hash')) {
                $table->string('scope_filters_hash', 64)->nullable()->after('scope_filters');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_runs', 'scope_filters_hash')) {
                $table->dropColumn('scope_filters_hash');
            }
            if (Schema::hasColumn('payroll_runs', 'scope_filters')) {
                $table->dropColumn('scope_filters');
            }
        });
    }
};
