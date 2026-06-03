<?php

namespace App\Imports;

use App\Models\EmployeeAdvance;
use App\Models\Employee;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;

class EmployeeAdvanceImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, \Maatwebsite\Excel\Concerns\WithMapping
{
    use Importable, SkipsFailures;

    public $rowsSaved = 0;
    public $savedNumbers = [];
    private $currentRow = 1; // Heading is row 1

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $this->rowsSaved++;
        $this->savedNumbers[] = $this->currentRow;
        $employeeId = $row['employee_id'];

        // Map KIRANxxx to user_id - searching across ALL branches
        $employee = Employee::withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$employee) {
            return null; // Should be handled by validation, but safe to check
        }

        $activeBranchId = session('active_branch_id');
        $branchId = ($activeBranchId && $activeBranchId !== 'all') ? $activeBranchId : null;

        return new EmployeeAdvance([
            'employee_id' => $employee->user_id,
            'amount' => $row['amount'],
            'pay_date' => \Carbon\Carbon::parse($row['pay_date']),
            'remarks' => $row['remarks'] ?? null,
            'status' => 'paid',
            'created_by' => creatorId(),
            'branch_id' => $branchId,
        ]);
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $exists = Employee::withoutGlobalScopes()
                        ->where('employee_id', $value)
                        ->whereIn('created_by', getCompanyAndUsersId())
                        ->exists();
                    if (!$exists) {
                        $fail(__('Employee ID :id not found.', ['id' => $value]));
                    }
                }
            ],
            'amount' => 'required|numeric|min:1',
            'pay_date' => 'required|date',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'employee_id.required' => __('The employee ID is required.'),
            'amount.required' => __('The amount is required.'),
            'amount.numeric' => __('The amount must be a number.'),
            'pay_date.required' => __('The pay date is required.'),
            'pay_date.date' => __('The pay date must be a valid date.'),
        ];
    }
    public function map($row): array
    {
        $this->currentRow++;
        return $row;
    }
}
