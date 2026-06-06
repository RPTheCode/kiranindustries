<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\StorageConfigService;
use Inertia\Inertia;
use App\Services\ActivityLogger;
use App\Services\SalaryPayroll\SalaryComponentAssignmentService;
use App\Traits\LogsActivity;

class EmployeeController extends Controller
{
    use LogsActivity;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();
        $activeBranchId = session('active_branch_id');

        $query = User::whereIn('created_by', getCompanyAndUsersId())
            ->with([
                'employee.branch',
                'employee.department',
                'employee.designation',
                'employee.category' => function ($q) {
                    $q->withoutGlobalScopes();
                }
            ])
            ->where('type', 'employee');

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhereHas('employee', function ($eq) use ($request) {
                        $eq->where('employee_id', 'like', '%' . $request->search . '%')
                            ->orWhere('phone', 'like', '%' . $request->search . '%');
                    });
            });
        }

        // Handle department filter
        if ($request->has('department') && !empty($request->department) && $request->department !== 'all') {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department);
            });
        }

        // Handle branch filter (Request filter overrides session branch if provided)
        $branchId = $request->get('branch', $activeBranchId);
        
        // Security: Prevent non-company/non-admin users from accessing 'all' branches
        if (!$authUser->hasRole(['company', 'superadmin', 'admin']) && $branchId === 'all') {
            $branchId = $activeBranchId;
        }

        if ($branchId && $branchId !== 'all') {
            $query->whereHas('employee', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        } elseif (!$authUser->hasRole(['company', 'superadmin', 'admin'])) {
            // Fallback: strictly limit to assigned branches if somehow 'all' is reached
            $allowedBranchIds = $authUser->branches()->pluck('branches.id')->toArray();
            $query->whereHas('employee', function ($q) use ($allowedBranchIds) {
                $q->whereIn('branch_id', $allowedBranchIds);
            });
        }

        // Handle designation filter
        if ($request->has('designation') && !empty($request->designation) && $request->designation !== 'all') {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('designation_id', $request->designation);
            });
        }

        // Handle status filter (default: active; pass status=all to show every status)
        $statusFilter = $request->input('status', 'active');
        if ($statusFilter !== 'all' && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        // Handle employment type filter
        if ($request->has('employment_type') && !empty($request->employment_type) && $request->employment_type !== 'all') {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('employment_type', $request->employment_type);
            });
        }

        // Handle category filter
        if ($request->has('category') && !empty($request->category) && $request->category !== 'all') {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('category_id', $request->category);
            });
        }

        if ($request->filled('shift_id') && $request->shift_id !== 'all') {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('shift_id', $request->shift_id);
            });
        }

        // Handle skill filter
        if ($request->has('skill') && !empty($request->skill) && $request->skill !== 'all') {
            $skillId = $request->skill;

            // Get user IDs that have the skill
            $userIdsWithSkill = \App\Models\EmployeeWorkHistory::whereHas('skills', function ($q) use ($skillId) {
                $q->where('skills.id', $skillId);
            })->pluck('employee_id');

            // Get emails of those users
            $emailsWithSkill = \App\Models\User::whereIn('id', $userIdsWithSkill)->pluck('email');

            // Filter main query by those emails
            $query->whereIn('email', $emailsWithSkill);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $employees = $query->paginate($request->per_page ?? 10);

        // Fetch departments - filtered by active branch and active status
        $departmentsQuery = Department::withoutGlobalScopes()
            ->with('branch')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId) {
            $departmentsQuery->where('branch_id', $activeBranchId);
        }
        $departments = $departmentsQuery->get(['id', 'name', 'branch_id']);

        // Fetch designations - filtered by active branch and active status
        $designationsQuery = Designation::withoutGlobalScopes()
            ->with('department')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId) {
            $designationsQuery->where('branch_id', $activeBranchId);
        }
        $designations = $designationsQuery->get(['id', 'name', 'department_id']);

        // Fetch available skills - filtered by active branch and active status
        $skillsQuery = \App\Models\Skill::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', true);
        if ($activeBranchId) {
            $skillsQuery->where('branch_id', $activeBranchId);
        }
        $skills = $skillsQuery->get(['id', 'name']);

        // Get plan limits for company users and staff users (only in SaaS mode)
        $planLimits = null;
        if (isSaas()) {
            if ($authUser->type === 'company' && $authUser->plan) {
                $currentUserCount = User::where('type', 'employee')->whereIn('created_by', getCompanyAndUsersId())->count();
                $planLimits = [
                    'current_users' => $currentUserCount,
                    'max_users' => $authUser->plan->max_employees,
                    'can_create' => $currentUserCount < $authUser->plan->max_employees
                ];
            }
            // Check for staff users (created by company users)
            elseif ($authUser->type !== 'superadmin' && $authUser->created_by) {
                $companyUser = User::find($authUser->created_by);
                if ($companyUser && $companyUser->type === 'company' && $companyUser->plan) {
                    $currentUserCount = User::where('type', 'employee')->whereIn('created_by', getCompanyAndUsersId())->count();
                    $planLimits = [
                        'current_users' => $currentUserCount,
                        'max_users' => $companyUser->plan->max_employees,
                        'can_create' => $currentUserCount < $companyUser->plan->max_employees
                    ];
                }
            }
        }
        $branches = Branch::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id', 'name']);

        $categories = $activeBranchId
            ? $this->categoriesForBranch((int) $activeBranchId)
            : [];


        $filters = $request->all(['search', 'branch', 'department', 'designation', 'status', 'shift_id', 'skill', 'employment_type', 'category', 'sort_field', 'sort_direction', 'per_page']);
        if (! isset($filters['status']) || $filters['status'] === null || $filters['status'] === '') {
            $filters['status'] = 'active';
        }
        if (!isset($filters['branch'])) {
            $filters['branch'] = $branchId ? (string) $branchId : 'all';
        }

        return Inertia::render('hr/employees/index', [
            'employees' => $employees,
            'planLimits' => $planLimits,
            'departments' => $departments,
            'designations' => $designations,
            'branches' => $branches,
            'categories' => $categories,
            'skills' => $skills,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $activeBranchId = session('active_branch_id');

        $departmentsQuery = Department::with('branch')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId) {
            $departmentsQuery->where('branch_id', $activeBranchId);
        }
        $departments = $departmentsQuery->get(['id', 'name', 'branch_id']);

        $designationsQuery = Designation::with('department')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId) {
            $designationsQuery->where('branch_id', $activeBranchId);
        }
        $designations = $designationsQuery->get(['id', 'name', 'department_id']);

        $documentTypes = DocumentType::whereIn('created_by', getCompanyAndUsersId())
            ->get(['id', 'name', 'is_required']);

        $shiftsQuery = \App\Models\Shift::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId) {
            $shiftsQuery->where('branch_id', $activeBranchId);
        }
        $shifts = $shiftsQuery->get(['id', 'name', 'branch_id']);

        $attendancePoliciesQuery = \App\Models\AttendancePolicy::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId) {
            $attendancePoliciesQuery->where('branch_id', $activeBranchId);
        }
        $attendancePolicies = $attendancePoliciesQuery->get(['id', 'name', 'branch_id']);

        $skillsQuery = \App\Models\Skill::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 1);
        if ($activeBranchId) {
            $skillsQuery->where('branch_id', $activeBranchId);
        }
        $skills = $skillsQuery->get(['id', 'name']);

        $sectionsQuery = \App\Models\Section::withoutGlobalScopes()->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId) {
            $sectionsQuery->where('branch_id', $activeBranchId);
        }
        $sections = $sectionsQuery->get(['id', 'name']);

        $categories = $activeBranchId
            ? $this->categoriesForBranch((int) $activeBranchId)
            : [];

        $banks = \App\Models\BankMaster::where('status', 'active')
            ->get(['id', 'bank_name', 'ifsc_code', 'branch_name']);

        $pfs = \App\Models\PfMaster::where('status', 'active')
            ->get(['id', 'name', 'percentage_employee', 'percentage_employer']);

        $esis = \App\Models\EsiMaster::where('status', 'active')
            ->get(['id', 'name', 'percentage_employee', 'percentage_employer']);
        $branches = \App\Models\Branch::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id', 'name']);
        $resignReasons = \App\Models\ResignReason::where('is_active', true)->get();
        $overtimeOptions = \App\Models\Overtime::where('is_active', true)->get();

        $salaryComponents = $this->salaryComponentsForBranch(
            $activeBranchId ? (int) $activeBranchId : null
        );

        return Inertia::render('hr/employees/create', [
            'departments' => $departments,
            'designations' => $designations,
            'sections' => $sections,
            'categories' => $categories,
            'banks' => $banks,
            'pfs' => $pfs,
            'esis' => $esis,
            'documentTypes' => $documentTypes,
            'shifts' => $shifts,
            'attendancePolicies' => $attendancePolicies,
            'skills' => $skills,
            'branches' => $branches,
            'salaryComponents' => $salaryComponents,
            'nextEmployeeId' => Employee::getNextEmployeeId(),
            'resignReasons' => $resignReasons,
            'overtimeOptions' => $overtimeOptions,
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Auto-generate Employee ID to prevent duplicates/gaps
        $request->merge(['employee_id' => Employee::getNextEmployeeId()]);

        try {
            \Log::info('Employee Store Request Data:', $request->all());

            // Decode JSON stringified fields if they are sent as strings (via FormData)
            if ($request->has('documents') && is_string($request->documents)) {
                $request->merge(['documents' => json_decode($request->documents, true)]);
            }
            if ($request->has('skill_id') && is_string($request->skill_id)) {
                $request->merge(['skill_id' => json_decode($request->skill_id, true)]);
            }
            if ($request->has('extra_salary_component_ids') && is_string($request->extra_salary_component_ids)) {
                $request->merge(['extra_salary_component_ids' => json_decode($request->extra_salary_component_ids, true)]);
            }

            // Validate basic information
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255|unique:users,email',
                'phone' => 'nullable|digits:10',
                'phone_2' => 'nullable|digits:10',
                'gender' => 'required|in:male,female,other',
                'marital_status' => 'nullable|string|max:20',
                'date_of_birth' => 'required|date|before_or_equal:today',
                'wedding_date' => 'nullable|date',
                'father_name' => 'nullable|string|max:255',
                'employee_id' => [
                    'required',
                    'string',
                    'max:255',
                    \Illuminate\Validation\Rule::unique('employees')->where(function ($query) use ($request) {
                        return $query->whereIn('created_by', getCompanyAndUsersId());
                    })
                ],
                'password' => 'nullable|string|min:8',
                'profile_image' => 'nullable|max:2048',
                'shift_id' => 'required|exists:shifts,id',
                'department_id' => 'required|exists:departments,id',
                'designation_id' => 'required|exists:designations,id',
                'section_id' => 'required|exists:sections,id',
                'category_id' => 'required|exists:categories,id',
                'date_of_joining' => 'required|date',
                'po_status' => 'required|in:Permanent,Other',
                'daily_option' => 'required|boolean',
                'week_off' => 'nullable',
                'ot_flag' => 'nullable|boolean',
                'ot_hours' => 'required_if:ot_flag,1,true|nullable|string|max:20',
            ], [
                'name.required' => __('Please enter the employee name.'),
                'date_of_birth.required' => __('Date of birth is required.'),
                'gender.required' => __('Please select a gender.'),
                'department_id.required' => __('Please select a department.'),
                'designation_id.required' => __('Please select a designation.'),
                'date_of_joining.required' => __('Date of joining is required.'),
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            // Create User
            $email = $request->email ? $request->email : null;
            $password = $request->password ? Hash::make($request->password) : Hash::make('password123');

            $user = new User();
            $user->name = $request->name;
            $user->email = $email;
            $user->password = $password;
            $user->type = 'employee';
            $user->lang = 'en';
            $user->created_by = creatorId();

            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->storeAs('media', $filename, 'public');
                $user->avatar = $filename;
            } elseif ($request->filled('avatar')) {
                $user->avatar = $request->avatar;
            }
            $user->save();

            // Assign Role
            $employeeRole = Role::where('name', 'employee')->first();
            if ($employeeRole) {
                $user->assignRole($employeeRole);
            }

            // Create Employee
            $employee = new Employee();
            $employee->user_id = $user->id;
            $employee->employee_id = $request->employee_id;
            $employee->emy_code = $request->emy_code ?? $request->employee_id;
            $employee->essl_id = $request->essl_id;
            $employee->common_id = $request->common_id;
            $employee->phone = $request->phone;
            $employee->phone_2 = $request->phone_2;
            $employee->gender = $request->gender;
            $employee->marital_status = $request->marital_status;
            $employee->date_of_birth = $request->date_of_birth;
            $employee->wedding_date = $request->wedding_date;
            $employee->father_name = $request->father_name;
            $employee->branch_id = $request->branch_id ?? session('active_branch_id');
            $employee->department_id = $request->department_id;
            $employee->designation_id = $request->designation_id;
            $employee->section_id = $request->section_id;
            $employee->category_id = $this->resolveCategoryIdForBranch($request->category_id, $employee->branch_id);
            $employee->shift_id = $request->shift_id;
            $employee->attendance_policy_id = $request->attendance_policy_id;
            $employee->date_of_joining = $request->date_of_joining;
            $employee->confirm_date = $request->confirm_date;
            $employee->employment_type = $request->employment_type;
            $employee->po_status = $request->po_status;
            $employee->daily_option = $request->daily_option;
            $employee->working_days = $request->working_days ?? 26;

            // Address & Personal
            $employee->address_line_1 = $request->address_line_1;
            $employee->address_line_2 = $request->address_line_2;
            $employee->city = $request->city;
            $employee->state = $request->state;
            $employee->country = $request->country ?? 'India';
            $employee->postal_code = $request->postal_code;
            $employee->education = $request->education;
            $employee->experience = $request->experience;
            $employee->aadhar_card_number = $request->aadhar_card_number;
            $employee->pan_card_number = $request->pan_card_number;
            $employee->driving_license = $request->driving_license ?? $request->driving_licence;
            $employee->blood_group = $request->blood_group;
            $employee->height = $request->height;
            $employee->weight = $request->weight;
            $employee->lunch_time = $request->lunch_time;
            $employee->week_off_type = $request->week_off_type ?? 'weekly';
            $employee->week_off = is_array($request->week_off) ? implode(',', $request->week_off) : $request->week_off;

            // Emergency Contact
            $employee->emergency_contact_name = $request->emergency_contact_name;
            $employee->emergency_contact_relationship = $request->emergency_contact_relationship;
            $employee->emergency_contact_number = $request->emergency_contact_number ?? $request->phone_2;
            $employee->emergency_contact_address = $request->emergency_contact_address;

            // Banking
            $employee->bank_name = $request->bank_name;
            $employee->account_holder_name = $request->account_holder_name;
            $employee->account_number = $request->account_number;
            $employee->ifsc_code = $request->ifsc_code ?? $request->bank_identifier_code;
            $employee->bank_identifier_code = $request->bank_identifier_code ?? $request->ifsc_code;
            $employee->bank_type = $request->account_type ?? $request->bank_type;
            $employee->bank_id = $request->bank_id;
            $employee->bank_branch = $request->bank_branch;

            // Statutory
            $employee->pf_number = $request->pf_number;
            $employee->uan_number = $request->uan_number;
            $employee->esic_number = $request->esic_number;
            $employee->pf_id = $request->pf_id;
            $employee->esi_id = $request->esi_id;
            $employee->pf_flag = $request->pf_flag ?? false;
            $employee->esic_flag = $request->esic_flag ?? false;
            $employee->ptax_flag = $request->ptax_flag ?? false;
            $employee->bonus_flag = $request->bonus_flag ?? false;
            $employee->ot_flag = $request->ot_flag ?? false;
            $employee->ot_hours = ($employee->ot_flag && filled($request->ot_hours)) ? $request->ot_hours : null;
            $employee->ot_type = $employee->ot_hours ? $request->ot_type : null;
            $employee->hod_flag = $request->hod_flag ?? false;
            $employee->skill_id = $this->normalizeSkillIds($request->skill_id);

            // Salary & Loan
            $employee->basic_salary = $request->basic_salary ?? 0;
            $employee->gross_salary = $request->gross_salary ?? 0;
            $employee->loan_total_amount = $request->loan_total_amount ?? 0;
            $employee->loan_installment_amount = $request->loan_installment_amount ?? 0;
            $employee->loan_period = $request->loan_period;
            $employee->extra_salary_component_ids = $this->normalizeExtraSalaryComponentIds(
                $request,
                (int) ($employee->branch_id ?? 0) ?: null
            );

            $employee->created_by = creatorId();
            $employee->save();

            // Refine profile image naming
            if ($user->avatar) {
                $user->avatar = $this->handleProfileImage($user, $employee, $user->avatar);
                $user->save();
            }

            // Handle Documents
            if ($request->has('documents')) {
                $this->saveEmployeeDocuments($employee, $request->documents);
            }

            // Salary Components
            if ($request->has('salary_components')) {
                \App\Models\EmployeeSalary::updateOrCreate(
                    ['employee_id' => $user->id],
                    [
                        'basic_salary' => $request->basic_salary ?? 0,
                        'components' => $request->salary_components,
                        'is_active' => true,
                        'created_by' => creatorId(),
                    ]
                );
            }

            // Nominees
            if ($request->has('nominees') && is_array($request->nominees)) {
                foreach ($request->nominees as $nominee) {
                    if (!empty($nominee['name'])) {
                        \App\Models\EmployeeNominee::create([
                            'employee_id' => $user->id,
                            'name' => $nominee['name'],
                            'aadhar_number' => $nominee['aadhar_number'] ?? null,
                            'relation' => $nominee['relation'] ?? null,
                            'percentage' => $nominee['percentage'] ?? 0,
                        ]);
                    }
                }
            }

            ActivityLogger::logEmployee($employee->fresh(), 'created');

            return redirect()->route('hr.employees.index')->with('success', __('Employee created successfully'));
        } catch (\Exception $e) {
            \Log::error('Employee creation failed: ' . $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        // Check if employee belongs to current company
        $companyUserIds = getCompanyAndUsersId();
        if (!in_array($employee->created_by, $companyUserIds)) {
            return redirect()->back()->with('error', __('You do not have permission to view this employee'));
        }

        // Load user with employee relationships
        $user = User::with([
            'employee.branch',
            'employee.department',
            'employee.designation',
            'employee.shift',
            'employee.attendancePolicy',
            'employee.documents.documentType',
            'employee.category',
            'employee.section',
            'employee.nominees',
            'employee.resignReason'
        ])
            ->where('id', $employee->user_id)
            ->first();

        // Fetch work history for all users with the same employee_id
        $workHistory = [];
        $relatedEmployments = collect();

        $employeeId = $user->employee->employee_id ?? null;

        if ($employeeId) {
            $relatedEmployments = User::whereHas('employee', function ($query) use ($employeeId) {
                $query->withoutGlobalScopes()->where('employee_id', $employeeId);
            })->with([
                        'employee' => function ($query) {
                            $query->withoutGlobalScopes();
                        },
                        'employee.branch',
                        'employee.department' => function ($query) {
                            $query->withoutGlobalScopes();
                        },
                        'employee.designation' => function ($query) {
                            $query->withoutGlobalScopes();
                        },
                        'employee.shift' => function ($query) {
                            $query->withoutGlobalScopes();
                        },
                        'employee.attendancePolicy' => function ($query) {
                            $query->withoutGlobalScopes();
                        },
                        'employee.section' => function ($query) {
                            $query->withoutGlobalScopes();
                        },
                        'employee.category' => function ($query) {
                            $query->withoutGlobalScopes();
                        }
                    ])
                ->get();

            $userIds = $relatedEmployments->pluck('id');

            $workHistory = \App\Models\EmployeeWorkHistory::whereIn('employee_id', $userIds)
                ->with(['skills', 'creator', 'branch'])
                ->orderBy('start_date', 'desc')
                ->get();
        }

        return Inertia::render('hr/employees/show', [
            'employee' => $user,
            'workHistory' => $workHistory,
            'relatedEmployments' => $relatedEmployments,
            'employeeSalary' => \App\Models\EmployeeSalary::where('employee_id', $user->id)->first(),
            'salaryComponents' => \App\Models\SalaryComponent::where('created_by', $user->creatorId())->get(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        // Check if employee belongs to current company
        $companyUserIds = getCompanyAndUsersId();
        if (!in_array($employee->created_by, $companyUserIds)) {
            return redirect()->back()->with('error', __('You do not have permission to edit this employee'));
        }

        // Load user with employee relationships
        $user = User::with(['employee.branch', 'employee.department', 'employee.designation', 'employee.documents.documentType', 'employee.nominees'])
            ->where('id', $employee->user_id)
            ->first();

        $activeBranchId = $employee->branch_id ?? session('active_branch_id');

        $departmentsQuery = Department::with('branch')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId) {
            $departmentsQuery->where('branch_id', $activeBranchId);
        }
        $departments = $departmentsQuery->get(['id', 'name', 'branch_id']);

        $designationsQuery = Designation::with('department')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId) {
            $designationsQuery->where('branch_id', $activeBranchId);
        }
        $designations = $designationsQuery->get(['id', 'name', 'department_id']);

        $documentTypes = DocumentType::whereIn('created_by', getCompanyAndUsersId())
            ->get(['id', 'name', 'is_required']);

        $shiftsQuery = \App\Models\Shift::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId) {
            $shiftsQuery->where('branch_id', $activeBranchId);
        }
        $shifts = $shiftsQuery->get(['id', 'name', 'branch_id']);

        $attendancePoliciesQuery = \App\Models\AttendancePolicy::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId) {
            $attendancePoliciesQuery->where('branch_id', $activeBranchId);
        }
        $attendancePolicies = $attendancePoliciesQuery->get(['id', 'name', 'branch_id']);

        $branchForCategories = $employee->branch_id ?? $activeBranchId;

        $skillsQuery = \App\Models\Skill::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 1);
        if ($branchForCategories) {
            $skillsQuery->where('branch_id', $branchForCategories);
        }
        $skills = $skillsQuery->orderBy('name')->get(['id', 'name']);

        $sectionsQuery = \App\Models\Section::withoutGlobalScopes()->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($branchForCategories) {
            $sectionsQuery->where('branch_id', $branchForCategories);
        }
        $sections = $sectionsQuery->get(['id', 'name']);

        $categories = $branchForCategories
            ? $this->categoriesForBranch((int) $branchForCategories)
            : [];

        if ($user->employee && $branchForCategories) {
            $user->employee->category_id = $this->resolveCategoryIdForBranch(
                $user->employee->category_id,
                (int) $branchForCategories
            );
        }

        $banks = \App\Models\BankMaster::where('status', 'active')
            ->get(['id', 'bank_name', 'ifsc_code', 'branch_name']);

        $pfs = \App\Models\PfMaster::where('status', 'active')
            ->get(['id', 'name', 'percentage_employee', 'percentage_employer']);

        $esis = \App\Models\EsiMaster::where('status', 'active')
            ->get(['id', 'name', 'percentage_employee', 'percentage_employer']);
        $branches = Branch::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id', 'name']);
        $resignReasons = \App\Models\ResignReason::where('is_active', true)->get();
        $overtimeOptions = \App\Models\Overtime::where('is_active', true)->get();

        $salaryComponents = $this->salaryComponentsForBranch(
            (int) ($employee->branch_id ?? session('active_branch_id') ?? 0) ?: null
        );

        $employeeSalary = \App\Models\EmployeeSalary::where('employee_id', $employee->user_id)
            ->where('is_active', true)
            ->first();

        return inertia('hr/employees/edit', [
            'employee' => $user,
            'departments' => $departments,
            'designations' => $designations,
            'sections' => $sections,
            'categories' => $categories,
            'banks' => $banks,
            'pfs' => $pfs,
            'esis' => $esis,
            'documentTypes' => $documentTypes,
            'shifts' => $shifts,
            'attendancePolicies' => $attendancePolicies,
            'skills' => $skills,
            'branches' => $branches,
            'salaryComponents' => $salaryComponents,
            'employeeSalary' => $employeeSalary,
            'resignReasons' => $resignReasons,
            'overtimeOptions' => $overtimeOptions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        // Check if employee belongs to current company
        $companyUserIds = getCompanyAndUsersId();
        if (!in_array($employee->created_by, $companyUserIds)) {
            return redirect()->back()->with('error', __('You do not have permission to update this employee'));
        }

        try {
            \Log::info('Employee Update Request (ID: ' . $employee->id . '):', $request->all());
            if ($request->has('documents') && is_string($request->documents)) {
                $request->merge(['documents' => json_decode($request->documents, true)]);
            }
            if ($request->has('existing_documents') && is_string($request->existing_documents)) {
                $request->merge(['existing_documents' => json_decode($request->existing_documents, true)]);
            }
            if ($request->has('skill_id') && is_string($request->skill_id)) {
                $request->merge(['skill_id' => json_decode($request->skill_id, true)]);
            }
            if ($request->has('extra_salary_component_ids') && is_string($request->extra_salary_component_ids)) {
                $request->merge(['extra_salary_component_ids' => json_decode($request->extra_salary_component_ids, true)]);
            }

            // Validate basic information
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'employee_id' => [
                    'required',
                    'string',
                    'max:255',
                    \Illuminate\Validation\Rule::unique('employees')->where(function ($query) use ($request) {
                        return $query->whereIn('created_by', getCompanyAndUsersId());
                    })->ignore($employee->id)
                ],
                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                    function ($attribute, $value, $fail) use ($request, $employee) {
                        if (!$value)
                            return;
                        // Check uniqueness company-wide
                        $exists = \App\Models\User::where('email', $value)
                            ->where('id', '!=', $employee->user_id)
                            ->whereIn('created_by', getCompanyAndUsersId())
                            ->exists();

                        if ($exists) {
                            $fail(__('The email has already been taken in this company.'));
                        }
                    },
                ],
                'phone' => 'nullable|digits:10',
                'phone_2' => 'nullable|digits:10',
                'gender' => 'required|in:male,female,other',
                'marital_status' => 'nullable|string|max:20',
                'date_of_birth' => 'required|date',
                'wedding_date' => 'nullable|date',
                'password' => 'nullable|string|min:8',
                'profile_image' => 'nullable|max:2048',
                'shift_id' => [
                    'nullable',
                    'exists:shifts,id',
                ],
                'attendance_policy_id' => 'nullable|exists:attendance_policies,id',

                // Employment details
                // branch_id handled by existing value (no move allowed)
                'department_id' => 'required|exists:departments,id',
                'designation_id' => 'required|exists:designations,id',
                'section_id' => 'required|exists:sections,id',
                'category_id' => 'required|exists:categories,id',
                'date_of_joining' => 'required|date',
                'employment_type' => 'nullable|string|max:50',
                'po_status' => 'required|in:Permanent,Other',
                'daily_option' => 'required|boolean',
                'working_days' => 'nullable|integer',
                'weight' => 'nullable|numeric|min:0',
                'employment_status' => 'nullable|string|max:50',
                'week_off' => 'nullable|max:255',

                // Biometric/Common IDs
                'essl_id' => 'nullable|string|max:255',
                'common_id' => 'nullable|string|max:255',
                'emy_code' => 'nullable|string|max:255',

                'aadhar_card_number' => 'nullable|digits:12',
                'pan_card_number' => 'nullable|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
                'driving_license' => 'nullable|string|max:50',
                'blood_group' => 'nullable|string|max:10',
                'lunch_time' => 'nullable|string|max:20',

                // Contact information
                'address_line_1' => 'nullable|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'emergency_contact_name' => 'nullable|string|max:255',
                'emergency_contact_relationship' => 'nullable|string|max:100',
                'emergency_contact_number' => 'nullable|strict_phone',
                'emergency_contact_address' => 'nullable|string|max:255',

                // Banking information
                'bank_name' => 'nullable|string|max:255',
                'account_holder_name' => 'nullable|string|max:255',
                'account_number' => 'nullable|string|max:50',
                'bank_identifier_code' => 'nullable|string|max:50',
                'ifsc_code' => 'nullable|string|max:50',
                'account_type' => 'nullable|string|max:20',
                'bank_branch' => 'nullable|string|max:255',
                'bank_id' => 'nullable|exists:bank_masters,id',
                'tax_payer_id' => 'nullable|string|max:50',

                // Salary & Loan
                'basic_salary' => 'nullable|numeric|min:0',
                'lta_allowance' => 'nullable|numeric|min:0',
                'hra_allowance' => 'nullable|numeric|min:0',
                'pt_deduction' => 'nullable|numeric|min:0',
                'other_allowance' => 'nullable|numeric|min:0',
                'medical_allowance' => 'nullable|numeric|min:0',
                'pf_id' => 'nullable|exists:pf_masters,id',
                'esi_id' => 'nullable|exists:esi_masters,id',
                'loan_total_amount' => 'nullable|numeric|min:0',
                'loan_installment_amount' => 'nullable|numeric|min:0',
                'loan_period' => 'nullable|string|max:255',
                'nominee_name' => 'nullable|string|max:255',
                'nominee_account_number' => 'nullable|string|max:50',
                'nominee_aadhar' => 'nullable|digits:12',

                // Flags
                'ptax_flag' => 'nullable|boolean',
                'bonus_flag' => 'nullable|boolean',
                'ot_flag' => 'nullable|boolean',
                'ot_hours' => 'required_if:ot_flag,1,true|nullable|string|max:20',
                'hod_flag' => 'nullable|boolean',

                // Additional Allowances & OT
                'education_allowance' => 'nullable|numeric|min:0',
                'conveyance_allowance' => 'nullable|numeric|min:0',
                'special_allowance' => 'nullable|numeric|min:0',
                'ot_rate' => 'nullable|numeric|min:0',

                // Documents
                'documents' => 'nullable|array',
                'documents.*.document_type_id' => 'required|exists:document_types,id',
                'documents.*.file_path' => 'required|string',
                'documents.*.expiry_date' => 'nullable|date',
                'documents.*.id_number' => 'nullable|string|max:255',

                // Existing Documents Update
                'existing_documents' => 'nullable|array',
                'existing_documents.*.id' => 'required|exists:employee_documents,id',
                'existing_documents.*.expiry_date' => 'nullable|date',
                'existing_documents.*.id_number' => 'nullable|string|max:255',

                // Additional Details
                'skill_id' => 'nullable|array',
                'skill_id.*' => 'exists:skills,id',
                // 'pf_number' => 'nullable|string|max:50',
                'uan_number' => 'nullable|string|max:50',
                'esic_number' => 'nullable|string|max:50',
            ], [
                'name.required' => __('Please enter the employee name.'),
                'date_of_birth.required' => __('Date of birth is required.'),
                'gender.required' => __('Please select a gender.'),
                'department_id.required' => __('Please select a department.'),
                'designation_id.required' => __('Please select a designation.'),
                'date_of_joining.required' => __('Date of joining is required.'),
                'address_line_1.required' => __('Primary address is required.'),
                'city.required' => __('City is required.'),
                'state.required' => __('State is required.'),
                'country.required' => __('Country is required.'),
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            // Update User
            $user = $employee->user;
            $user->name = $request->name;
            if ($request->filled('email')) {
                $user->email = $request->email;
            }
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            // Handle Profile Image
            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->storeAs('media', $filename, 'public');
                $user->avatar = $filename;
            } elseif ($request->filled('avatar')) {
                $user->avatar = $request->avatar;
            }
            $user->save();

            // Update Employee
            $employee->employee_id = $request->employee_id;
            $employee->emy_code = $request->emy_code ?? $request->employee_id;
            $employee->essl_id = $request->essl_id;
            $employee->common_id = $request->common_id;
            $employee->phone = $request->phone;
            $employee->phone_2 = $request->phone_2;
            $employee->gender = $request->gender;
            $employee->marital_status = $request->marital_status;
            $employee->date_of_birth = $request->date_of_birth;
            $employee->wedding_date = $request->wedding_date;
            $employee->father_name = $request->father_name;
            $employee->department_id = $request->department_id;
            $employee->designation_id = $request->designation_id;
            $employee->branch_id = $request->branch_id ?? $employee->branch_id;
            $employee->section_id = $request->section_id;
            $employee->category_id = $this->resolveCategoryIdForBranch($request->category_id, $employee->branch_id);
            $employee->shift_id = $request->shift_id;
            $employee->attendance_policy_id = $request->attendance_policy_id;
            $employee->date_of_joining = $request->date_of_joining;
            $employee->employment_type = $request->employment_type;
            $employee->po_status = $request->po_status;
            $employee->daily_option = $request->daily_option;
            $employee->working_days = $request->working_days ?? 26;

            // Address & Personal
            $employee->address_line_1 = $request->address_line_1;
            $employee->address_line_2 = $request->address_line_2;
            $employee->city = $request->city;
            $employee->state = $request->state;
            $employee->country = $request->country ?? 'India';
            $employee->postal_code = $request->postal_code;
            $employee->education = $request->education;
            $employee->experience = $request->experience;
            $employee->aadhar_card_number = $request->aadhar_card_number;
            $employee->pan_card_number = $request->pan_card_number;
            $employee->driving_license = $request->driving_license ?? $request->driving_licence;
            $employee->blood_group = $request->blood_group;
            $employee->height = $request->height;
            $employee->weight = $request->weight;
            $employee->lunch_time = $request->lunch_time;
            $employee->week_off_type = $request->week_off_type ?? 'weekly';
            $employee->week_off = is_array($request->week_off) ? implode(',', $request->week_off) : $request->week_off;

            // Emergency Contact
            $employee->emergency_contact_name = $request->emergency_contact_name;
            $employee->emergency_contact_relationship = $request->emergency_contact_relationship;
            $employee->emergency_contact_number = $request->emergency_contact_number ?? $request->phone_2;
            $employee->emergency_contact_address = $request->emergency_contact_address;

            // Banking
            $employee->bank_name = $request->bank_name;
            $employee->account_holder_name = $request->account_holder_name;
            $employee->account_number = $request->account_number;
            $employee->ifsc_code = $request->ifsc_code ?? $request->bank_identifier_code;
            $employee->bank_identifier_code = $request->bank_identifier_code ?? $request->ifsc_code;
            $employee->bank_type = $request->account_type ?? $request->bank_type;
            $employee->bank_id = $request->bank_id;
            $employee->bank_branch = $request->bank_branch;

            // Statutory
            $employee->pf_number = $request->pf_number;
            $employee->uan_number = $request->uan_number;
            $employee->esic_number = $request->esic_number;
            $employee->pf_id = $request->pf_id;
            $employee->esi_id = $request->esi_id;
            $employee->pf_flag = $request->pf_flag ?? false;
            $employee->esic_flag = $request->esic_flag ?? false;
            $employee->ptax_flag = $request->ptax_flag ?? false;
            $employee->bonus_flag = $request->bonus_flag ?? false;
            $employee->ot_flag = $request->ot_flag ?? false;
            $employee->ot_hours = ($employee->ot_flag && filled($request->ot_hours)) ? $request->ot_hours : null;
            $employee->ot_type = $employee->ot_hours ? $request->ot_type : null;
            $employee->hod_flag = $request->hod_flag ?? false;
            $employee->skill_id = $this->normalizeSkillIds($request->skill_id);

            // Salary & Loan
            $employee->basic_salary = $request->basic_salary ?? 0;
            $employee->gross_salary = $request->gross_salary ?? 0;
            $employee->loan_total_amount = $request->loan_total_amount ?? 0;
            $employee->loan_installment_amount = $request->loan_installment_amount ?? 0;
            $employee->loan_period = $request->loan_period;
            $employee->extra_salary_component_ids = $this->normalizeExtraSalaryComponentIds(
                $request,
                (int) ($employee->branch_id ?? 0) ?: null
            );

            $employee->employment_status = $request->employment_status ?? 'active';
            $employee->resign_date = $request->resign_date;
            $employee->resign_reason_id = $request->resign_reason_id;
            $employee->confirm_date = $request->confirm_date;
            $employee->it_amount = $request->it_amount ?? 0;

            $employee->save();

            // Refine profile image naming
            if ($user->avatar) {
                $user->avatar = $this->handleProfileImage($user, $employee, $user->avatar);
                $user->save();
            }

            // Handle Nominees
            if ($request->has('nominees') && is_array($request->nominees)) {
                \App\Models\EmployeeNominee::where('employee_id', $user->id)->delete();
                foreach ($request->nominees as $nominee) {
                    if (!empty($nominee['name'])) {
                        \App\Models\EmployeeNominee::create([
                            'employee_id' => $user->id,
                            'name' => $nominee['name'],
                            'aadhar_number' => $nominee['aadhar_number'] ?? null,
                            'relation' => $nominee['relation'] ?? null,
                            'percentage' => $nominee['percentage'] ?? 0,
                        ]);
                    }
                }
            }

            // Handle Documents
            if ($request->has('documents')) {
                $this->saveEmployeeDocuments($employee, $request->documents);
            }

            // Salary Record
            if ($request->has('salary_components')) {
                \App\Models\EmployeeSalary::updateOrCreate(
                    ['employee_id' => $employee->user_id],
                    [
                        'basic_salary' => $request->basic_salary ?? 0,
                        'pf_basic_salary' => $request->pf_basic_salary ?? 0,
                        'gross_salary' => $request->gross_salary ?? 0,
                        'components' => $request->salary_components,
                        'is_active' => true,
                        'created_by' => creatorId(),
                    ]
                );
            }

            ActivityLogger::logEmployee($employee->fresh(), 'updated');

            return redirect()->route('hr.employees.index')->with('success', __('Employee updated successfully'));
        } catch (\Exception $e) {
            \Log::error('Employee update failed: ' . $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($userId)
    {
        try {
            $user = User::with('employee')->where('id', $userId)->whereIn('created_by', getCompanyAndUsersId())->first();

            if (!$user || !$user->employee) {
                return redirect()->back()->with('error', __('Employee not found'));
            }

            $employee = $user->employee;

            ActivityLogger::logEmployee($employee, 'deleted');

            // Delete documents first
            EmployeeDocument::where('employee_id', $employee->id)->delete();

            // Delete employee record
            $employee->delete();

            // Delete user record and avatar
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->delete();

            return redirect()->route('hr.employees.index')->with('success', __('Employee deleted successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete employee: :message', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Update employee status.
     */
    public function toggleStatus(Employee $employee)
    {
        // Check if employee belongs to current company
        $companyUserIds = getCompanyAndUsersId();
        if (!in_array($employee->created_by, $companyUserIds)) {
            return redirect()->back()->with('error', __('You do not have permission to update this employee'));
        }

        try {
            $user = $employee->user;
            $newStatus = $user->status === 'active' ? 'inactive' : 'active';
            $user->update(['status' => $newStatus]);

            ActivityLogger::log(
                'Employee',
                'updated',
                sprintf(
                    '%s changed employee status to %s: %s',
                    auth()->user()->name,
                    $newStatus,
                    $employee->user?->name ?? $employee->employee_id
                ),
                $employee->branch_id
            );

            return redirect()->back()->with('success', __('Employee status updated successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update employee status'));
        }
    }

    /**
     * Change employee password.
     */
    public function changePassword(Request $request, Employee $employee)
    {
        // Check if employee belongs to current company
        $companyUserIds = getCompanyAndUsersId();
        if (!in_array($employee->created_by, $companyUserIds)) {
            return redirect()->back()->with('error', __('You do not have permission to change this employee password'));
        }

        try {
            $validated = $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = $employee->user;
            $user->password = Hash::make($validated['password']);
            $user->save();

            return redirect()->back()->with('success', __('Employee password changed successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to change employee password'));
        }
    }

    /**
     * Delete employee document.
     */
    public function deleteDocument($userId, $documentId)
    {
        $user = User::with('employee')->find($userId);

        if (!$user || !$user->employee) {
            return redirect()->back()->with('error', __('Employee not found'));
        }

        $companyUserIds = getCompanyAndUsersId();
        if (!in_array($user->created_by, $companyUserIds)) {
            return redirect()->back()->with('error', __('You do not have permission to access this employee'));
        }

        $document = EmployeeDocument::where('id', $documentId)
            ->where('employee_id', $userId)
            ->first();

        if (!$document) {
            return redirect()->back()->with('error', __('Document not found'));
        }

        try {
            $document->delete();
            return redirect()->back()->with('success', __('Document deleted successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete document'));
        }
    }

    /**
     * Approve employee document.
     */
    public function approveDocument($userId, $documentId)
    {
        $user = User::with('employee')->find($userId);
        if (!$user || !$user->employee) {
            return redirect()->back()->with('error', __('Employee not found'));
        }

        $document = EmployeeDocument::where('id', $documentId)
            ->where('employee_id', $userId)
            ->first();

        if (!$document) {
            return redirect()->back()->with('error', __('Document not found'));
        }

        try {
            $document->update(['verification_status' => 'verified']);
            return redirect()->back()->with('success', __('Document approved successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to approve document'));
        }
    }

    /**
     * Reject employee document.
     */
    public function rejectDocument($userId, $documentId)
    {
        $user = User::with('employee')->find($userId);
        if (!$user || !$user->employee) {
            return redirect()->back()->with('error', __('Employee not found'));
        }

        $document = EmployeeDocument::where('id', $documentId)
            ->where('employee_id', $userId)
            ->first();

        if (!$document) {
            return redirect()->back()->with('error', __('Document not found'));
        }

        try {
            $document->update(['verification_status' => 'rejected']);
            return redirect()->back()->with('success', __('Document rejected successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to reject document'));
        }
    }

    /**
     * Download employee document.
     */
    public function downloadDocument($userId, $documentId)
    {

        $user = User::with('employee')->find($userId);
        if (!$user || !$user->employee) {
            return redirect()->back()->with('error', __('Employee not found'));
        }

        $companyUserIds = getCompanyAndUsersId();
        if (!in_array($user->created_by, $companyUserIds)) {
            return redirect()->back()->with('error', __('You do not have permission to access this employee'));
        }

        $document = EmployeeDocument::where('id', $documentId)
            ->where('employee_id', $userId)
            ->first();


        if (!$document) {
            return redirect()->back()->with('error', __('Document not found'));
        }

        if (!$document->file_path) {
            return redirect()->back()->with('error', __('Document file not found'));
        }

        $filePath = getStorageFilePath($document->file_path);

        if (!file_exists($filePath)) {
            return redirect()->back()->with('error', __('Document file not found'));
        }

        return response()->download($filePath);
    }

    /**
     * Download sample employee import file.
     */

    /**
     * Import employees from Excel.
     */
    public function import(Request $request)
    {
        set_time_limit(0);

        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:xlsx,xls,csv'],
        ], [
            'file.required' => __('Please select a file to import.'),
            'file.file' => __('The upload failed. Please select a valid file and try again.'),
            'file.mimes' => __('Invalid file format. Only Excel (.xlsx, .xls) and CSV (.csv) files are allowed.'),
            'file.max' => __('The file is too large. Maximum allowed size is 20 MB.'),
        ]);

        $uploadedFile = $request->file('file');
        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());

        if (! in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            return redirect()->back()->with(
                'error',
                $this->employeeImportErrorMessage(
                    __('Invalid file type ":type". Please upload an Excel file (.xlsx, .xls) or CSV (.csv) only.', [
                        'type' => strtoupper($extension ?: 'unknown'),
                    ])
                )
            );
        }

        try {
            $import = \App\Services\ActivityLogger::withoutLogging(function () use ($uploadedFile) {
                $import = new \App\Imports\EmployeesImport;
                \Maatwebsite\Excel\Facades\Excel::import($import, $uploadedFile);

                return $import;
            });

            $failures = $import->failures();
            $savedCount = $import->rowsSaved;
            $failedCount = $failures->count();

            if ($savedCount === 0 && $failedCount === 0) {
                return redirect()->back()->with(
                    'error',
                    $this->employeeImportErrorMessage(
                        __('No employee rows were found in the file. Please add employee data below the header row (from row 2 onwards) and import again.')
                    )
                );
            }

            if ($failedCount > 0) {
                $msg = '<div class="space-y-1 text-sm">';
                $msg .= '<div class="font-bold text-gray-800 border-b pb-1 mb-2">Import Summary: ' . $savedCount . ' saved, ' . $failedCount . ' failed</div>';



                $msg .= '<div class="text-red-500 mt-2 font-semibold">✘ Failures:</div>';
                $msg .= '<ul class="list-disc pl-5 text-red-500 text-xs space-y-0.5">';
                foreach ($failures as $failure) {
                    $msg .= '<li>Row ' . $failure->row() . ': ' . implode(', ', $failure->errors()) . '</li>';
                }
                $msg .= '</ul>';
                $msg .= '</div>';

                return redirect()->back()->with('error', $msg);
            }

            return redirect()->back()->with('success', __('Employees imported successfully.'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $this->employeeImportErrorMessage($e->getMessage()));
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            \Log::error('Employee import read failed: ' . $e->getMessage());

            return redirect()->back()->with(
                'error',
                $this->employeeImportErrorMessage(
                    __('Unable to read the uploaded file. Please ensure it is a valid Excel (.xlsx, .xls) or CSV file that matches the sample template.')
                )
            );
        } catch (\Exception $e) {
            \Log::error('Import process failed: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            $message = $e->getMessage();
            if (str_contains(strtolower($message), 'no readers') || str_contains(strtolower($message), 'could not open')) {
                $message = __('The file format is not supported. Please upload a valid Excel (.xlsx, .xls) or CSV file.');
            }

            return redirect()->back()->with('error', $this->employeeImportErrorMessage($message));
        }
    }

    /**
     * Format import errors for display inside the import modal.
     */
    private function employeeImportErrorMessage(string $message): string
    {
        return '<div class="space-y-1 text-sm text-red-600 font-medium">' . e($message) . '</div>';
    }

    /**
     * Export employee profile.
     */
    public function export(User $user)
    {
        // Check permissions
        // if (!auth()->user()->can('view-employees')) { // Adjust permission as needed
        //      abort(403);
        // }

        $filename = \Illuminate\Support\Str::slug($user->name) . '_' . ($user->employee->employee_id ?? $user->id) . '_profile.pdf';

        $employee = \App\Models\Employee::with([
            'department',
            'designation',
            'branch',
            'shift',
            'category',
            'section',
            'nominees'
        ])->where('user_id', $user->id)->first();

        $branchName = "";
        if ($employee->branch) {
            $branchName = " - " . strtoupper($employee->branch->name);
        }
        $companyName = getSetting('titleText', 'KIRAN INDUSTRIES') . $branchName;

        $employeeSalary = \App\Models\EmployeeSalary::where('employee_id', $user->id)->first();
        $salaryComponents = \App\Models\SalaryComponent::where('created_by', $user->creatorId())->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::setOptions([
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'helvetica'
        ])->loadView('reports.single_employee_report', [
                    'employee' => $employee,
                    'user' => $user,
                    'companyName' => $companyName,
                    'employeeSalary' => $employeeSalary,
                    'salaryComponents' => $salaryComponents,
                ]);

        $pdf->setPaper('A4', 'portrait');

        // Render first to get canvas
        $pdf->render();
        $domPdf = $pdf->getDomPDF();
        $canvas = $domPdf->get_canvas();
        $fontBold = $domPdf->getFontMetrics()->getFont('Helvetica', 'bold');
        $fontNormal = $domPdf->getFontMetrics()->getFont('Helvetica', 'normal');

        // Page Number (White for dark header)
        $canvas->page_text(520, 35, "PAGE {PAGE_NUM} OF {PAGE_COUNT}", $fontBold, 8, [1, 1, 1]);

        /*
        |--------------------------------------------------------------------------
        | FOOTER LEFT : DEVELOPED BY & SUPPORT
        |--------------------------------------------------------------------------
        */
        $canvas->page_text(
            20,
            815,
            "Develop by Sridix Technology LLP",
            $fontNormal,
            7,
            [0.5, 0.5, 0.5]
        );

        /*
        |--------------------------------------------------------------------------
        | FOOTER CENTER : CONTINUED ON PAGE
        |--------------------------------------------------------------------------
        */
        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            if ($pageNumber < $pageCount) {
                $font = $fontMetrics->get_font("Helvetica", "bold");
                $canvas->text(
                    240,
                    815,
                    "Continued On Page No... " . ($pageNumber + 1),
                    $font,
                    7,
                    [0.4, 0.4, 0.4]
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | FOOTER RIGHT : PRINTED ON
        |--------------------------------------------------------------------------
        */
        $canvas->page_text(
            440,
            815,
            "Printed On : " . now()->format('d/m/Y H:i:s'),
            $fontNormal,
            7,
            [0.5, 0.5, 0.5]
        );

        return response($domPdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /**
     * Export Employee Master Report as PDF.
     */
    public function reportPdf(Request $request)
    {
        $branchId = $request->branch_id ?? session('active_branch_id');

        $departmentsQuery = \App\Models\Department::with([
            'employees' => function ($q) use ($branchId) {
                if ($branchId && $branchId !== 'all') {
                    $q->where('branch_id', $branchId);
                }
                $q->with(['user', 'designation', 'shift']);
                $q->orderBy('emy_code', 'asc');
            }
        ])->whereIn('created_by', getCompanyAndUsersId());

        if ($branchId && $branchId !== 'all') {
            $departmentsQuery->where('branch_id', $branchId);
        }

        $departments = $departmentsQuery->get();
        $branchName = "";
        $headerBranchId = $request->branch_id ?? session('active_branch_id');
        if ($headerBranchId && $headerBranchId !== 'all') {
            $activeBranch = \App\Models\Branch::find($headerBranchId);
            if ($activeBranch) {
                $branchName = " - " . strtoupper($activeBranch->name);
            }
        } elseif ($headerBranchId === 'all') {
            $branchName = " - ALL BRANCHES";
        }
        $companyName = getSetting('titleText', 'KIRAN INDUSTRIES') . $branchName;

        $filename = 'employee_master_report_' . date('Y-m-d') . '.pdf';
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::setOptions([
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'helvetica'
        ])->loadView('reports.employee_report', [
                    'departments' => $departments,
                    'companyName' => $companyName,
                ]);

        $pdf->setPaper('A4', 'portrait');
        // Render first to get canvas
        $pdf->render();
        $domPdf = $pdf->getDomPDF();
        $canvas = $domPdf->get_canvas();
        $fontBold = $domPdf->getFontMetrics()->getFont('Helvetica', 'bold');
        $fontNormal = $domPdf->getFontMetrics()->getFont('Helvetica', 'normal');

        // Page Number (White for dark header)
        $canvas->page_text(520, 35, "PAGE {PAGE_NUM} OF {PAGE_COUNT}", $fontBold, 8, [1, 1, 1]);

        /*
        |--------------------------------------------------------------------------
        | FOOTER LEFT : DEVELOPED BY & SUPPORT
        |--------------------------------------------------------------------------
        */
        $canvas->page_text(
            20,
            815,
            "Develop by Sridix Technology LLP",
            $fontNormal,
            7,
            [0.5, 0.5, 0.5]
        );

        /*
        |--------------------------------------------------------------------------
        | FOOTER CENTER : CONTINUED ON PAGE
        |--------------------------------------------------------------------------
        */
        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            if ($pageNumber < $pageCount) {
                $font = $fontMetrics->get_font("Helvetica", "bold");
                $canvas->text(
                    240,
                    815,
                    "Continued On Page No... " . ($pageNumber + 1),
                    $font,
                    7,
                    [0.4, 0.4, 0.4]
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | FOOTER RIGHT : PRINTED ON
        |--------------------------------------------------------------------------
        */
        $canvas->page_text(
            440,
            815,
            "Printed On : " . now()->format('d/m/Y H:i:s'),
            $fontNormal,
            7,
            [0.5, 0.5, 0.5]
        );

        return response($domPdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }
    /**
     * Get next employee ID based on category.
     */
    public function getNextId(Request $request)
    {
        try {
            $nextId = \App\Models\Employee::getNextEmployeeId();
            return response()->json([
                'success' => true,
                'next_id' => $nextId
            ]);
        } catch (\Exception $e) {
            \Log::error('Employee Store Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
    /**
     * Save employee documents with custom naming.
     */
    private function saveEmployeeDocuments($employee, $documents)
    {
        if (!is_array($documents))
            return;

        foreach ($documents as $docData) {
            if (empty($docData['file_path']) || empty($docData['document_type_id'])) {
                continue;
            }

            // Get document type name for renaming
            $docType = DocumentType::find($docData['document_type_id']);
            $typeName = $docType ? Str::slug($docType->name, '_') : 'document';

            $oldFilePath = $docData['file_path'];
            $newFilePath = $oldFilePath;

            // Rename logic
            try {
                $activeDisk = StorageConfigService::getActiveDisk();
                $extension = pathinfo($oldFilePath, PATHINFO_EXTENSION);
                $newFileName = $employee->emy_code . '_' . $typeName . '.' . $extension;

                $oldPath = 'media/' . $oldFilePath;
                $newPath = 'media/' . $newFileName;

                if (Storage::disk($activeDisk)->exists($oldPath)) {
                    // Avoid overwriting if possible or handle it
                    if (!Storage::disk($activeDisk)->exists($newPath) || $oldPath !== $newPath) {
                        Storage::disk($activeDisk)->move($oldPath, $newPath);
                        $newFilePath = $newFileName;

                        // Update Media table if it exists
                        \Spatie\MediaLibrary\MediaCollections\Models\Media::where('file_name', $oldFilePath)
                            ->update(['file_name' => $newFileName, 'name' => pathinfo($newFileName, PATHINFO_FILENAME)]);
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Failed to rename document: " . $e->getMessage());
            }

            if (isset($docData['id']) && !empty($docData['id'])) {
                $existingDoc = EmployeeDocument::find($docData['id']);
                if ($existingDoc) {
                    $existingDoc->update([
                        'document_type_id' => $docData['document_type_id'],
                        'file_path' => $newFilePath,
                        'expiry_date' => $docData['expiry_date'] ?? null,
                        'id_number' => $docData['id_number'] ?? null,
                    ]);
                }
            } else {
                EmployeeDocument::create([
                    'employee_id' => $employee->user_id,
                    'document_type_id' => $docData['document_type_id'],
                    'file_path' => $newFilePath,
                    'expiry_date' => $docData['expiry_date'] ?? null,
                    'id_number' => $docData['id_number'] ?? null,
                    'verification_status' => 'pending',
                    'created_by' => creatorId(),
                ]);
            }
        }
    }

    /**
     * Rename profile image with employee code.
     */
    private function handleProfileImage($user, $employee, $currentPath)
    {
        if (empty($currentPath))
            return $currentPath;

        try {
            $activeDisk = StorageConfigService::getActiveDisk();
            $prefix = $employee->emy_code ?: $employee->employee_id;
            $extension = pathinfo($currentPath, PATHINFO_EXTENSION);
            $newFileName = $prefix . '_profile.' . $extension;

            $oldPath = 'media/' . $currentPath;
            $newPath = 'media/' . $newFileName;

            if (Storage::disk($activeDisk)->exists($oldPath)) {
                if ($oldPath !== $newPath) {
                    // Check if target already exists and delete if it's different file
                    if (Storage::disk($activeDisk)->exists($newPath)) {
                        Storage::disk($activeDisk)->delete($newPath);
                    }

                    Storage::disk($activeDisk)->move($oldPath, $newPath);

                    // Update Media table
                    \Spatie\MediaLibrary\MediaCollections\Models\Media::where('file_name', $currentPath)
                        ->update(['file_name' => $newFileName, 'name' => pathinfo($newFileName, PATHINFO_FILENAME)]);

                    return $newFileName;
                }
            }
        } catch (\Exception $e) {
            \Log::error("Failed to rename profile image: " . $e->getMessage());
        }

        return $currentPath;
    }

    /**
     * Branch-scoped categories — one row per name (no duplicate STAFF from other branches).
     */
    public function branchMasters(Request $request)
    {
        $request->validate(['branch_id' => 'required|integer']);
        $branchId = (int) $request->branch_id;
        $companyIds = getCompanyAndUsersId();

        $departments = Department::withoutGlobalScopes()
            ->whereIn('created_by', $companyIds)
            ->where('status', 'active')
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id']);

        $sections = \App\Models\Section::withoutGlobalScopes()
            ->whereIn('created_by', $companyIds)
            ->where('status', 'active')
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $designations = \App\Models\Designation::withoutGlobalScopes()
            ->whereIn('created_by', $companyIds)
            ->where('status', 'active')
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);

        $shifts = \App\Models\Shift::withoutGlobalScopes()
            ->whereIn('created_by', $companyIds)
            ->where('status', 'active')
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $skills = \App\Models\Skill::whereIn('created_by', $companyIds)
            ->where('status', 1)
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'categories' => $this->categoriesForBranch($branchId),
            'departments' => $departments,
            'sections' => $sections,
            'designations' => $designations,
            'shifts' => $shifts,
            'skills' => $skills,
        ]);
    }

    private function categoriesForBranch(int $branchId): array
    {
        if (!$branchId) {
            return [];
        }

        return Category::withoutGlobalScopes()
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->unique(fn($cat) => strtoupper(trim($cat->name)))
            ->values()
            ->all();
    }

    /**
     * Persist skill_id JSON column from form (array, string, or single id).
     */
    private function normalizeSkillIds(mixed $skillId): ?array
    {
        if ($skillId === null || $skillId === '' || $skillId === []) {
            return null;
        }

        if (is_string($skillId)) {
            $decoded = json_decode($skillId, true);
            $skillId = is_array($decoded) ? $decoded : [$skillId];
        }

        if (!is_array($skillId)) {
            $skillId = [$skillId];
        }

        $ids = array_values(array_filter(array_map('intval', $skillId)));

        return $ids ?: null;
    }

    /**
     * Active salary components for a branch (primary + custom).
     */
    private function salaryComponentsForBranch(?int $branchId)
    {
        $query = \App\Models\SalaryComponent::where('status', 'active')
            ->whereIn('created_by', getCompanyAndUsersId());

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get([
            'id', 'name', 'type', 'calculation_type', 'default_amount',
            'percentage_of_basic', 'percentage_of_gross_pay', 'component_group', 'assign_to_all',
        ]);
    }

    private function normalizeExtraSalaryComponentIds(Request $request, ?int $branchId): array
    {
        $extraIds = $request->input('extra_salary_component_ids', []);
        if (is_string($extraIds)) {
            $extraIds = json_decode($extraIds, true) ?? [];
        }
        if (! is_array($extraIds)) {
            return [];
        }

        return app(SalaryComponentAssignmentService::class)
            ->validateExtraComponentIds(collect($this->salaryComponentsForBranch($branchId)), $extraIds);
    }

    /**
     * Map category to the employee's branch (same name, correct branch_id).
     */
    private function resolveCategoryIdForBranch(?int $categoryId, ?int $branchId): ?int
    {
        if (!$categoryId || !$branchId) {
            return $categoryId;
        }

        $cat = \App\Models\Category::withoutGlobalScopes()->find($categoryId);
        if (!$cat) {
            return $categoryId;
        }

        $local = \App\Models\Category::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('name', $cat->name)
            ->first();

        return $local?->id ?? $categoryId;
    }

    /**
     * Download the template for importing employees.
     */
    public function downloadSample()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\EmployeesTemplateExport, 'employees_template.xlsx');
    }
}


