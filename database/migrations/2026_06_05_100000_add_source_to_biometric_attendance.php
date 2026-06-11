<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_attendances', function (Blueprint $table) {
            $table->string('primary_source', 20)->nullable()->after('is_manual');
        });

        Schema::table('biometric_attendance_logs', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('is_manual');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_attendance_logs', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('biometric_attendances', function (Blueprint $table) {
            $table->dropColumn('primary_source');
        });
    }
};
