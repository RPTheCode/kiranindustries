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
        Schema::table('biometric_attendances', function (Blueprint $table) {
            $table->renameColumn('late_minutes', 'late_in');
            $table->renameColumn('early_minutes', 'early_out');
        });

        Schema::table('biometric_attendances', function (Blueprint $table) {
            $table->string('late_in')->nullable()->change();
            $table->string('early_out')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biometric_attendances', function (Blueprint $table) {
            //
        });
    }
};
