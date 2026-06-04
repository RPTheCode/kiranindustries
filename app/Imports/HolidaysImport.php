<?php

namespace App\Imports;

use App\Models\Holiday;
use App\Models\Branch; // Import Branch model
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Validation\Rule;

class HolidaysImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, \Maatwebsite\Excel\Concerns\WithMapping
{
    use SkipsFailures;

    public $rowsSaved = 0;
    public $savedNumbers = [];
    private $currentRow = 1; // Heading is row 1
    protected $branchScope;
    protected $selectedBranches;

    public $customFailures = [];

    public function __construct($branchScope = 'current', $selectedBranches = [])
    {
        $this->branchScope = $branchScope;
        $this->selectedBranches = $selectedBranches;
    }

    private function transformDate($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return $value;
        }
    }

    private function transformBoolean($value)
    {
        if (empty($value)) {
            return false;
        }

        $value = strtolower(trim($value));
        return $value === 'yes' || $value === 'true' || $value === '1';
    }

    public function model(array $row) 
    {
        $creatorId = creatorId();
        
        $startDate = $this->transformDate($row['start_date']);
        $endDate = $this->transformDate($row['end_date']);

        if (empty($endDate) && !empty($startDate)) {
            $endDate = $startDate;
        }

        $targetBranchIds = [];
        if ($this->branchScope === 'all') {
            $targetBranchIds = Branch::where('created_by', $creatorId)->pluck('id')->toArray();
        } elseif ($this->branchScope === 'selected' && !empty($this->selectedBranches)) {
            $targetBranchIds = $this->selectedBranches;
        } else {
            $activeBranchId = session('active_branch_id');
            if ($activeBranchId) {
                $targetBranchIds = [$activeBranchId];
            }
        }

        if (!empty($targetBranchIds) && $startDate) {
            $overlapping = Holiday::whereIn('created_by', getCompanyAndUsersId())
                ->whereHas('branches', function ($q) use ($targetBranchIds) {
                    $q->whereIn('branches.id', $targetBranchIds);
                })
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->where('start_date', '<=', $endDate)
                      ->where(function ($sub) use ($startDate) {
                          $sub->where('end_date', '>=', $startDate)
                              ->orWhere(function ($nullEnd) use ($startDate) {
                                  $nullEnd->whereNull('end_date')
                                          ->where('start_date', '>=', $startDate);
                              });
                      });
                })
                ->exists();

            if ($overlapping) {
                // $this->currentRow is one ahead because it increments in map() for the *next* row, 
                // wait, if map() runs, it increments. So it represents current row perfectly.
                $this->customFailures[] = "Row {$this->currentRow}: A holiday already exists in this date range for the selected branch(es).";
                return null;
            }
        }

        $this->rowsSaved++;
        $this->savedNumbers[] = $this->currentRow;

        $holiday = Holiday::create([
            'name' => $row['name'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'category' => strtolower(trim($row['category'])),
            'description' => $row['description'] ?? null,
            'is_recurring' => $this->transformBoolean($row['is_recurring'] ?? null),
            'is_paid' => $this->transformBoolean($row['is_paid'] ?? null),
            'is_half_day' => $this->transformBoolean($row['is_half_day'] ?? null),
            'created_by' => $creatorId,
        ]);

        if (!empty($targetBranchIds)) {
            $holiday->branches()->sync($targetBranchIds);
        }

        return $holiday;
    }

    public function map($row): array
    {
        $this->currentRow++;
        return [
            'name' => $row['name'] ?? $row['holiday_name'] ?? $row['occassion'] ?? $row['occasion'] ?? null,
            'start_date' => $row['start_date'] ?? $row['date'] ?? null,
            'end_date' => $row['end_date'] ?? null,
            'category' => $row['category'] ?? 'national',
            'description' => $row['description'] ?? null,
            'is_recurring' => $row['is_recurring'] ?? 'no',
            'is_paid' => $row['is_paid'] ?? 'yes',
            'is_half_day' => $row['is_half_day'] ?? 'no',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => 'required',
            'start_date' => 'required',
            'category' => [
                'required',
                Rule::in(['national', 'religious', 'company-specific', 'regional']),
            ],
        ];
    }
}
