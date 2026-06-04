<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiometricAttendance extends Model
{
    protected $fillable = [
        'employee_id',
        'employee_code',
        'department_id',
        'branch_id',
        'category_id',
        'section_id',
        'attendance_date',
        'shift_code',
        'base_shift',
        'in_time',
        'out_time',
        'in_count',
        'out_count',
        'punch_count',
        'total_minutes',
        'late_in',
        'early_out',
        'ot_minutes',
        'duty_value',
        'status',
        'is_holiday',
        'is_weekly_off',
        'is_manual',
        'manual_by',
        'manual_remarks',
        'remarks',
        'shift_slot_id',
        'log_details',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'in_time' => 'datetime',
        'out_time' => 'datetime',
        'duty_value' => 'decimal:1',
        'is_holiday' => 'boolean',
        'is_weekly_off' => 'boolean',
        'is_manual' => 'boolean',
    ];

    protected $appends = ['ot_hours'];

    public function getOtHoursAttribute()
    {
        $minutes = $this->ot_minutes;
        if (!$minutes || $minutes <= 0)
            return '0m';
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)
            ->withoutGlobalScopes()
            ->withTrashed();
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function slot()
    {
        return $this->belongsTo(ShiftSlot::class, 'shift_slot_id');
    }

    public function manualUser()
    {
        return $this->belongsTo(User::class, 'manual_by');
    }

    public function logs()
    {
        return $this->hasMany(BiometricAttendanceLog::class, 'biometric_attendance_id');
    }

    public function getLogDetailsAttribute($value)
    {
        $logs = $this->relationLoaded('logs')
            ? $this->logs->sortBy('punch_time')->values()
            : $this->logs()->orderBy('punch_time', 'asc')->get();

        if ($logs->isNotEmpty()) {
            $fromLogs = $logs->map(function ($log) {
                $timeStr = \Carbon\Carbon::parse($log->punch_time)->format('H:i');
                $manualStr = $log->is_manual ? ' (M)' : '';
                return "{$timeStr} {$log->punch_type}{$manualStr}";
            })->join(', ');

            if ($fromLogs !== '') {
                return $fromLogs;
            }
        }

        return is_string($value) ? trim($value) : '';
    }

    protected static function booted()
    {
        static::saved(function ($attendance) {
            static $syncing = false;
            if ($syncing) return;
            $syncing = true;
 
            try {
                // Get the raw log_details attribute supplied to the model
                $logDetailsStr = $attendance->getAttributes()['log_details'] ?? '';

                // Fetch previous logs to preserve their manual status if punches are unmodified
                $previousLogs = $attendance->logs()->get();
                $wasInManual = $previousLogs->where('punch_type', 'IN')->where('is_manual', true)->isNotEmpty();
                $wasOutManual = $previousLogs->where('punch_type', 'OUT')->where('is_manual', true)->isNotEmpty();
 
                // Delete existing logs for this attendance record to keep it clean
                $attendance->logs()->delete();
 
                if (!empty($logDetailsStr)) {
                    $attendanceDate = \Carbon\Carbon::parse($attendance->attendance_date)->startOfDay();
 
                    // Fetch existing biometric logs (from essl_logs) for this employee around the attendance date to differentiate
                    $biometricLogs = collect();
                    $employee = $attendance->employee;
                    if ($employee && $employee->user_id) {
                        $biometricLogs = \Illuminate\Support\Facades\DB::table('essl_logs')
                             ->where('user_id', $employee->user_id)
                             ->whereBetween('log_date', [
                                 $attendanceDate->copy()->subDay()->startOfDay(),
                                 $attendanceDate->copy()->addDays(2)->endOfDay()
                             ])
                             ->get()
                             ->map(function ($log) {
                                 return \Carbon\Carbon::parse($log->log_date)->format('Y-m-d H:i');
                             });
                    }
 
                    $punches = explode(',', $logDetailsStr);
                    $previousMinutes = -1;
                    $daysToAdd = 0;
 
                    foreach ($punches as $punch) {
                        $punch = trim($punch);
                        if (empty($punch)) continue;
 
                        $parts = explode(' ', $punch);
                        if (count($parts) >= 2) {
                            $timeStr = trim($parts[0]);
                            $typeStr = strtoupper(trim($parts[1])); // 'IN' or 'OUT'
 
                            $timeParts = explode(':', $timeStr);
                            $currentMinutes = (int)($timeParts[0] ?? 0) * 60 + (int)($timeParts[1] ?? 0);
 
                            // Detect midnight cross-over chronologically
                            if ($previousMinutes !== -1 && $currentMinutes < $previousMinutes) {
                                 $daysToAdd++;
                            }
                            $previousMinutes = $currentMinutes;
 
                            $punchDatetime = $attendanceDate->copy()
                                 ->addDays($daysToAdd)
                                 ->setTime($timeParts[0] ?? 0, $timeParts[1] ?? 0, 0);
 
                            $punchFormatStr = $punchDatetime->format('Y-m-d H:i');
                            $isPunchBiometric = $biometricLogs->contains($punchFormatStr);
 
                            $isManualPunch = false;
                            if ($attendance->is_manual) {
                                 if ($typeStr === 'IN') {
                                     $inChanged = false;
                                     $originalIn = $attendance->getOriginal('in_time');
                                     if ($attendance->in_time) {
                                         if (!$originalIn) {
                                             $inChanged = true;
                                         } else {
                                             $origInCarbon = \Carbon\Carbon::parse($originalIn);
                                             $inChanged = $attendance->in_time->format('Y-m-d H:i') !== $origInCarbon->format('Y-m-d H:i');
                                         }
                                     } else {
                                         $inChanged = $originalIn ? true : false;
                                     }
                                     $isManualPunch = ($attendance->wasRecentlyCreated || $inChanged) ? true : $wasInManual;
                                 } elseif ($typeStr === 'OUT') {
                                     $outChanged = false;
                                     $originalOut = $attendance->getOriginal('out_time');
                                     if ($attendance->out_time) {
                                         if (!$originalOut) {
                                             $outChanged = true;
                                         } else {
                                             $origOutCarbon = \Carbon\Carbon::parse($originalOut);
                                             $outChanged = $attendance->out_time->format('Y-m-d H:i') !== $origOutCarbon->format('Y-m-d H:i');
                                         }
                                     } else {
                                         $outChanged = $originalOut ? true : false;
                                     }
                                     $isManualPunch = ($attendance->wasRecentlyCreated || $outChanged) ? true : $wasOutManual;
                                 } else {
                                     $isManualPunch = !$isPunchBiometric;
                                 }
                            } else {
                                 $isManualPunch = !$isPunchBiometric;
                            }

                            BiometricAttendanceLog::withoutEvents(function () use ($attendance, $punchDatetime, $typeStr, $isManualPunch) {
                                $attendance->logs()->create([
                                    'punch_time' => $punchDatetime,
                                    'punch_type' => $typeStr,
                                    'is_manual' => $isManualPunch,
                                    'manually_by' => $isManualPunch ? $attendance->manual_by : null,
                                ]);
                            });
                        }
                    }
                }
            } finally {
                $syncing = false;
            }
        });
    }
}
