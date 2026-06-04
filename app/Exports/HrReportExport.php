<?php

namespace App\Exports;

use App\Models\BiometricAttendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HrReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected array $filters;

    protected string $reportId;

    protected int $rowNumber = 0;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
        $this->reportId = (string) ($filters['report_id'] ?? 'biometric');
    }

    public function query(): Builder
    {
        $fromDate = !empty($this->filters['from_date'])
            ? Carbon::parse($this->filters['from_date'])
            : now();
        $toDate = !empty($this->filters['to_date'])
            ? Carbon::parse($this->filters['to_date'])
            : now();

        $branchId = $this->filters['branch_id'] ?? null;
        $reportId = $this->reportId;

        $query = BiometricAttendance::query()
            ->with([
                'employee' => fn ($q) => $q->withoutGlobalScopes(),
                'employee.user:id,name',
                'employee.department:id,name',
                'employee.section:id,name',
                'employee.designation:id,name',
                'employee.branch:id,name',
                'logs' => fn ($q) => $q->select('id', 'biometric_attendance_id', 'punch_type', 'is_manual')->where('is_manual', true),
            ])
            ->whereBetween('attendance_date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);

        $status = $this->filters['status'] ?? null;
        if (!$status || $status === 'all') {
            $status = str_starts_with($reportId, 'att_') ? 'P' : 'all';
        }

        if ($status && $status !== 'all') {
            if ($status === 'P') {
                $query->where(function ($q) {
                    $q->whereIn('status', ['P', 'HD', 'OD', 'CO'])
                        ->orWhere(function ($q2) {
                            $q2->where('status', 'MIS')->whereNotNull('in_time');
                        });
                });
            } elseif ($status === 'overtime') {
                $query->where('ot_minutes', '>', 0);
            } elseif ($status === 'latein') {
                $query->where('late_in', '!=', '0m')->whereNotNull('late_in');
            } elseif ($status === 'earlyout') {
                $query->where('early_out', '!=', '0m')->whereNotNull('early_out');
            } else {
                $query->where('status', $status);
            }
        }

        $query->whereHas('employee', function ($q) use ($branchId) {
            $q->withoutGlobalScopes();
            if ($branchId && $branchId !== 'all') {
                $q->where('branch_id', $branchId);
            }
            if (!empty($this->filters['department']) && $this->filters['department'] !== 'all') {
                $q->where('department_id', $this->filters['department']);
            }
            if (!empty($this->filters['section']) && $this->filters['section'] !== 'all') {
                $q->where('section_id', $this->filters['section']);
            }
            if (!empty($this->filters['category']) && $this->filters['category'] !== 'all') {
                $q->where('category_id', $this->filters['category']);
            }
            if (!empty($this->filters['employee_id']) && $this->filters['employee_id'] !== 'all') {
                $q->where('id', $this->filters['employee_id']);
            } else {
                $q->whereHas('user', fn ($sq) => $sq->where('status', 'active'));
            }
        });

        return $query->orderBy('attendance_date', 'desc')->orderBy('id', 'desc');
    }

    public function headings(): array
    {
        if ($this->reportId === 'biometric_all_punches') {
            return [
                'S.No',
                'Date',
                'Employee Code',
                'Employee Name',
                'Designation',
                'All Punch Details',
                'Lunch Time',
                'Total Hours',
                'Status',
                'Branch',
            ];
        }

        if ($this->usesWorkerBiometricColumns()) {
            return [
                'Code',
                'Name',
                'Department',
                'Section',
                'Shift',
                'Date',
                'In Time',
                'Out Time',
                'Total Hours',
                'OT',
                'Duty',
                'Status',
                'Branch',
            ];
        }

        if ($this->usesAttendanceColumns()) {
            return [
                'Code',
                'Name',
                'Department',
                'Date',
                'In Time',
                'Out Time',
                'Status',
                'Branch',
            ];
        }

        return [
            'Code',
            'Name',
            'Date',
            'In Time',
            'Out Time',
            'Late In',
            'Early Out',
            'Mis-Punch',
            'Branch',
        ];
    }

    public function map($record): array
    {
        $row = $this->formatRecord($record);

        if ($this->reportId === 'biometric_all_punches') {
            $this->rowNumber++;

            return [
                $this->rowNumber,
                $row['date'],
                $row['code'],
                $row['name'],
                $row['designation'],
                $row['log_details'],
                $row['lunch_time'],
                $row['total_hours'],
                $row['status'],
                $row['branch'],
            ];
        }

        if ($this->usesWorkerBiometricColumns()) {
            return [
                $row['code'],
                $row['name'],
                $row['department'],
                $row['section'],
                $row['shift'],
                $row['date'],
                $row['in_time'],
                $row['out_time'],
                $row['total_hours'],
                $row['over_time'],
                $row['duty_value'],
                $row['status'],
                $row['branch'],
            ];
        }

        if ($this->usesAttendanceColumns()) {
            return [
                $row['code'],
                $row['name'],
                $row['department'],
                $row['date'],
                $row['in_time'],
                $row['out_time'],
                $row['status'],
                $row['branch'],
            ];
        }

        return [
            $row['code'],
            $row['name'],
            $row['date'],
            $row['in_time'],
            $row['out_time'],
            $row['late_in'],
            $row['early_out'],
            $row['mis_punch'],
            $row['branch'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    protected function usesWorkerBiometricColumns(): bool
    {
        return str_contains($this->reportId, 'biometric')
            || str_starts_with($this->reportId, 'att_worker');
    }

    protected function usesAttendanceColumns(): bool
    {
        return str_starts_with($this->reportId, 'att_')
            || $this->reportId === 'manual_entries';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRecord(BiometricAttendance $record): array
    {
        $isInManual = false;
        $isOutManual = false;

        if ($record->is_manual) {
            if ($record->logs && $record->logs->isNotEmpty()) {
                $isInManual = $record->logs->where('punch_type', 'IN')->where('is_manual', true)->isNotEmpty();
                $isOutManual = $record->logs->where('punch_type', 'OUT')->where('is_manual', true)->isNotEmpty();
            } else {
                if ($record->status === 'MIS') {
                    $isInManual = $record->in_time && $record->in_count > 0;
                    $isOutManual = $record->out_time && $record->out_count > 0;
                } else {
                    $isInManual = (bool) $record->in_time;
                    $isOutManual = (bool) $record->out_time;
                }
            }
        }

        $inTime = $record->in_time ? Carbon::parse($record->in_time)->format('H:i') : '-';
        $outTime = $record->out_time ? Carbon::parse($record->out_time)->format('H:i') : '-';

        if ($isInManual && $inTime !== '-') {
            $inTime .= ' (M)';
        }
        if ($isOutManual && $outTime !== '-') {
            $outTime .= ' (M)';
        }

        return [
            'code' => $record->employee?->emy_code ?? ($record->employee?->employee_id ?? $record->employee_code ?? '-'),
            'name' => $record->employee?->user?->name ?? 'N/A',
            'department' => $record->employee?->department?->name ?? 'N/A',
            'section' => $record->employee?->section?->name ?? 'N/A',
            'branch' => $record->employee?->branch?->name ?? 'N/A',
            'shift' => $record->shift_code ?: ($record->employee?->shift?->name ?? '-'),
            'date' => Carbon::parse($record->attendance_date)->format('d/m/Y'),
            'in_time' => $inTime,
            'out_time' => $outTime,
            'log_details' => $record->log_details ?? '-',
            'late_in' => ($record->late_in && $record->late_in !== '0m') ? $record->late_in : '-',
            'early_out' => ($record->early_out && $record->early_out !== '0m') ? $record->early_out : '-',
            'status' => $record->status,
            'mis_punch' => ($record->status === 'MIS') ? 'YES' : 'NO',
            'total_hours' => $record->total_minutes
                ? floor($record->total_minutes / 60) . 'h ' . ($record->total_minutes % 60) . 'm'
                : '-',
            'over_time' => ($record->ot_minutes > 0)
                ? floor($record->ot_minutes / 60) . 'h ' . ($record->ot_minutes % 60) . 'm'
                : '-',
            'duty_value' => $record->duty_value ?? '0.0',
            'designation' => $record->employee?->designation?->name ?? '---',
            'lunch_time' => $record->employee?->lunch_time ? $record->employee->lunch_time . ' Min' : '---',
        ];
    }
}
