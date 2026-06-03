<?php

namespace App\Exports;

use App\Models\EsslLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EsslLogExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = EsslLog::select('essl_logs.*')
            ->join('users', 'essl_logs.user_id', '=', 'users.id')
            ->where('users.status', 'active')
            ->with(['user.employee.branch']);

        $needsEmployeeJoin = (!empty($this->filters['branch_id']) && $this->filters['branch_id'] !== 'all') || 
                             (!empty($this->filters['category_id']) && $this->filters['category_id'] !== 'all');

        if ($needsEmployeeJoin) {
            $query->join('employees as emp_filter', 'users.id', '=', 'emp_filter.user_id');
            
            if (!empty($this->filters['branch_id']) && $this->filters['branch_id'] !== 'all') {
                $query->where('emp_filter.branch_id', $this->filters['branch_id']);
            }

            if (!empty($this->filters['category_id']) && $this->filters['category_id'] !== 'all') {
                $query->where('emp_filter.category_id', $this->filters['category_id']);
            }
        }

        if (!empty($this->filters['date_from'])) {
            $query->where('log_date', '>=', $this->filters['date_from'] . ' 00:00:00');
        }

        if (!empty($this->filters['date_to'])) {
            $query->where('log_date', '<=', $this->filters['date_to'] . ' 23:59:59');
        }

        if (!empty($this->filters['employee_id']) && $this->filters['employee_id'] !== 'all') {
            $query->where('essl_logs.user_id', $this->filters['employee_id']);
        }

        if (isset($this->filters['direction']) && $this->filters['direction'] !== 'all') {
            $query->where('direction', $this->filters['direction']);
        }

        return $query->orderBy('log_date', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Log ID',
            'Employee Name',
            'Employee Code',
            'Branch',
            'Date & Time',
            'Direction',
            'Device ID',
            'Temperature',
            'Mask Status'
        ];
    }

    public function map($log): array
    {
        $direction = 'N/A';
        $dir = strtolower($log->direction);
        if ($dir == '0' || $dir == 'in') {
            $direction = 'IN';
        } elseif ($dir == '1' || $dir == 'out') {
            $direction = 'OUT';
        }

        return [
            $log->device_log_id,
            $log->user->name ?? 'N/A',
            $log->user->employee->employee_id ?? 'N/A',
            $log->user->employee->branch->name ?? 'N/A',
            $log->log_date,
            $direction,
            $log->device_id,
            $log->body_temperature ?? '-',
            $log->is_mask_on === null ? '-' : ($log->is_mask_on ? 'Mask On' : 'No Mask')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
