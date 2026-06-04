<?php

namespace App\Imports;

use App\Models\Designation;
use App\Models\Department;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithMapping;

class DesignationsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithMapping 
{
    use Importable, SkipsFailures;

    public $rowsSaved = 0;
    public $savedNumbers = [];
    private $currentRow = 1;

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row) 
    {
        $this->rowsSaved++;
        $this->savedNumbers[] = $this->currentRow;
        
        return new Designation([
            'name' => $row['name'],
            'code' => $row['code'],
            'rate' => $row['rate'] ?? 0,
            'department_id' => $row['department_id'],
            'status' => strtolower($row['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
            'created_by' => creatorId(),
        ]);
    }

    public function map($row): array
    {
        $this->currentRow++;
        $row = array_change_key_case($row, CASE_LOWER);

        // Map from desgnrpt.xls format:
        // Column 0 (Code) -> Department Name
        // Column 1 (Full Name) -> Designation Name
        // Column 2 (Rate) -> Rate

        $designationName = $row['full_name'] ?? ($row['name'] ?? null);
        $deptName = $row['code'] ?? ($row['department'] ?? null);
        $rate = $row['rate'] ?? 0;

        $deptId = null;
        if (!empty($deptName)) {
            $dept = Department::firstOrCreate(
                ['name' => trim($deptName), 'branch_id' => session('active_branch_id')],
                ['created_by' => creatorId()]
            );
            $deptId = $dept->id;
        }

        return [
            'name' => $designationName,
            'code' => $designationName,
            'rate' => $rate,
            'department_id' => $deptId,
            'status' => $row['status'] ?? 'active',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'department_id' => 'required',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function headingRow(): int
    {
        return 2;
    }

    public function customValidationMessages()
    {
        return [
            'name.required' => __('The designation name is required.'),
            'department_id.required' => __('A valid department is required.'),
        ];
    }
}
