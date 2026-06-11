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
        'source',
        'latitude',
        'longitude',
        'manually_by',
    ];

    protected $casts = [
        'punch_time' => 'datetime',
        'is_manual' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
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
