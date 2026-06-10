<?php

namespace App\Http\Controllers\SalaryPayroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalaryPayroll\SalaryLoanGuarantor;
use App\Models\SalaryPayroll\SalaryLoanRequest;
use App\Models\User;
use App\Services\SalaryPayroll\AmountInWordsService;
use App\Services\SalaryPayroll\SalaryLoanEligibilityService;
use App\Services\SalaryPayroll\SalaryLoanInstallmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalaryLoanController extends Controller
{
    public function __construct(
        private SalaryLoanEligibilityService $eligibilityService,
        private SalaryLoanInstallmentService $installmentService,
        private AmountInWordsService $amountInWords
    ) {}

    public function index(Request $request)
    {
        $branchId = session('active_branch_id');

        $query = SalaryLoanRequest::query()
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

        $loans = $query->paginate($request->integer('per_page', 15))->withQueryString();

        return Inertia::render('hr/salary-payroll/salary-loans/index', [
            'loans' => $loans,
            'filters' => $request->only(['search', 'status', 'month_year', 'per_page']),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create()
    {
        return Inertia::render('hr/salary-payroll/salary-loans/form', [
            'loan' => null,
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $employee = User::with('employee')->findOrFail($validated['employee_id']);
        $this->validateGuarantors($employee, $validated['guarantors'] ?? []);
        $applicationDate = Carbon::parse($validated['application_date']);
        $branchId = $this->resolveBranchId($validated['branch_id'] ?? null, $employee);
        $installmentCount = (int) ($validated['installment_count'] ?? SalaryLoanRequest::DEFAULT_INSTALLMENTS);

        try {
            $this->eligibilityService->validateLoan(
                $employee,
                (float) $validated['requested_amount'],
                $installmentCount,
                $applicationDate
            );
        } catch (\InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'requested_amount' => $e->getMessage(),
            ]);
        }

        $eligibility = $this->eligibilityService->forEmployee($employee, $applicationDate);
        $emi = SalaryLoanEligibilityService::calculateEmi((float) $validated['requested_amount'], $installmentCount);

        $loan = DB::transaction(function () use ($validated, $employee, $applicationDate, $branchId, $eligibility, $installmentCount, $emi) {
            $loan = SalaryLoanRequest::create([
                'employee_id' => $employee->id,
                'branch_id' => $branchId !== 'all' ? $branchId : ($employee->employee?->branch_id),
                'application_date' => $applicationDate->toDateString(),
                'requested_amount' => $validated['requested_amount'],
                'approved_amount' => $validated['requested_amount'],
                'installment_count' => $installmentCount,
                'installment_amount' => $emi['installment_amount'],
                'amount_in_words' => $this->amountInWords->rupees((float) $validated['requested_amount']),
                'purpose' => $validated['purpose'],
                'present_salary_snapshot' => $eligibility['present_salary'],
                'month_year' => $applicationDate->format('Y-m'),
                'status' => ($validated['submit'] ?? false) ? SalaryLoanRequest::STATUS_SUBMITTED : SalaryLoanRequest::STATUS_DRAFT,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncGuarantors($loan, $validated['guarantors'] ?? []);

            return $loan;
        });

        return redirect()
            ->route('hr.salary-loans.show', $loan)
            ->with('success', __('Salary loan request saved successfully.'));
    }

    public function show(SalaryLoanRequest $salaryLoanRequest)
    {
        $this->authorizeRequest($salaryLoanRequest);
        $salaryLoanRequest->load([
            'employee.employee.department',
            'employee.employee.designation',
            'employee.employee.branch',
            'creator',
            'approver',
            'branch',
            'guarantors',
            'installments',
        ]);

        return Inertia::render('hr/salary-payroll/salary-loans/show', [
            'loan' => $this->transformLoan($salaryLoanRequest),
        ]);
    }

    public function edit(SalaryLoanRequest $salaryLoanRequest)
    {
        $this->authorizeRequest($salaryLoanRequest);

        if (! $salaryLoanRequest->isEditable()) {
            return redirect()
                ->route('hr.salary-loans.show', $salaryLoanRequest)
                ->with('error', __('This loan request cannot be edited.'));
        }

        $salaryLoanRequest->load('guarantors');

        return Inertia::render('hr/salary-payroll/salary-loans/form', [
            'loan' => $this->transformLoan($salaryLoanRequest),
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function update(Request $request, SalaryLoanRequest $salaryLoanRequest)
    {
        $this->authorizeRequest($salaryLoanRequest);

        if (! $salaryLoanRequest->isEditable()) {
            return back()->with('error', __('This loan request cannot be edited.'));
        }

        $validated = $this->validateRequest($request, $salaryLoanRequest);
        $employee = User::with('employee')->findOrFail($salaryLoanRequest->employee_id);
        $this->validateGuarantors($employee, $validated['guarantors'] ?? []);
        $applicationDate = Carbon::parse($validated['application_date']);
        $installmentCount = (int) ($validated['installment_count'] ?? $salaryLoanRequest->installment_count);

        try {
            $this->eligibilityService->validateLoan(
                $employee,
                (float) $validated['requested_amount'],
                $installmentCount,
                $applicationDate,
                $salaryLoanRequest->id
            );
        } catch (\InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'requested_amount' => $e->getMessage(),
            ]);
        }

        $eligibility = $this->eligibilityService->forEmployee($employee, $applicationDate, $salaryLoanRequest->id);
        $emi = SalaryLoanEligibilityService::calculateEmi((float) $validated['requested_amount'], $installmentCount);

        DB::transaction(function () use ($salaryLoanRequest, $validated, $applicationDate, $eligibility, $installmentCount, $emi) {
            $salaryLoanRequest->update([
                'application_date' => $applicationDate->toDateString(),
                'requested_amount' => $validated['requested_amount'],
                'approved_amount' => $validated['requested_amount'],
                'installment_count' => $installmentCount,
                'installment_amount' => $emi['installment_amount'],
                'amount_in_words' => $this->amountInWords->rupees((float) $validated['requested_amount']),
                'purpose' => $validated['purpose'],
                'present_salary_snapshot' => $eligibility['present_salary'],
                'month_year' => $applicationDate->format('Y-m'),
                'status' => ($validated['submit'] ?? false)
                    ? SalaryLoanRequest::STATUS_SUBMITTED
                    : $salaryLoanRequest->status,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $this->syncGuarantors($salaryLoanRequest, $validated['guarantors'] ?? []);
        });

        return redirect()
            ->route('hr.salary-loans.show', $salaryLoanRequest)
            ->with('success', __('Salary loan request updated successfully.'));
    }

    public function destroy(SalaryLoanRequest $salaryLoanRequest)
    {
        $this->authorizeRequest($salaryLoanRequest);

        if (! in_array($salaryLoanRequest->status, [
            SalaryLoanRequest::STATUS_DRAFT,
            SalaryLoanRequest::STATUS_SUBMITTED,
            SalaryLoanRequest::STATUS_REJECTED,
            SalaryLoanRequest::STATUS_CANCELLED,
        ], true)) {
            return back()->with('error', __('Only draft, submitted, rejected or cancelled requests can be deleted.'));
        }

        $salaryLoanRequest->delete();

        return redirect()
            ->route('hr.salary-loans.index')
            ->with('success', __('Salary loan request deleted.'));
    }

    public function approve(SalaryLoanRequest $salaryLoanRequest)
    {
        $this->authorizeRequest($salaryLoanRequest);

        if (! in_array($salaryLoanRequest->status, [SalaryLoanRequest::STATUS_SUBMITTED, SalaryLoanRequest::STATUS_DRAFT], true)) {
            return back()->with('error', __('This request cannot be approved.'));
        }

        $salaryLoanRequest->update([
            'status' => SalaryLoanRequest::STATUS_APPROVED,
            'approved_amount' => $salaryLoanRequest->approved_amount ?? $salaryLoanRequest->requested_amount,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', __('Loan request approved.'));
    }

    public function disburse(SalaryLoanRequest $salaryLoanRequest)
    {
        $this->authorizeRequest($salaryLoanRequest);

        if ($salaryLoanRequest->status !== SalaryLoanRequest::STATUS_APPROVED) {
            return back()->with('error', __('Only approved requests can be disbursed.'));
        }

        DB::transaction(function () use ($salaryLoanRequest) {
            $disbursementDate = now();
            $salaryLoanRequest->update([
                'status' => SalaryLoanRequest::STATUS_DISBURSED,
                'disbursement_date' => $disbursementDate->toDateString(),
                'paid_amount' => 0,
                'approved_amount' => $salaryLoanRequest->approved_amount ?? $salaryLoanRequest->requested_amount,
            ]);

            $this->installmentService->generateSchedule($salaryLoanRequest->fresh(), $disbursementDate);
        });

        return back()->with('success', __('Loan disbursed. EMI recovery will apply in Generate Payroll.'));
    }

    public function printBlank()
    {
        return view('exports.salary-loan-form', [
            'blank' => true,
            'loan' => null,
            'companyName' => $this->printCompanyTitle(),
            'printDate' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    public function print(SalaryLoanRequest $salaryLoanRequest)
    {
        $this->authorizeRequest($salaryLoanRequest);
        $salaryLoanRequest->load([
            'employee.employee.department',
            'employee.employee.designation',
            'employee.employee.branch',
            'creator',
            'approver',
            'branch',
            'guarantors',
        ]);

        $companyTitle = $this->printCompanyTitle(
            $salaryLoanRequest->branch?->name
                ?? $salaryLoanRequest->employee?->employee?->branch?->name
        );

        return view('exports.salary-loan-form', [
            'blank' => false,
            'loan' => $salaryLoanRequest,
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

        return response()->json(
            $this->eligibilityService->forEmployee($employee, $date)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequest(Request $request, ?SalaryLoanRequest $existing = null): array
    {
        return $request->validate([
            'employee_id' => [$existing ? 'prohibited' : 'required', 'exists:users,id'],
            'application_date' => ['required', 'date'],
            'requested_amount' => ['required', 'numeric', 'min:1'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:'.SalaryLoanRequest::MAX_INSTALLMENTS],
            'purpose' => ['required', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'submit' => ['nullable', 'boolean'],
            'guarantors' => ['required', 'array', 'size:3'],
            'guarantors.*.name' => ['required', 'string', 'max:255'],
            'guarantors.*.employee_code' => ['nullable', 'string', 'max:50'],
            'guarantors.*.department' => ['nullable', 'string', 'max:255'],
            'guarantors.*.guarantor_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $guarantors
     */
    private function validateGuarantors(User $applicant, array $guarantors): void
    {
        $applicantEmployeeId = $applicant->employee?->id;
        $applicantCode = strtolower(trim((string) ($applicant->employee?->employee_id ?? $applicant->employee?->emy_code ?? '')));
        $seenEmployeeIds = [];
        $seenCodes = [];

        foreach (array_values($guarantors) as $index => $row) {
            $guarantorEmployeeId = ! empty($row['guarantor_employee_id']) ? (int) $row['guarantor_employee_id'] : null;
            $employeeCode = strtolower(trim((string) ($row['employee_code'] ?? '')));

            if ($applicantEmployeeId && $guarantorEmployeeId === (int) $applicantEmployeeId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "guarantors.{$index}.guarantor_employee_id" => __('Loan applicant cannot be their own guarantor.'),
                ]);
            }

            if ($applicantCode !== '' && $employeeCode !== '' && $employeeCode === $applicantCode) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "guarantors.{$index}.employee_code" => __('Loan applicant cannot be their own guarantor.'),
                ]);
            }

            if ($guarantorEmployeeId) {
                if (in_array($guarantorEmployeeId, $seenEmployeeIds, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "guarantors.{$index}.guarantor_employee_id" => __('Each guarantor must be a different employee.'),
                    ]);
                }
                $seenEmployeeIds[] = $guarantorEmployeeId;
            }

            if ($employeeCode !== '') {
                if (in_array($employeeCode, $seenCodes, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "guarantors.{$index}.employee_code" => __('Each guarantor must be a different person.'),
                    ]);
                }
                $seenCodes[] = $employeeCode;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $guarantors
     */
    private function syncGuarantors(SalaryLoanRequest $loan, array $guarantors): void
    {
        $loan->guarantors()->delete();

        foreach (array_values($guarantors) as $index => $row) {
            $guarantorEmployeeId = ! empty($row['guarantor_employee_id']) ? (int) $row['guarantor_employee_id'] : null;
            $department = $row['department'] ?? null;
            $employeeCode = $row['employee_code'] ?? null;

            if ($guarantorEmployeeId) {
                $emp = Employee::with('department')->find($guarantorEmployeeId);
                if ($emp) {
                    $department = $department ?: $emp->department?->name;
                    $employeeCode = $employeeCode ?: $emp->employee_id;
                }
            }

            SalaryLoanGuarantor::create([
                'salary_loan_request_id' => $loan->id,
                'guarantor_employee_id' => $guarantorEmployeeId,
                'name' => $row['name'],
                'employee_code' => $employeeCode,
                'department' => $department,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function authorizeRequest(SalaryLoanRequest $loan): void
    {
        if (! in_array($loan->created_by, getCompanyAndUsersId())) {
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
            ->with([
                'employee' => fn ($q) => $q->withoutGlobalScopes()
                    ->select('id', 'user_id', 'employee_id', 'emy_code', 'branch_id', 'department_id')
                    ->with('department:id,name'),
            ]);

        if ($branchId && $branchId !== 'all') {
            $query->whereHas('employee', fn ($q) => $q->withoutGlobalScopes()->where('branch_id', $branchId));
        }

        return $query->orderBy('name')->get(['id', 'name'])->map(function (User $user) {
            $employee = $user->employee;
            $code = $employee?->employee_id ?? $employee?->emy_code ?? '';

            return [
                'id' => $user->id,
                'employee_record_id' => $employee?->id,
                'name' => $user->name,
                'employee_code' => $code,
                'department' => $employee?->department?->name ?? '',
                'label' => trim($user->name.($code !== '' ? " ({$code})" : '')),
            ];
        })->filter(fn (array $row) => $row['employee_record_id'] !== null)->values()->all();
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            SalaryLoanRequest::STATUS_DRAFT => __('Draft'),
            SalaryLoanRequest::STATUS_SUBMITTED => __('Submitted'),
            SalaryLoanRequest::STATUS_APPROVED => __('Approved'),
            SalaryLoanRequest::STATUS_DISBURSED => __('Disbursed'),
            SalaryLoanRequest::STATUS_RECOVERING => __('Recovering'),
            SalaryLoanRequest::STATUS_CLOSED => __('Closed'),
            SalaryLoanRequest::STATUS_REJECTED => __('Rejected'),
            SalaryLoanRequest::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformLoan(SalaryLoanRequest $loan): array
    {
        $emp = $loan->employee?->employee;

        return [
            'id' => $loan->id,
            'employee_id' => $loan->employee_id,
            'employee_name' => $loan->employee?->name,
            'employee_code' => $emp?->employee_id ?? $emp?->emy_code,
            'department' => $emp?->department?->name,
            'designation' => $emp?->designation?->name,
            'division' => $emp?->branch?->name,
            'application_date' => $loan->application_date?->format('Y-m-d'),
            'requested_amount' => (float) $loan->requested_amount,
            'approved_amount' => (float) ($loan->approved_amount ?? $loan->requested_amount),
            'installment_count' => (int) $loan->installment_count,
            'installment_amount' => (float) ($loan->installment_amount ?? 0),
            'amount_in_words' => $loan->amount_in_words,
            'purpose' => $loan->purpose,
            'present_salary_snapshot' => (float) $loan->present_salary_snapshot,
            'month_year' => $loan->month_year,
            'deduction_start_month' => $loan->deduction_start_month,
            'status' => $loan->status,
            'disbursement_date' => $loan->disbursement_date?->format('Y-m-d'),
            'paid_amount' => (float) $loan->paid_amount,
            'pending_amount' => $loan->pending_amount,
            'remarks' => $loan->remarks,
            'approved_at' => $loan->approved_at?->toIso8601String(),
            'approver_name' => $loan->approver?->name,
            'creator_name' => $loan->creator?->name,
            'guarantors' => $loan->guarantors?->map(fn ($g) => [
                'id' => $g->id,
                'guarantor_employee_id' => $g->guarantor_employee_id,
                'name' => $g->name,
                'employee_code' => $g->employee_code,
                'department' => $g->department,
                'sort_order' => $g->sort_order,
            ])->values()->all() ?? [],
            'installments' => $loan->installments?->map(fn ($i) => [
                'id' => $i->id,
                'installment_no' => $i->installment_no,
                'due_month' => $i->due_month,
                'amount' => (float) $i->amount,
                'status' => $i->status,
                'paid_at' => $i->paid_at?->toIso8601String(),
            ])->values()->all() ?? [],
        ];
    }
}
