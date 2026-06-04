<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'wedding_date')) {
                $table->date('wedding_date')->nullable()->after('date_of_birth');
            }
            if (!Schema::hasColumn('employees', 'resign_date')) {
                $table->date('resign_date')->nullable()->after('working_days');
            }
            if (!Schema::hasColumn('employees', 'resign_reason_id')) {
                $table->unsignedBigInteger('resign_reason_id')->nullable()->after('resign_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['wedding_date', 'resign_date', 'resign_reason_id']);
        });
    }
};
