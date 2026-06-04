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
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->enum('overtime_type', ['fixed', 'salary_based'])->default('fixed')->after('early_departure_grace');
            $table->integer('weekoff_full_day_hours')->default(6)->after('overtime_rate_per_hour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn(['overtime_type', 'weekoff_full_day_hours']);
        });
    }
};
