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
        if (!Schema::hasColumn('salary_components', 'percentage_of_gross_pay')) {
            Schema::table('salary_components', function (Blueprint $table) {
                $table->decimal('percentage_of_gross_pay', 10, 2)->nullable()->after('percentage_of_basic');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropColumn('percentage_of_gross_pay');
        });
    }
};
