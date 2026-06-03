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
        if (!Schema::hasColumn('week_offs', 'employment_type')) {
            Schema::table('week_offs', function (Blueprint $table) {
                $table->string('employment_type')->default('Employee')->after('branch_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('week_offs', function (Blueprint $table) {
            $table->dropColumn('employment_type');
        });
    }
};
