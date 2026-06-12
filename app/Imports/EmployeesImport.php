<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\User;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Section;
use App\Models\Category;
use App\Models\Shift;
use App\Models\Skill;
use App\Models\EmployeeSalary;
use App\Models\SalaryComponent;
use App\Models\BankMaster;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class EmployeesImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, \Maatwebsite\Excel\Concerns\WithMapping, WithEvents
{
    use SkipsFailures;

    public $rowsSaved = 0;

    public $savedNumbers = [];

    /** @var array<int, array{row: int, emp_code: string, name: string, reason: string}> */
    public array $skippedRows = [];

    private $currentRow = 1; // Heading is row 1

    private $latestIdNumber = null;

    private bool $structureValidated = false;



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

    public function model(array $row)
    {
        $creatorId = creatorId() ?: 1;

        // 1. Determine Branch from Sheet if available (checking 'branch' or 'place' column), fallback to session
        $branchName = trim($row['branch'] ?? ($row['branch_name'] ?? ($row['place'] ?? '')));
        $activeBranchId = null;

        if (! empty($branchName)) {
            $branch = Branch::withoutGlobalScopes()
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeMasterName($branchName)])
                ->first();

            if ($branch) {
                $activeBranchId = $branch->id;
            }
        } elseif (! $activeBranchId) {
            $activeBranchId = session('active_branch_id');
        }

        try {
            // Precise Mapping from CSV Headers:
            // SECTION,CATEGORY,EMP.CODE, O / P,SHIFT,UAN NO,GENDER,STATUS,EMP.SKILL,DEPARTMENT,DESIGNATION,EMP.NAME,FATHER.NAME,PLACE,LOCAL ADDRESS,LOCAL CITY,LOCAL PINCODE,STATE,PERM.ADDRESS,EMP.EMAILS,PHONE NO 1,PHONE NO 2,ITAX NO.,DRIVING LIC.,ELEC.CARD,AADHAR CARD,BRITH DT.,JOINING DT.,CONFIRM DT.,EDUCATION,EXPERINCE,PF.FLAG,PF.NO.,ESIC FLAG,ESIC NO.,DAILY FLAG,PTAX FLAG,HOD FLAG,BONUS FLAG,OT FLAG,OT HOURS,BANK NAME,BANK TYPE,BANK IFSC,BANK A/C.,GROSS AMT,BASIC,H.R.A.,CONVEY,ALLOWANCE,MEDICAL,EDUCATION,DAYS,WEEK OFF,LUNCH TIME

            $sectionName = trim($row['section'] ?? '');
            $categoryName = trim($row['category'] ?? '');
            $empCode = trim($row['emp_code'] ?? ($row['empcode'] ?? ($row['employee_code'] ?? ($row['emp_no'] ?? ''))));
            $opStatus = trim($row['o_p'] ?? ''); // Map " O / P"

            // Flexible shift name extraction
            $shiftName = trim($row['shift'] ?? '');
            if (empty($shiftName)) {
                // Try to find a key that looks like 'shift'
                foreach ($row as $key => $val) {
                    if (str_contains(strtolower($key), 'shift')) {
                        $shiftName = trim($val);
                        break;
                    }
                }
            }
            $uanNo = trim($row['uan_no'] ?? '');
            $gender = strtolower(trim($row['gender'] ?? 'male'));
            $status = trim($row['status'] ?? '');

            // Flexible skill data extraction
            $skillData = '';
            foreach ($row as $key => $val) {
                $cleanKey = strtolower(str_replace([' ', '.', '_'], '', $key));
                if ($cleanKey === 'empskill' || $cleanKey === 'skill' || $cleanKey === 'employeeskill') {
                    $skillData = trim($val);
                    break;
                }
            }
            if (empty($skillData)) {
                $skillData = trim($row['emp_skill'] ?? '');
            }

            $departmentName = trim($row['department'] ?? '');
            $designationName = trim($row['designation'] ?? '');
            $empName = trim($row['empname'] ?? 'Unknown');
            $fatherName = trim($row['fathername'] ?? '');
            $place = trim($row['place'] ?? '');
            $localAddress = trim($row['local_address'] ?? '');
            $localCity = trim($row['local_city'] ?? '');
            $localPincode = trim($row['local_pincode'] ?? '');
            $state = trim($row['state'] ?? '');
            $permAddress = trim($row['permaddress'] ?? '');
            $emails = trim($row['empemails'] ?? '');
            $phone1 = trim($row['phone_no_1'] ?? '');
            $phone2 = trim($row['phone_no_2'] ?? '');
            $itaxNo = trim($row['itax_no'] ?? ''); // PAN
            $drivingLic = trim($row['driving_lic'] ?? '');
            $elecCard = trim($row['eleccard'] ?? '');
            $aadharCard = trim($row['aadhar_card'] ?? '');
            $birthDt = $this->transformDate($row['brith_dt'] ?? null);
            $joiningDt = $this->transformDate($row['joining_dt'] ?? null);
            $confirmDt = $this->transformDate($row['confirm_dt'] ?? null);
            $education = trim($row['education'] ?? '');
            $experience = trim($row['experince'] ?? '');

            $pfFlag = (strtoupper($row['pfflag'] ?? ($row['pf_flag'] ?? '')) === 'YES' || strtoupper($row['pfflag'] ?? ($row['pf_flag'] ?? '')) === 'Y');
            $pfNo = trim($row['pf_no'] ?? ($row['pfno'] ?? ($row['pf_number'] ?? '')));
            $esicFlag = (strtoupper($row['esicflag'] ?? ($row['esic_flag'] ?? '')) === 'YES' || strtoupper($row['esicflag'] ?? ($row['esic_flag'] ?? '')) === 'Y');
            $esicNo = trim($row['esic_no'] ?? ($row['esic_number'] ?? ($row['esicno'] ?? '')));
            $dailyFlag = (strtoupper($row['daily_flag'] ?? '') === 'YES' || strtoupper($row['daily_flag'] ?? '') === 'Y');
            $ptaxFlag = (strtoupper($row['ptax_flag'] ?? '') === 'YES' || strtoupper($row['ptax_flag'] ?? '') === 'Y');
            $hodFlag = (strtoupper($row['hod_flag'] ?? '') === 'YES' || strtoupper($row['hod_flag'] ?? '') === 'Y');
            $bonusFlag = (strtoupper($row['bonus_flag'] ?? '') === 'YES' || strtoupper($row['bonus_flag'] ?? '') === 'Y' || ($row['bonus_flag'] ?? '') == 1);
            $otFlag = (strtoupper($row['ot_flag'] ?? '') === 'YES' || strtoupper($row['ot_flag'] ?? '') === 'Y' || ($row['ot_flag'] ?? '') == 1);
            $otHours = floatval($row['ot_hours'] ?? 0);
            $otType = trim($row['ot_type'] ?? '');

            $bankName = '';
            $bankType = '';
            $bankIfsc = '';
            $bankAc = '';

            foreach ($row as $key => $val) {
                $cleanKey = strtolower(str_replace([' ', '.', '_'], '', $key));
                if ($cleanKey === 'bankname') {
                    $bankName = trim($val);
                } elseif ($cleanKey === 'banktype') {
                    $bankType = trim($val);
                } elseif ($cleanKey === 'bankifsc' || $cleanKey === 'ifsccode' || $cleanKey === 'ifsc') {
                    $bankIfsc = trim($val);
                } elseif ($cleanKey === 'bankac' || $cleanKey === 'accountnumber' || $cleanKey === 'bankaccount') {
                    $bankAc = trim($val);
                }
            }

            // Flexible Salary Extraction
            $grossAmt = 0;
            $basic = 0;
            $hra = 0;
            $convey = 0;
            $allowance = 0;
            $medical = 0;
            $educationAllowance = 0;
            $pfBasic = 0;
            $lta = 0;
            $mediclaim = 0;
            $componentValues = []; // Store name => value for dynamic assignment

            foreach ($row as $key => $val) {
                $cleanKey = strtolower(str_replace([' ', '.', '_'], '', $key));
                $val = floatval(str_replace(',', '', $val)); // Remove commas from currency strings

                if ($cleanKey === 'grossamt' || $cleanKey === 'grosssalary' || $cleanKey === 'gross') {
                    $grossAmt = $val;
                } elseif ($cleanKey === 'basic' || $cleanKey === 'basicsalary') {
                    $basic = $val;
                } elseif ($cleanKey === 'hra' || $cleanKey === 'hraallowance') {
                    $hra = $val;
                } elseif ($cleanKey === 'convey' || $cleanKey === 'conveyance' || $cleanKey === 'conveyanceallowance') {
                    $convey = $val;
                } elseif ($cleanKey === 'allowance' || $cleanKey === 'specialallowance') {
                    $allowance = $val;
                } elseif ($cleanKey === 'medical' || $cleanKey === 'medicalallowance') {
                    $medical = $val;
                } elseif ($cleanKey === 'education' && !isset($education_first)) {
                    $education_first = true; // Skip first education column if it's degree
                } elseif ($cleanKey === 'education' || $cleanKey === 'educationallowance') {
                    $educationAllowance = $val;
                } elseif ($cleanKey === 'pfbasic') {
                    $pfBasic = $val;
                } elseif ($cleanKey === 'lta') {
                    $lta = $val;
                } elseif ($cleanKey === 'mediclaim') {
                    $mediclaim = $val;
                }

                // Track all numeric values for component mapping
                if (is_numeric($val) && $val > 0) {
                    $componentValues[$cleanKey] = $val;
                }
            }

            $days = trim($row['days'] ?? '');
            $weekOff = trim($row['week_off'] ?? '');
            $lunchTime = trim($row['lunch_time'] ?? '');

            // Additional fields extraction
            $bloodGroup = trim($row['blood_group'] ?? ($row['bloodgroup'] ?? ''));
            $height = trim($row['height'] ?? '');
            $weightVal = trim($row['weight'] ?? '');
            $weight = is_numeric($weightVal) ? floatval($weightVal) : null;

            $loanTotal = is_numeric(trim($row['loan_total_amount'] ?? ($row['loantotal'] ?? ''))) ? floatval($row['loan_total_amount'] ?? $row['loantotal']) : 0;
            $loanInstallment = is_numeric(trim($row['loan_installment_amount'] ?? ($row['loaninstallment'] ?? ''))) ? floatval($row['loan_installment_amount'] ?? $row['loaninstallment']) : 0;
            $loanPeriod = trim($row['loan_period'] ?? ($row['loanperiod'] ?? ''));

            // Flexible search for blood group, height, weight, and license in keys if not found
            if (empty($bloodGroup) || empty($height) || empty($weight) || empty($drivingLic)) {
                foreach ($row as $key => $val) {
                    $cleanKey = strtolower(str_replace([' ', '.', '_'], '', $key));
                    if ($cleanKey === 'bloodgroup' && empty($bloodGroup))
                        $bloodGroup = trim($val);
                    if ($cleanKey === 'height' && empty($height))
                        $height = trim($val);
                    if ($cleanKey === 'weight' && empty($weight))
                        $weight = is_numeric(trim($val)) ? floatval($val) : null;
                    if (($cleanKey === 'drivinglicense' || $cleanKey === 'drivinglicence' || $cleanKey === 'drivinglic') && empty($drivingLic))
                        $drivingLic = trim($val);
                }
            }

            // CRITICAL: Skip if no employee code found
            if (empty($empCode)) {
                return $this->skipEmployee(
                    __('Employee code is missing. Each row must have EMP.CODE filled.'),
                    '',
                    $empName
                );
            }

            if (! empty($branchName) && ! $activeBranchId) {
                return $this->skipEmployee(
                    __('Branch ":name" was not found. Add it under Organization first.', ['name' => $branchName]),
                    $empCode,
                    $empName
                );
            }

            $masters = $this->resolveOrganizationMasters(
                $empCode,
                $empName,
                $activeBranchId,
                $sectionName,
                $categoryName,
                $departmentName,
                $designationName,
                $shiftName,
                $skillData
            );

            if ($masters === false) {
                return null;
            }

            [
                'section' => $section,
                'category' => $category,
                'department' => $department,
                'designation' => $designation,
                'shift' => $shift,
                'skill_ids' => $skillIds,
            ] = $masters;

            // User Management: Find by Employee record first
            $employee = Employee::withoutGlobalScopes()
                ->where('employee_id', $empCode)
                ->when($activeBranchId, fn ($query) => $query->where('branch_id', $activeBranchId))
                ->first();
            $user = $employee ? $employee->user : null;

            // If not found by employee, check if a user with this numeric ID already exists (matching machine UserId)
            if (! $user && is_numeric($empCode)) {
                $user = User::withoutGlobalScopes()->find((int) $empCode);
            }

            // If still not found, try email (if provided)
            if (! $user && ! empty($emails)) {
                $user = User::where('email', $emails)->first();
            }

            if (! $user) {
                $userData = [
                    'name' => $empName,
                    'email' => ! empty($emails) ? $emails : null,
                    'type' => 'employee',
                    'lang' => 'en',
                    'created_by' => $creatorId,
                ];

                // CRITICAL: If empCode is numeric, use it as the User ID to match machine configuration
                if (is_numeric($empCode)) {
                    $userData['id'] = (int) $empCode;
                }

                // We use forceCreate to bypass fillable protection for the 'id' field
                $user = User::forceCreate($userData);
            } else {
                $userUpdates = [];

                if (! empty($empName) && $empName !== 'Unknown') {
                    $userUpdates['name'] = $empName;
                }

                if (($user->type ?? null) !== 'employee') {
                    $userUpdates['type'] = 'employee';
                }

                if ($userUpdates !== []) {
                    $user->update($userUpdates);
                }
            }

            if (! $user->hasAnyRole(['employee', 'staff'])) {
                $employeeRole = resolveEmployeeSpatieRole(getCompanyOwnerId());
                if ($employeeRole) {
                    $user->assignRole($employeeRole);
                }
            }

            // Handle BankMaster
            $bankId = null;
            if (!empty($bankName)) {
                $bankObj = BankMaster::withoutGlobalScopes()->where('bank_name', 'like', $bankName);
                if ($activeBranchId) {
                    $bankObj->where(function ($q) use ($activeBranchId) {
                        $q->where('branch_id', $activeBranchId)->orWhereNull('branch_id');
                    });
                }
                $bankObj = $bankObj->first();

                if (!$bankObj) {
                    $bankObj = BankMaster::create([
                        'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $bankName), 0, 10)),
                        'bank_name' => $bankName,
                        'ifsc_code' => $bankIfsc,
                        'branch_id' => $activeBranchId,
                        'created_by' => $creatorId,
                        'status' => 'active'
                    ]);
                }
                $bankId = $bankObj->id;
            }

            // Employee Data Assembly
            $employeeData = [
                'user_id' => $user->id,
                'employee_id' => $empCode,
                'phone' => $phone1,
                'phone_2' => $phone2,
                'date_of_birth' => $birthDt,
                'gender' => $gender,
                'father_name' => $fatherName,
                'section_id' => $section?->id,
                'category_id' => $category?->id,
                'department_id' => $department?->id,
                'designation_id' => $designation?->id,
                'shift_id' => $shift?->id,
                'lunch_time' => $lunchTime,
                'week_off' => $weekOff,
                'days' => $days,
                'date_of_joining' => $joiningDt,
                'confirm_date' => $confirmDt,
                'place' => $place,
                'employment_type' => $status ?: 'Full Time',
                'employment_status' => $status,
                'po_status' => (strtoupper($opStatus) === 'P' ? 'Permanent' : 'Other'),
                'address_line_1' => $localAddress,
                'city' => $localCity,
                'state' => $state,
                'country' => 'India',
                'postal_code' => $localPincode,
                'permanent_address' => $permAddress,
                'aadhar_card_number' => $aadharCard,
                'pan_card_number' => $itaxNo,
                'driving_license' => $drivingLic,
                'election_card' => $elecCard,
                'uan_number' => $uanNo,
                'pf_number' => $pfNo,
                'pf_flag' => $pfFlag,
                'esic_number' => $esicNo,
                'esic_flag' => $esicFlag,
                'ptax_flag' => $ptaxFlag,
                'hod_flag' => $hodFlag,
                'bonus_flag' => $bonusFlag,
                'ot_flag' => $otFlag,
                'ot_hours' => $otHours,
                'ot_type' => $otType,
                'daily_option' => $dailyFlag,
                'bank_name' => $bankName,
                'bank_type' => $bankType,
                'ifsc_code' => $bankIfsc,
                'bank_identifier_code' => $bankIfsc,
                'account_number' => $bankAc,
                'bank_id' => $bankId,
                'blood_group' => $bloodGroup,
                'height' => $height,
                'weight' => $weight,
                'loan_total_amount' => $loanTotal,
                'loan_installment_amount' => $loanInstallment,
                'loan_period' => $loanPeriod,
                'gross_salary' => $grossAmt,
                'basic_salary' => $basic,
                'hra_allowance' => $hra,
                'conveyance_allowance' => $convey,
                'special_allowance' => $allowance,
                'medical_allowance' => $medical,
                'lta_allowance' => $lta,
                'education_allowance' => $educationAllowance,
                'pf_basic_salary' => $pfBasic,
                'education' => $education,
                'experience' => $experience,
                'skill_id' => $skillIds,
                'emy_code' => $empCode,
                'branch_id' => $activeBranchId, // Ensure branch_id is saved
                'daily_option' => (isset($days) && $days == 1) ? 1 : 0,
                'working_days' => (isset($days) && is_numeric($days)) ? $days : 26,
                'created_by' => $creatorId,
            ];

            Employee::withoutGlobalScopes()
                ->withTrashed()
                ->updateOrCreate(
                    ['employee_id' => $empCode, 'branch_id' => $activeBranchId],
                    $employeeData
                );

            // DYNAMIC COMPONENT ASSIGNMENT
            $allComponents = SalaryComponent::withoutGlobalScopes()->where('status', 'active')->get();
            $assignedComponents = []; // Store as [id => amount] for system compatibility

            foreach ($allComponents as $comp) {
                $compCleanName = strtolower(str_replace([' ', '.', '_'], '', $comp->name));
                $amount = 0;

                // Check if this component exists in our row data with a value > 0
                if (isset($componentValues[$compCleanName]) && $componentValues[$compCleanName] > 0) {
                    $amount = $componentValues[$compCleanName];
                }

                // Special mappings for common variations if not already caught
                if ($compCleanName === 'hra' && $hra > 0 && $amount == 0)
                    $amount = $hra;
                if ($compCleanName === 'lta' && $lta > 0 && $amount == 0)
                    $amount = $lta;
                if ($compCleanName === 'pfbasic' && $pfBasic > 0 && $amount == 0)
                    $amount = $pfBasic;
                if ($compCleanName === 'allowance' && $allowance > 0 && $amount == 0)
                    $amount = $allowance;
                if ($compCleanName === 'mediclaim' && $mediclaim > 0 && $amount == 0)
                    $amount = $mediclaim;

                if ($amount > 0) {
                    $assignedComponents[$comp->id] = $amount;
                }
            }

            // Ensure default components (BASIC, HRA, Allowance) are always included in the list
            $defaultNames = ['BASIC', 'HRA', 'Allowance'];
            $defaultIds = SalaryComponent::whereIn('name', $defaultNames)->pluck('id')->toArray();
            foreach ($defaultIds as $defId) {
                if (!isset($assignedComponents[$defId])) {
                    $assignedComponents[$defId] = 0; // Set placeholder 0, calculation will pick from employee columns
                }
            }

            // SYNC TO EmployeeSalary TABLE (Primary source for payroll UI)
            EmployeeSalary::updateOrCreate(
                ['employee_id' => $user->id],
                [
                    'basic_salary' => $basic,
                    'components' => $assignedComponents,
                    'is_active' => true,
                    'created_by' => $creatorId
                ]
            );

            \Log::info("Import: Processed employee $empCode with Basic: $basic, Gross: $grossAmt");

            $this->rowsSaved++;
            $this->savedNumbers[] = $this->currentRow;

            return null;

        } catch (\Throwable $e) {
            \Log::error('Import: Fatal Error in model()', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'row' => $row
            ]);
            throw $e;
        }
    }

    private function skipEmployee(string $reason, string $empCode = '', string $empName = ''): null
    {
        $this->skippedRows[] = [
            'row' => $this->currentRow,
            'emp_code' => $empCode !== '' ? $empCode : '—',
            'name' => $empName !== '' && $empName !== 'Unknown' ? $empName : '—',
            'reason' => $reason,
        ];

        return null;
    }

    private function normalizeMasterName(string $name): string
    {
        return strtolower(trim($name));
    }

    private function branchScopedQuery(string $modelClass, string $name, ?int $branchId)
    {
        $normalized = $this->normalizeMasterName($name);

        $query = $modelClass::withoutGlobalScopes()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized]);

        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        return $query;
    }

    /**
     * Resolve organization masters — never auto-create. Returns false when row is skipped.
     *
     * @return array{section: ?Section, category: ?Category, department: ?Department, designation: ?Designation, shift: ?Shift, skill_ids: array<int, string>}|false
     */
    private function resolveOrganizationMasters(
        string $empCode,
        string $empName,
        ?int $branchId,
        string $sectionName,
        string $categoryName,
        string $departmentName,
        string $designationName,
        string $shiftName,
        string $skillData,
    ): array|false {
        $section = null;
        if ($sectionName !== '') {
            $section = $this->branchScopedQuery(Section::class, $sectionName, $branchId)->first();
            if (! $section) {
                $this->skipEmployee(
                    __('Section ":name" not found. Add it under Organization → Sections first.', ['name' => $sectionName]),
                    $empCode,
                    $empName
                );

                return false;
            }
        }

        $category = null;
        if ($categoryName !== '') {
            $category = $this->branchScopedQuery(Category::class, $categoryName, $branchId)->first();
            if (! $category) {
                $this->skipEmployee(
                    __('Category ":name" not found. Add it under Organization → Categories first.', ['name' => $categoryName]),
                    $empCode,
                    $empName
                );

                return false;
            }
        }

        $department = null;
        if ($departmentName !== '') {
            $department = $this->branchScopedQuery(Department::class, $departmentName, $branchId)->first();
            if (! $department) {
                $this->skipEmployee(
                    __('Department ":name" not found. Add it under Organization → Departments first.', ['name' => $departmentName]),
                    $empCode,
                    $empName
                );

                return false;
            }
        }

        $designation = null;
        if ($designationName !== '') {
            if (! $department) {
                $this->skipEmployee(
                    __('Designation ":designation" needs Department in the same row. Department is missing or not found in masters.', ['designation' => $designationName]),
                    $empCode,
                    $empName
                );

                return false;
            }

            $designation = Designation::withoutGlobalScopes()
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeMasterName($designationName)])
                ->where('department_id', $department->id)
                ->when($branchId, function ($q) use ($branchId) {
                    $q->where(function ($inner) use ($branchId) {
                        $inner->where('branch_id', $branchId)->orWhereNull('branch_id');
                    });
                })
                ->first();

            if (! $designation) {
                $this->skipEmployee(
                    __('Designation ":designation" not found under Department ":department". Add it under Organization → Designations first.', [
                        'designation' => $designationName,
                        'department' => $departmentName,
                    ]),
                    $empCode,
                    $empName
                );

                return false;
            }
        }

        $shift = null;
        if ($shiftName !== '') {
            $normalizedShift = $this->normalizeMasterName($shiftName);
            $shiftQuery = Shift::withoutGlobalScopes()->where(function ($q) use ($shiftName, $normalizedShift) {
                $q->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedShift])
                    ->orWhereRaw('LOWER(TRIM(short_code)) = ?', [$normalizedShift])
                    ->orWhere('name', 'like', $shiftName)
                    ->orWhere('short_code', 'like', $shiftName);
            });

            $branchShiftQuery = clone $shiftQuery;
            if ($branchId) {
                $branchShiftQuery->where(function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                });
            }

            $shift = $branchShiftQuery->first() ?? $shiftQuery->first();

            if (! $shift && is_numeric($shiftName)) {
                $shift = Shift::withoutGlobalScopes()->find((int) $shiftName);
            }

            if (! $shift) {
                $this->skipEmployee(
                    __('Shift ":name" not found. Add it under Organization → Shifts first (name or short code must match).', ['name' => $shiftName]),
                    $empCode,
                    $empName
                );

                return false;
            }
        }

        $skillIds = [];
        if ($skillData !== '') {
            $missingSkills = [];

            foreach (explode(',', $skillData) as $skillName) {
                $skillName = trim($skillName);
                if ($skillName === '') {
                    continue;
                }

                $skill = $this->branchScopedQuery(Skill::class, $skillName, $branchId)->first();
                if (! $skill) {
                    $missingSkills[] = $skillName;
                    continue;
                }

                $skillIds[] = (string) $skill->id;
            }

            if ($missingSkills !== []) {
                $this->skipEmployee(
                    __('Skill(s) not found in Organization masters: :names. Add them under Organization → Skills first.', [
                        'names' => implode(', ', $missingSkills),
                    ]),
                    $empCode,
                    $empName
                );

                return false;
            }
        }

        return [
            'section' => $section,
            'category' => $category,
            'department' => $department,
            'designation' => $designation,
            'shift' => $shift,
            'skill_ids' => $skillIds,
        ];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                if ($this->structureValidated) {
                    return;
                }

                $this->structureValidated = true;
                $rows = $event->getSheet()->getDelegate()->toArray(null, true, true, false);

                if (empty($rows) || empty($rows[0])) {
                    throw new \InvalidArgumentException(
                        'The uploaded file is empty. Please download the sample Excel template, add employee rows, and try again.'
                    );
                }

                self::assertEmployeeImportHeaders($rows[0]);
            },
        ];
    }

    /**
     * Ensure the first row matches the employee import template.
     */
    public static function assertEmployeeImportHeaders(array $headerRow): void
    {
        $normalized = [];

        foreach ($headerRow as $header) {
            if ($header === null || trim((string) $header) === '') {
                continue;
            }

            $normalized[] = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $header));
        }

        if ($normalized === []) {
            throw new \InvalidArgumentException(
                'Invalid file format. No column headers were found in the first row. Please download the sample file and use the correct template.'
            );
        }

        $hasEmpCode = false;

        foreach ($normalized as $header) {
            if (in_array($header, ['empcode', 'emp_code', 'employeecode', 'empno', 'employeeid', 'code'], true)) {
                $hasEmpCode = true;
                break;
            }

            if (str_contains($header, 'emp') && str_contains($header, 'code')) {
                $hasEmpCode = true;
                break;
            }
        }

        if (! $hasEmpCode) {
            throw new \InvalidArgumentException(
                'Invalid file format. Required column "EMP.CODE" (Employee Code) was not found. Please download the sample file and match the column headers.'
            );
        }
    }

    public function rules(): array
    {
        return [
            // Flexible rules
        ];
    }

    public function map($row): array
    {
        $this->currentRow++;
        return $row;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
