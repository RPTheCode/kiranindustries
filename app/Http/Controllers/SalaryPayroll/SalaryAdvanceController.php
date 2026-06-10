<?php

namespace App\Http\Controllers\SalaryPayroll;

use App\Http\Controllers\Controller;
use App\Models\SalaryPayroll\SalaryAdvanceRequest;
use App\Models\User;
use App\Services\SalaryPayroll\AmountInWordsService;
use App\Services\SalaryPayroll\SalaryAdvanceEligibilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalaryAdvanceController extends Controller
{
    public function __construct(
        private SalaryAdvanceEligibilityService $eligibilityService,
        private AmountInWordsService $amountInWords
    ) {}

    public function index(Request $request)
    {
        $branchId = session('active_branch_id');

        $query = SalaryAdvanceRequest::query()
            ->with(['employee.employee.department', 'employee.employee.designation', 'approver'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->orderByDesc('application_date')
            ->orderByDesc('id');

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->where('employee_id', 'like', "%{$search}%")
                            ->orWhere('emy_code', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('month_year')) {
            $query->where('month_year', $request->month_year);
        }

        $advances = $query->paginate($request->integer('per_page', 15))->withQueryString();

        return Inertia::render('hr/salary-payroll/salary-advances/index', [
            'advances' => $advances,
            'filters' => $request->only(['search', 'status', 'month_year', 'per_page']),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create()
    {
        return Inertia::render('hr/salary-payroll/salary-advances/form', [
            'advance' => null,
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $employee = User::with('employee')->findOrFail($validated['employee_id']);
        $applicationDate = Carbon::parse($validated['application_date']);
        $branchId = $this->resolveBranchId($validated['branch_id'] ?? null, $employee);

        try {
            $this->eligibilityService->validateAmount(
                $employee,
                (float) $validated['requested_amount'],
                $applicationDate,
                $branchId !== 'all' ? (int) $branchId : null
            );
        } catch (\InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'requested_amount' => $e->getMessage(),
            ]);
        }

        $eligibility = $this->eligibilityService->forEmployee(
            $employee,
            $applicationDate,
            $branchId !== 'all' ? (int) $branchId : null
        );

        $advance = DB::transaction(function () use ($validated, $employee, $applicationDate, $branchId, $eligibility) {
            return SalaryAdvanceRequest::create([
                'employee_id' => $employee->id,
                'branch_id' => $branchId !== 'all' ? $branchId : ($employee->employee?->branch_id),
                'application_date' => $applicationDate->toDateString(),
                'advance_type' => $validated['advance_type'] ?? SalaryAdvanceRequest::TYPE_GENERAL,
                'requested_amount' => $validated['requested_amount'],
                'approved_amount' => $validated['requested_amount'],
                'amount_in_words' => $this->amountInWords->rupees((float) $validated['requested_amount']),
                'purpose' => $validated['purpose'],
                'present_salary_snapshot' => $eligibility['present_salary'],
                'earned_salary_snapshot' => $eligibility['earned_salary'],
                'allowed_amount_snapshot' => $eligibility['allowed_amount'],
                'month_year' => $applicationDate->format('Y-m'),
                'status' => $validated['submit'] ?? false ? SalaryAdvanceRequest::STATUS_SUBMITTED : SalaryAdvanceRequest::STATUS_DRAFT,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        return redirect()
            ->route('hr.salary-advances.show', $advance)
            ->with('success', __('Salary advance request saved successfully.'));
    }

    public function show(SalaryAdvanceRequest $salaryAdvanceRequest)
    {
        $this->authorizeRequest($salaryAdvanceRequest);

        $salaryAdvanceRequest->load(['employee.employee.department', 'employee.employee.designation', 'employee.employee.branch', 'creator', 'approver', 'branch']);

        return Inertia::render('hr/salary-payroll/salary-advances/show', [
            'advance' => $this->transformAdvance($salaryAdvanceRequest),
        ]);
    }

    public function edit(SalaryAdvanceRequest $salaryAdvanceRequest)
    {
        $this->authorizeRequest($salaryAdvanceRequest);

        if (! $salaryAdvanceRequest->isEditable()) {
            return redirect()
                ->route('hr.salary-advances.show', $salaryAdvanceRequest)
                ->with('error', __('This advance request cannot be edited.'));
        }

        return Inertia::render('hr/salary-payroll/salary-advances/form', [
            'advance' => $this->transformAdvance($salaryAdvanceRequest),
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function update(Request $request, SalaryAdvanceRequest $salaryAdvanceRequest)
    {
        $this->authorizeRequest($salaryAdvanceRequest);

        if (! $salaryAdvanceRequest->isEditable()) {
            return back()->with('error', __('This advance request cannot be edited.'));
        }

        $validated = $this->validateRequest($request, $salaryAdvanceRequest);
        $employee = User::with('employee')->findOrFail($salaryAdvanceRequest->employee_id);
        $applicationDate = Carbon::parse($validated['application_date']);
        $branchId = $salaryAdvanceRequest->branch_id;

        try {
            $this->eligibilityService->validateAmount(
                $employee,
                (float) $validated['requested_amount'],
                $applicationDate,
                $branchId,
                $salaryAdvanceRequest->id
            );
        } catch (\InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'requested_amount' => $e->getMessage(),
            ]);
        }

        $eligibility = $this->eligibilityService->forEmployee($employee, $applicationDate, $branchId, null, $salaryAdvanceRequest->id);

        DB::transaction(function () use ($salaryAdvanceRequest, $validated, $applicationDate, $eligibility) {
            $salaryAdvanceRequest->update([
                'application_date' => $applicationDate->toDateString(),
                'advance_type' => $validated['advance_type'] ?? $salaryAdvanceRequest->advance_type ?? SalaryAdvanceRequest::TYPE_GENERAL,
                'requested_amount' => $validated['requested_amount'],
                'approved_amount' => $validated['requested_amount'],
                'amount_in_words' => $this->amountInWords->rupees((float) $validated['requested_amount']),
                'purpose' => $validated['purpose'],
                'present_salary_snapshot' => $eligibility['present_salary'],
                'earned_salary_snapshot' => $eligibility['earned_salary'],
                'allowed_amount_snapshot' => $eligibility['allowed_amount'],
                'month_year' => $applicationDate->format('Y-m'),
                'status' => ($validated['submit'] ?? false)
                    ? SalaryAdvanceRequest::STATUS_SUBMITTED
                    : $salaryAdvanceRequest->status,
                'remarks' => $validated['remarks'] ?? null,
            ]);
        });

        return redirect()
            ->route('hr.salary-advances.show', $salaryAdvanceRequest)
            ->with('success', __('Salary advance request updated successfully.'));
    }

    public function destroy(SalaryAdvanceRequest $salaryAdvanceRequest)
    {
        $this->authorizeRequest($salaryAdvanceRequest);

        if (! in_array($salaryAdvanceRequest->status, [
            SalaryAdvanceRequest::STATUS_DRAFT,
            SalaryAdvanceRequest::STATUS_SUBMITTED,
            SalaryAdvanceRequest::STATUS_REJECTED,
            SalaryAdvanceRequest::STATUS_CANCELLED,
        ], true)) {
            return back()->with('error', __('Only draft, submitted, rejected or cancelled requests can be deleted.'));
        }

        $salaryAdvanceRequest->delete();

        return redirect()
            ->route('hr.salary-advances.index')
            ->with('success', __('Salary advance request deleted.'));
    }

    public function approve(SalaryAdvanceRequest $salaryAdvanceRequest)
    {
        $this->authorizeRequest($salaryAdvanceRequest);

        if (! in_array($salaryAdvanceRequest->status, [SalaryAdvanceRequest::STATUS_SUBMITTED, SalaryAdvanceRequest::STATUS_DRAFT], true)) {
            return back()->with('error', __('This request cannot be approved.'));
        }

        $salaryAdvanceRequest->update([
            'status' => SalaryAdvanceRequest::STATUS_APPROVED,
            'approved_amount' => $salaryAdvanceRequest->approved_amount ?? $salaryAdvanceRequest->requested_amount,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', __('Advance request approved.'));
    }

    public function disburse(SalaryAdvanceRequest $salaryAdvanceRequest)
    {
        $this->authorizeRequest($salaryAdvanceRequest);

        if ($salaryAdvanceRequest->status !== SalaryAdvanceRequest::STATUS_APPROVED) {
            return back()->with('error', __('Only approved requests can be disbursed.'));
        }

        $salaryAdvanceRequest->update([
            'status' => SalaryAdvanceRequest::STATUS_DISBURSED,
            'disbursement_date' => now()->toDateString(),
            'paid_amount' => 0,
            'approved_amount' => $salaryAdvanceRequest->approved_amount ?? $salaryAdvanceRequest->requested_amount,
        ]);

        return back()->with('success', __('Advance disbursed. Recovery will apply in Generate Payroll.'));
    }

    public function printBlank()
    {
        return view('exports.salary-advance-form', [
            'blank' => true,
            'advance' => null,
            'companyName' => $this->printCompanyTitle(),
            'printDate' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    public function print(SalaryAdvanceRequest $salaryAdvanceRequest)
    {
        $this->authorizeRequest($salaryAdvanceRequest);
        $salaryAdvanceRequest->load(['employee.employee.department', 'employee.employee.designation', 'employee.employee.branch', 'creator', 'approver', 'branch']);

        $companyTitle = $this->printCompanyTitle(
            $salaryAdvanceRequest->branch?->name
                ?? $salaryAdvanceRequest->employee?->employee?->branch?->name
        );

        return view('exports.salary-advance-form', [
            'blank' => false,
            'advance' => $salaryAdvanceRequest,
            'companyName' => $companyTitle,
            'printDate' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    private function printCompanyTitle(?string $branchName = null): string
    {
        $branchName = $branchName
            ?? (($branchId = session('active_branch_id')) && $branchId !== 'all'
                ? \App\Models\Branch::find($branchId)?->name
                : null);

        $companyTitle = strtoupper(getSetting('titleText', 'KIRAN INDUSTRIES PVT LTD.'));
        if ($branchName) {
            $companyTitle .= ' - '.strtoupper($branchName);
        }

        return $companyTitle;
    }

    public function eligibility(Request $request, int $userId)
    {
        $employee = User::with('employee')->where('type', 'employee')->findOrFail($userId);
        $date = Carbon::parse($request->query('date', now()->toDateString()));
        $branchId = session('active_branch_id');
        $branchId = ($branchId && $branchId !== 'all') ? (int) $branchId : $employee->employee?->branch_id;

        return response()->json(
            $this->eligibilityService->forEmployee($employee, $date, $branchId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequest(Request $request, ?SalaryAdvanceRequest $existing = null): array
    {
        return $request->validate([
            'employee_id' => [$existing ? 'prohibited' : 'required', 'exists:users,id'],
            'application_date' => ['required', 'date'],
            'advance_type' => ['nullable', 'in:type_20,type_25,general'],
            'requested_amount' => ['required', 'numeric', 'min:1'],
            'purpose' => ['required', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'submit' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeRequest(SalaryAdvanceRequest $advance): void
    {
        if (! in_array($advance->created_by, getCompanyAndUsersId())) {
            abort(403);
        }
    }

    private function resolveBranchId(mixed $branchId, User $employee): mixed
    {
        $sessionBranch = session('active_branch_id');

        if ($sessionBranch && $sessionBranch !== 'all') {
            return $sessionBranch;
        }

        return $branchId ?? $employee->employee?->branch_id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function employeeOptions(): array
    {
        $branchId = session('active_branch_id');

        $query = User::withPermissionCheck()
            ->where('type', 'employee')
            ->with(['employee' => fn ($q) => $q->withoutGlobalScopes()->select('user_id', 'employee_id', 'branch_id')]);

        if ($branchId && $branchId !== 'all') {
            $query->whereHas('employee', fn ($q) => $q->withoutGlobalScopes()->where('branch_id', $branchId));
        }

        return $query->orderBy('name')->get(['id', 'name'])->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name.' - '.($user->employee->employee_id ?? ''),
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            SalaryAdvanceRequest::STATUS_DRAFT => __('Draft'),
            SalaryAdvanceRequest::STATUS_SUBMITTED => __('Submitted'),
            SalaryAdvanceRequest::STATUS_APPROVED => __('Approved'),
            SalaryAdvanceRequest::STATUS_DISBURSED => __('Disbursed'),
            SalaryAdvanceRequest::STATUS_RECOVERING => __('Recovering'),
            SalaryAdvanceRequest::STATUS_RECOVERED => __('Recovered'),
            SalaryAdvanceRequest::STATUS_REJECTED => __('Rejected'),
            SalaryAdvanceRequest::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformAdvance(SalaryAdvanceRequest $advance): array
    {
        $emp = $advance->employee?->employee;

        return [
            'id' => $advance->id,
            'employee_id' => $advance->employee_id,
            'employee_name' => $advance->employee?->name,
            'employee_code' => $emp?->employee_id ?? $emp?->emy_code,
            'department' => $emp?->department?->name,
            'designation' => $emp?->designation?->name,
            'division' => $emp?->branch?->name,
            'application_date' => $advance->application_date?->format('Y-m-d'),
            'advance_type' => $advance->advance_type,
            'requested_amount' => (float) $advance->requested_amount,
            'approved_amount' => (float) ($advance->approved_amount ?? $advance->requested_amount),
            'amount_in_words' => $advance->amount_in_words,
            'purpose' => $advance->purpose,
            'present_salary_snapshot' => (float) $advance->present_salary_snapshot,
            'earned_salary_snapshot' => (float) $advance->earned_salary_snapshot,
            'allowed_amount_snapshot' => (float) $advance->allowed_amount_snapshot,
            'month_year' => $advance->month_year,
            'status' => $advance->status,
            'disbursement_date' => $advance->disbursement_date?->format('Y-m-d'),
            'paid_amount' => (float) $advance->paid_amount,
            'pending_amount' => $advance->pending_amount,
            'remarks' => $advance->remarks,
            'approved_at' => $advance->approved_at?->toIso8601String(),
            'approver_name' => $advance->approver?->name,
            'creator_name' => $advance->creator?->name,
        ];
    }
}
