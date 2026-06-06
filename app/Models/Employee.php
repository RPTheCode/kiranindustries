<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, \App\Traits\HasBranch, SoftDeletes;

    public function scopeActive($query)
    {
        return $query->whereHas('user', function ($q) {
            $q->where('status', 'active');
        });
    }

    protected $fillable = [
        'user_id',
        'employee_id',
        'essl_id',
        'common_id',
        'emy_code',
        'phone',
        'phone_2',
        'gender',
        'marital_status',
        'date_of_birth',
        'wedding_date',
        'gender',
        'father_name',
        'education',
        'experience',
        'aadhar_card_number',
        'pan_card_number',
        'driving_license',
        'driving_licence',
        'election_card',
        'blood_group',
        'height',
        'weight',
        'branch_id',
        'department_id',
        'designation_id',
        'section_id',
        'category_id',
        'shift_id',
        'lunch_time',
        'week_off',
        'week_off_type',
        'days',
        'attendance_policy_id',
        'date_of_joining',
        'confirm_date',
        'place',
        'employment_type',
        'employment_status',
        'po_status',
        'daily_option',
        'working_days',
        'resign_date',
        'resign_reason_id',
        'probation_period',
        'weight',
        'address_line_1',
        'address_line_2',
        'permanent_address',
        'city',
        'state',
        'country',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_number',
        'emergency_contact_address',
        'bank_name',
        'ifsc_code',
        'bank_type',
        'account_holder_name',
        'account_number',
        'bank_identifier_code',
        'bank_branch',
        'bank_id',
        'tax_payer_id',
        'term_date',
        'skill_id',
        'pf_number',
        'pf_id',
        'pf_flag',
        'uan_number',
        'esic_number',
        'esi_id',
        'esic_flag',
        'extra_salary_component_ids',
        'pt_deduction',
        'ptax_flag',
        'bonus_flag',
        'ot_flag',
        'ot_hours',
        'ot_type',
        'hod_flag',
        'loan_total_amount',
        'loan_installment_amount',
        'loan_period',
        'nominee_name',
        'nominee_account_number',
        'nominee_aadhar',
        'gross_salary',
        'it_amount',
        'basic_salary',
        'pf_basic_salary',
        'lta_allowance',
        'hra_allowance',
        'conveyance_allowance',
        'special_allowance',
        'other_allowance',
        'medical_allowance',
        'education_allowance',
        'ot_rate',
        'created_by'
    ];

    protected $casts = [
        'skill_id' => 'array',
        'daily_option' => 'boolean',
        'pf_flag' => 'boolean',
        'esic_flag' => 'boolean',
        'extra_salary_component_ids' => 'array',
        'ptax_flag' => 'boolean',
        'bonus_flag' => 'boolean',
        'ot_flag' => 'boolean',
        'hod_flag' => 'boolean',
        'working_days' => 'integer',
        'weight' => 'decimal:2',
        'basic_salary' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'lta_allowance' => 'decimal:2',
        'hra_allowance' => 'decimal:2',
        'conveyance_allowance' => 'decimal:2',
        'special_allowance' => 'decimal:2',
        'pt_deduction' => 'decimal:2',
        'other_allowance' => 'decimal:2',
        'medical_allowance' => 'decimal:2',
        'education_allowance' => 'decimal:2',
        'ot_rate' => 'decimal:2',
        'ot_hours' => 'decimal:2',
        'loan_total_amount' => 'decimal:2',
        'loan_installment_amount' => 'decimal:2',
    ];

    protected $appends = ['skills'];

    /**
     * Get the skills associated with the employee.
     * Note: Since we store IDs in JSON, we can't use standard belongsTo.
     * We can define an accessor or a method to get collection.
     */
    public function getSkillsAttribute()
    {
        if (empty($this->skill_id)) {
            return collect([]);
        }
        return Skill::whereIn('id', $this->skill_id)->get();
    }

    /**
     * Get the branch that the employee belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the department that the employee belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the designation that the employee has.
     */
    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get the section that the employee belongs to.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the category that the employee belongs to.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the shift that the employee belongs to.
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the attendance policy that the employee has.
     */
    public function attendancePolicy()
    {
        return $this->belongsTo(AttendancePolicy::class);
    }

    /**
     * Get the bank master details.
     */
    public function bankMaster()
    {
        return $this->belongsTo(BankMaster::class, 'bank_id');
    }

    /**
     * Get the PF master details.
     */
    public function pfMaster()
    {
        return $this->belongsTo(PfMaster::class, 'pf_id');
    }

    /**
     * Get the ESI master details.
     */
    public function esiMaster()
    {
        return $this->belongsTo(EsiMaster::class, 'esi_id');
    }

    /**
     * Get the user associated with the employee.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the resignation reason.
     */
    public function resignReason()
    {
        return $this->belongsTo(ResignReason::class, 'resign_reason_id');
    }

    /**
     * Get the user who created the employee.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the employee's biometric attendance records.
     */
    public function biometricAttendances()
    {
        return $this->hasMany(BiometricAttendance::class, 'employee_id');
    }

    /**
     * Get the employee's documents.
     */
    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id', 'user_id');
    }

    /**
     * Get the employee's work history.
     */
    public function workHistory()
    {
        return $this->hasMany(EmployeeWorkHistory::class, 'employee_id', 'user_id');
    }

    /**
     * Get the individual week offs for the employee.
     */
    public function individualWeekOffs()
    {
        return $this->hasMany(EmployeeWeekOff::class, 'employee_id', 'user_id');
    }

    /**
     * Get the employee's nominees.
     */
    public function nominees()
    {
        return $this->hasMany(EmployeeNominee::class, 'employee_id', 'user_id');
    }

    /**
     * Generate the next Employee ID.
     */
    public static function getNextEmployeeId()
    {
        // Get the latest number from employee_id (purely numeric)
        // Use withoutGlobalScopes to ensure uniqueness across ALL branches
        $maxNumber = self::withoutGlobalScopes()
            ->whereRaw("employee_id REGEXP '^[0-9]+$'")
            ->selectRaw('MAX(CAST(employee_id AS UNSIGNED)) as max_num')
            ->value('max_num');

        // If no record exists, start from 1 (or a default base if preferred)
        $nextNumber = $maxNumber ? $maxNumber + 1 : 1;

        return (string) $nextNumber;
    }

    /**
     * Calculate leave days in a range, excluding week offs and holidays.
     *
     * @param \Carbon\Carbon|string $startDate
     * @param \Carbon\Carbon|string $endDate
     * @param int|null $branchId
     * @return int
     */
    public function calculateLeaveDaysInRange($startDate, $endDate, $branchId = null)
    {
        $startDate = \Carbon\Carbon::parse($startDate);
        $endDate = \Carbon\Carbon::parse($endDate);
        $totalDays = 0;

        $targetBranchId = $branchId ?? $this->branch_id;

        // Fetch WeekOff settings for the target branch and employment type
        $weekOff = WeekOff::where('branch_id', $targetBranchId)
            ->where('employment_type', $this->employment_type ?? 'Employee')
            ->first();

        // Fetch Holidays for the target branch
        $holidays = Holiday::where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                });
        })
            ->where(function ($q) use ($targetBranchId) {
                $q->doesntHave('branches')
                    ->orWhereHas('branches', function ($q2) use ($targetBranchId) {
                        $q2->where('branches.id', $targetBranchId);
                    });
            })
            ->get();

        // Fetch individual week offs for this employee in the range
        $individualOffs = $this->individualWeekOffs()
            ->whereBetween('off_date', [$startDate, $endDate])
            ->pluck('off_date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->format('Y-m-d');

            // Check if today is an individual week off
            if (in_array($dateString, $individualOffs)) {
                continue; // It's an off day, don't count as leave day
            }

            // Check if today is a branch-wide week off (falling back to weekend if no settings found)
            $isWeekOff = $weekOff ? $weekOff->isDateWeekOff($date) : $date->isWeekend();

            $isHoliday = false;
            foreach ($holidays as $holiday) {
                if ($date->between($holiday->start_date, $holiday->end_date)) {
                    $isHoliday = true;
                    break;
                }
            }

            if (!$isWeekOff && !$isHoliday) {
                $totalDays++;
            }
        }

        return $totalDays;
    }
}