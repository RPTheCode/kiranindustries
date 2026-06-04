<?php

namespace App\Imports;

use App\Models\Skill;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithMapping;

class SkillsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithMapping
{
    use SkipsFailures;

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
        return new Skill([
            'name' => $row['name'],
            'code' => $row['code'],
            'created_by' => creatorId(),
            'status' => strtolower($row['status'] ?? 'active') === 'active' ? 1 : 0,
        ]);
    }

    public function map($row): array
    {
        $this->currentRow++;
        $row = array_change_key_case($row, CASE_LOWER);

        $name = $row['name'] ?? $row['skill_name'] ?? $row['skill'] ?? null;
        $code = $row['code'] ?? $row['short_code'] ?? null;
        $status = $row['status'] ?? 'active';

        return [
            'name' => $name,
            'code' => $code,
            'status' => $status,
        ];
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $exists = Skill::where('code', $value)
                        ->where('created_by', creatorId())
                        ->exists();
                    if ($exists) {
                        $fail('A skill with this short code already exists.');
                    }
                }
            ],
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
