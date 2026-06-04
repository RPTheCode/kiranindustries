<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Branch;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\Importable;

class DepartmentsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, \Maatwebsite\Excel\Concerns\WithMapping 
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
        return new Department([
            'name' => $row['name'],
            'code' => $row['code'] ?? null,
            'short_code' => $row['short_code'] ?? null,
            'status' => strtolower($row['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
            'sanction_strength' => isset($row['sanction_strength']) && $row['sanction_strength'] !== ''
                ? (int) $row['sanction_strength']
                : null,
            'branch_id' => session('active_branch_id'),
            'created_by' => creatorId(),
        ]);
    }

    public function map($row): array
    {
        $this->currentRow++;
        $row = array_change_key_case($row, CASE_LOWER);

        return [
            'name' => $row['name'] ?? $row['department_name'] ?? $row['department'] ?? null,
            'code' => $row['code'] ?? null,
            'short_code' => $row['short_code'] ?? null,
            'status' => $row['status'] ?? 'active',
            'sanction_strength' => $row['sanction_strength'] ?? $row['sanction'] ?? $row['strength'] ?? null,
        ];
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'short_code' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $exists = Department::where('short_code', $value)
                        ->where('branch_id', session('active_branch_id'))
                        ->whereIn('created_by', getCompanyAndUsersId())
                        ->exists();
                    if ($exists) {
                        $fail(__('A department with this short code already exists in this branch.'));
                    }
                }
            ],
            'status' => 'nullable|in:active,inactive',
            'sanction_strength' => 'nullable|integer|min:0|max:99999',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'name.required' => __('The department name is required.'),
            'short_code.required' => __('The short code is required.'),
            'status.in' => __('The status must be active or inactive.'),
        ];
    }
}
