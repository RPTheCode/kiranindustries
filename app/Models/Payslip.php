<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class Payslip extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'payroll_entry_id',
        'employee_id',
        'payslip_number',
        'pay_period_start',
        'pay_period_end',
        'pay_date',
        'file_path',
        'status',
        'salary_status',
        'released_at',
        'sent_at',
        'downloaded_at',
        'created_by'
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'pay_date' => 'date',
        'sent_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Payslip $payslip) {
            $payslip->syncEmployeeIdFromPayrollEntry();
        });
    }

    /**
     * Always align payslip.employee_id with payroll_entries.employee_id (users.id).
     */
    public function syncEmployeeIdFromPayrollEntry(): void
    {
        if (! $this->payroll_entry_id) {
            return;
        }

        $entry = PayrollEntry::withoutGlobalScopes()->find($this->payroll_entry_id);
        if ($entry && (int) $entry->employee_id > 0) {
            $this->employee_id = (int) $entry->employee_id;
        }
    }

    /**
     * Get the payroll entry.
     */
    public function payrollEntry()
    {
        return $this->belongsTo(PayrollEntry::class);
    }

    /**
     * Get the employee.
     */
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id')->withTrashed();
    }

    /**
     * Get the user who created the payslip.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generate payslip number.
     */
    public static function generatePayslipNumber($employeeUserId, $payDate)
    {
        $date = \Carbon\Carbon::parse($payDate);
        $prefix = 'PS-' . $date->format('Ym') . '-';

        $user = User::withoutGlobalScopes()
            ->with(['employee' => fn ($q) => $q->withoutGlobalScopes()])
            ->find($employeeUserId);

        $employeeCode = (string) ($user?->employee?->emy_code
            ?? $user?->employee?->employee_id
            ?? $employeeUserId);
        $employeeCode = trim($employeeCode) !== '' ? $employeeCode : str_pad((string) $employeeUserId, 4, '0', STR_PAD_LEFT);

        $baseNumber = $prefix . $employeeCode;

        // Ensure uniqueness by appending a sequence number if exists
        if (!self::where('payslip_number', $baseNumber)->exists()) {
            return $baseNumber;
        }

        $count = 1;
        while (self::where('payslip_number', "{$baseNumber}-{$count}")->exists()) {
            $count++;
        }

        return "{$baseNumber}-{$count}";
    }

    /**
     * Generate PDF payslip.
     */
    public function generatePDF()
    {
        $this->syncEmployeeIdFromPayrollEntry();
        if ($this->isDirty('employee_id')) {
            $this->saveQuietly();
        }

        $payrollEntry = $this->payrollEntry()->with([
            'employee.employee' => function ($q) {
                $q->withoutGlobalScopes()->with(['shift']);
            },
            'payrollRun'
        ])->first();

        if (!$payrollEntry) {
            throw new \Exception('Payroll entry not found');
        }

        // Fetch daily biometric attendance records for the "Shift Details" section
        $attendanceDetails = \App\Models\BiometricAttendance::with(['slot'])
            ->where('employee_code', $payrollEntry->employee->employee->emy_code ?? '')
            ->whereBetween('attendance_date', [$payrollEntry->payrollRun->pay_period_start, $payrollEntry->payrollRun->pay_period_end])
            ->orderBy('attendance_date', 'asc')
            ->get();

        $empDetail = $payrollEntry->employee->employee;
        $productionEntries = collect();
        if ($empDetail) {
            $productionEntries = \App\Models\DailyProductionAttendanceEntry::with(['materialItem', 'shift'])
                ->where('employee_id', $empDetail->id)
            ->whereBetween('date', [
                $payrollEntry->payrollRun->pay_period_start,
                $payrollEntry->payrollRun->pay_period_end,
            ])
                ->orderBy('date')
                ->get();
        }

        $totalWorkedDays = (float) $payrollEntry->present_days
            + ((float) $payrollEntry->half_days * 0.5)
            + (float) ($payrollEntry->week_off_present_days ?? 0)
            + (float) ($payrollEntry->paid_leave_days ?? 0);

        $data = [
            'payslip' => $this,
            'payrollEntry' => $payrollEntry,
            'employee' => $payrollEntry->employee,
            'employeeDetail' => $empDetail,
            'payrollRun' => $payrollEntry->payrollRun,
            'earnings' => $payrollEntry->earnings_breakdown ?? [],
            'deductions' => $payrollEntry->deductions_breakdown ?? [],
            'attendanceDetails' => $attendanceDetails,
            'attendanceLog' => $attendanceDetails->filter(fn ($r) => ($r->punch_count ?? 0) > 0),
            'productionEntries' => $productionEntries,
            'totalWorkedDays' => $totalWorkedDays,
            'themeColor' => brandThemeColor($this->created_by),
            'currencySymbol' => pdfCurrencySymbol($this->created_by),
            'companyName' => config('app.name', 'Kiran Industries'),
            'logoUrl' => getSetting('logoLight') ? asset('storage/' . getSetting('logoLight')) : null,
        ];

        $pdf = Pdf::loadView('payslips.template', $data);

        $fileName = 'payslip-' . $this->payslip_number . '.pdf';
        $filePath = 'payslips/' . $fileName;

        Storage::disk('public')->put($filePath, $pdf->output());

        $this->update(['file_path' => $filePath]);

        return $filePath;
    }

    /**
     * Get download URL.
     */
    public function getDownloadUrlAttribute()
    {
        if ($this->file_path) {
            return Storage::disk('public')->url($this->file_path);
        }

        return null;
    }

    /**
     * Mark as downloaded.
     */
    public function markAsDownloaded()
    {
        $this->update([
            'status' => 'downloaded',
            'downloaded_at' => now(),
        ]);
    }

    /**
     * Mark as sent.
     */
    public function markAsSent()
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}