<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            if (! Schema::hasColumn('skills', 'govt_wage_per_day')) {
                $table->decimal('govt_wage_per_day', 10, 2)->nullable()->after('code');
            }
            if (! Schema::hasColumn('skills', 'govt_wage_per_month')) {
                $table->decimal('govt_wage_per_month', 10, 2)->nullable()->after('govt_wage_per_day');
            }
            if (! Schema::hasColumn('skills', 'wage_effective_from')) {
                $table->date('wage_effective_from')->nullable()->after('govt_wage_per_month');
            }
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn(['govt_wage_per_day', 'govt_wage_per_month', 'wage_effective_from']);
        });
    }
};
