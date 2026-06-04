<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiometricAttendanceLog extends Model
{
    protected $table = 'biometric_attendance_logs';

    protected $fillable = [
        'biometric_attendance_id',
        'punch_time',
        'punch_type',
        'is_manual',
        'manually_by'
    ];

    protected $casts = [
        'punch_time' => 'datetime',
        'is_manual' => 'boolean',
    ];

    public function biometricAttendance()
    {
        return $this->belongsTo(BiometricAttendance::class, 'biometric_attendance_id');
    }

    public function manualBy()
    {
        return $this->belongsTo(User::class, 'manually_by');
    }
}
