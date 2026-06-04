<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_attendances', function (Blueprint $table) {
            $table->index('branch_id', 'bio_att_branch_id_idx');
            $table->index('status', 'bio_att_status_idx');
            $table->index(['attendance_date', 'branch_id'], 'bio_att_date_branch_idx');
            $table->index(['attendance_date', 'status'], 'bio_att_date_status_idx');
            $table->index(['branch_id', 'status', 'attendance_date'], 'bio_att_branch_status_date_idx');
        });

        if (Schema::hasTable('biometric_attendance_logs')) {
            Schema::table('biometric_attendance_logs', function (Blueprint $table) {
                $table->index('biometric_attendance_id', 'bio_att_logs_attendance_id_idx');
                $table->index('punch_time', 'bio_att_logs_punch_time_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('biometric_attendances', function (Blueprint $table) {
            $table->dropIndex('bio_att_branch_id_idx');
            $table->dropIndex('bio_att_status_idx');
            $table->dropIndex('bio_att_date_branch_idx');
            $table->dropIndex('bio_att_date_status_idx');
            $table->dropIndex('bio_att_branch_status_date_idx');
        });

        if (Schema::hasTable('biometric_attendance_logs')) {
            Schema::table('biometric_attendance_logs', function (Blueprint $table) {
                $table->dropIndex('bio_att_logs_attendance_id_idx');
                $table->dropIndex('bio_att_logs_punch_time_idx');
            });
        }
    }
};
