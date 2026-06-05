<?php

use App\Database\Migrations\Concerns\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->addIndexIfMissing('biometric_attendances', ['branch_id'], 'bio_att_branch_id_idx');
        $this->addIndexIfMissing('biometric_attendances', ['status'], 'bio_att_status_idx');
        $this->addIndexIfMissing('biometric_attendances', ['attendance_date', 'branch_id'], 'bio_att_date_branch_idx');
        $this->addIndexIfMissing('biometric_attendances', ['attendance_date', 'status'], 'bio_att_date_status_idx');
        $this->addIndexIfMissing('biometric_attendances', ['branch_id', 'status', 'attendance_date'], 'bio_att_branch_status_date_idx');

        $this->addIndexIfMissing('biometric_attendance_logs', ['biometric_attendance_id'], 'bio_att_logs_attendance_id_idx');
        $this->addIndexIfMissing('biometric_attendance_logs', ['punch_time'], 'bio_att_logs_punch_time_idx');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('biometric_attendances', 'bio_att_branch_id_idx');
        $this->dropIndexIfExists('biometric_attendances', 'bio_att_status_idx');
        $this->dropIndexIfExists('biometric_attendances', 'bio_att_date_branch_idx');
        $this->dropIndexIfExists('biometric_attendances', 'bio_att_date_status_idx');
        $this->dropIndexIfExists('biometric_attendances', 'bio_att_branch_status_date_idx');
        $this->dropIndexIfExists('biometric_attendance_logs', 'bio_att_logs_attendance_id_idx');
        $this->dropIndexIfExists('biometric_attendance_logs', 'bio_att_logs_punch_time_idx');
    }
};
