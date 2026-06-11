<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'attendance_mode')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('attendance_mode', 20)->default('both')->after('shift_id');
            });
        }

        if (Schema::hasTable('biometric_attendances')) {
            Schema::table('biometric_attendances', function (Blueprint $table) {
                if (! Schema::hasColumn('biometric_attendances', 'clock_in_latitude')) {
                    $table->decimal('clock_in_latitude', 10, 7)->nullable()->after('out_time');
                    $table->decimal('clock_in_longitude', 10, 7)->nullable()->after('clock_in_latitude');
                    $table->decimal('clock_out_latitude', 10, 7)->nullable()->after('clock_in_longitude');
                    $table->decimal('clock_out_longitude', 10, 7)->nullable()->after('clock_out_latitude');
                }
            });
        }

        if (Schema::hasTable('biometric_attendance_logs')) {
            Schema::table('biometric_attendance_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('biometric_attendance_logs', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('source');
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('biometric_attendance_logs')) {
            Schema::table('biometric_attendance_logs', function (Blueprint $table) {
                if (Schema::hasColumn('biometric_attendance_logs', 'latitude')) {
                    $table->dropColumn(['latitude', 'longitude']);
                }
            });
        }

        if (Schema::hasTable('biometric_attendances')) {
            Schema::table('biometric_attendances', function (Blueprint $table) {
                if (Schema::hasColumn('biometric_attendances', 'clock_in_latitude')) {
                    $table->dropColumn([
                        'clock_in_latitude',
                        'clock_in_longitude',
                        'clock_out_latitude',
                        'clock_out_longitude',
                    ]);
                }
            });
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'attendance_mode')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('attendance_mode');
            });
        }
    }
};
