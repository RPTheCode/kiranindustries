<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_components')) {
            return;
        }

        if (! Schema::hasColumn('salary_components', 'component_group')) {
            Schema::table('salary_components', function (Blueprint $table) {
                $table->string('component_group', 20)->default('custom')->after('type');
                $table->boolean('assign_to_all')->default(false)->after('component_group');
            });
        }

        foreach (['BASIC', 'HRA', 'LTA'] as $name) {
            DB::table('salary_components')
                ->whereRaw('UPPER(name) = ?', [$name])
                ->update([
                    'component_group' => 'primary',
                    'assign_to_all' => true,
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('salary_components')) {
            return;
        }

        Schema::table('salary_components', function (Blueprint $table) {
            if (Schema::hasColumn('salary_components', 'assign_to_all')) {
                $table->dropColumn('assign_to_all');
            }
            if (Schema::hasColumn('salary_components', 'component_group')) {
                $table->dropColumn('component_group');
            }
        });
    }
};
