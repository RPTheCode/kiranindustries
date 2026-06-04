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
        if (!Schema::hasColumn('salary_components', 'rounding_method')) {
            Schema::table('salary_components', function (Blueprint $table) {
                $table->enum('rounding_method', ['none', 'round', 'ceil', 'floor'])->default('none')->after('percentage_of_gross_pay');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropColumn('rounding_method');
        });
    }
};
