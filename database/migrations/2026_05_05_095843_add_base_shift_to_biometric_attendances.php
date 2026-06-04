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
            $table->string('base_shift')->nullable()->after('attendance_date');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_attendances', function (Blueprint $table) {
            $table->dropColumn('base_shift');
        });
    }
};
