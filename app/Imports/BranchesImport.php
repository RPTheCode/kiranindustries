<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithValidation;

class BranchesImport implements ToModel, WithHeadingRow, WithValidation, WithMapping, SkipsOnFailure
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
        $creatorId = creatorId();

        // Note: The $row here is already mapped by map() below

        return new Branch([
            'name' => $row['name'],
            'address' => $row['address'] ?? null,
            'city' => $row['city'] ?? null,
            'state' => $row['state'] ?? null,
            'country' => $row['country'] ?? null,
            'zip_code' => $row['zip_code'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'in_charge_name' => $row['branch_in_charge_name'] ?? null,
            'in_charge_contact' => $row['branch_in_charge_contact_number'] ?? null,
            'status' => $row['status'] ?? 'active',
            'created_by' => $creatorId,
        ]);
    }

    public function map($row): array
    {
        $this->currentRow++;
        $row = array_change_key_case($row, CASE_LOWER);

        $name = $row['name'] ?? $row['branch_name'] ?? $row['branch'] ?? null;
        $in_charge_name = $row['branch_in_charge_name'] ?? $row['in_charge_name'] ?? $row['incharge_name'] ?? null;
        $in_charge_contact = $row['branch_in_charge_contact_number'] ?? $row['in_charge_contact'] ?? $row['incharge_contact'] ?? null;

        return [
            'name' => $name,
            'address' => $row['address'] ?? null,
            'city' => $row['city'] ?? null,
            'state' => $row['state'] ?? null,
            'country' => $row['country'] ?? null,
            'zip_code' => $row['zip_code'] ?? $row['pincode'] ?? null,
            'phone' => isset($row['phone']) ? (string) $row['phone'] : null,
            'email' => $row['email'] ?? null,
            'branch_in_charge_name' => $in_charge_name,
            'branch_in_charge_contact_number' => isset($in_charge_contact) ? (string) $in_charge_contact : null,
            'status' => $row['status'] ?? 'active',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $exists = Branch::where('name', $value)
                        ->whereIn('created_by', getCompanyAndUsersId())
                        ->exists();
                    if ($exists) {
                        $fail('A branch with this name already exists.');
                    }
                }
            ],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'branch_in_charge_contact_number' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function headingRow(): int
    {
        return 1;
    }
}
