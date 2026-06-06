<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        if (! Schema::hasColumn('employees', 'extra_salary_component_ids')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->json('extra_salary_component_ids')->nullable()->after('esic_flag');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'extra_salary_component_ids')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('extra_salary_component_ids');
        });
    }
};
