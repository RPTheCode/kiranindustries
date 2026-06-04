<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'report_name',
        'file_path',
        'status',
        'progress',
        'error_message',
        'filters'
    ];

    protected $casts = [
        'filters' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generateReadableName($request)
    {
        $reportId = $request->get('report_id');
        $reportType = $request->get('report_type');

        $labels = [
            'biometric_dedicated' => 'Biometric Attendance Report',
            'biometric_dedicated_code' => 'Biometric Codewise Report',
            'biometric_dedicated_dept' => 'Biometric Departmentwise Report',
            'biometric_single' => 'Daily Present Report',
            'mispunch_dedicated' => 'MisPunch Report',
            'mispunch_dedicated_code' => 'MisPunch Codewise Report',
            'mispunch_dedicated_dept' => 'MisPunch Departmentwise Report',
            'manual_entries' => 'Manual Entries Report',
            'att_worker' => 'Workerwise Attendance Report',
            'att_dept' => 'Departmentwise Attendance Report',
            'att_shift' => 'Shiftwise Attendance Report',
            'att_summary' => 'Attendance Summary Report',
            'emp_monthly' => 'Employee Monthly Report',
            'monthly_production' => 'Monthly Production Report',
            'monthly_earning_deduction' => 'Monthly Earning & Deduction Report',
            'monthly_earning' => 'Monthly Earning Report',
            'monthly_deduction_payroll' => 'Monthly Deduction Report',
            'monthly_payroll_summary' => 'Monthly Payroll Summary',
            'deduction' => 'Deduction Report',
            'incentive' => 'Incentive Report',
            'production' => 'Production Report',
            'bank_transfer' => 'Bank Transfer Register',
            'loan_ledger' => 'Loan Ledger Report',
            'nominee_register' => 'Nominee Register',
            'salary_ctc' => 'Salary CTC Report',
        ];

        $name = $labels[$reportId] ?? ucwords(str_replace('_', ' ', $reportId ?? 'report'));

        if ($reportId === 'biometric_dedicated' && $reportType) {
            $name = 'Biometric ' . ucfirst($reportType) . ' Report';
        }

        if ($reportId === 'mispunch_dedicated' && $reportType) {
            $name = 'MisPunch ' . ucfirst($reportType) . ' Report';
        }

        $dateRange = '';
        if ($reportId === 'biometric_single') {
            if ($request->filled('from_date')) {
                $dateRange = ' (' . \Carbon\Carbon::parse($request->get('from_date'))->format('d M Y') . ')';
            } else {
                $dateRange = ' (' . now()->format('d M Y') . ')';
            }
        } elseif ($request->filled('from_date')) {
            $from = \Carbon\Carbon::parse($request->get('from_date'))->format('d M Y');
            if ($request->filled('to_date')) {
                $to = \Carbon\Carbon::parse($request->get('to_date'))->format('d M Y');
                $dateRange = $from === $to ? " ($from)" : " ($from to $to)";
            } else {
                $dateRange = " ($from)";
            }
        }

        return $name . $dateRange;
    }
}
