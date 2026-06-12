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
        $query = EsslLog::query()
            ->select([
                'essl_logs.id',
                'essl_logs.device_log_id',
                'essl_logs.user_id',
                'essl_logs.log_date',
                'essl_logs.direction',
                'essl_logs.device_id',
                'essl_logs.body_temperature',
                'essl_logs.is_mask_on',
            ])
            ->join('users', 'essl_logs.user_id', '=', 'users.id')
            ->where('users.status', 'active')
            ->with([
                'user:id,name',
                'user.employee' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'user_id', 'employee_id', 'emy_code', 'branch_id'),
                'user.employee.branch:id,name',
            ]);

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

        return $query->orderByDesc('essl_logs.log_date')->limit(50000)->get();
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
