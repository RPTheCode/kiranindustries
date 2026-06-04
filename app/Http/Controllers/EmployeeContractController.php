<?php

namespace App\Http\Controllers;

use App\Models\EmployeeContract;
use App\Models\ContractType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Carbon\Carbon;

class EmployeeContractController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeContract::withPermissionCheck()->with(['employee', 'contractType', 'approver']);

        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('contract_number', 'like', '%' . $request->search . '%')
                    ->orWhereHas('employee', function ($eq) use ($request) {
                        $eq->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('contract_type_id') && !empty($request->contract_type_id) && $request->contract_type_id !== 'all') {
            $query->where('contract_type_id', $request->contract_type_id);
        }

        if ($request->has('employee_id') && !empty($request->employee_id) && $request->employee_id !== 'all') {
            $query->where('employee_id', $request->employee_id);
        }

        // Auto-update expired contracts
        EmployeeContract::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'Active')
            ->where('end_date', '<', Carbon::today())
            ->update(['status' => 'Expired']);

        $query->orderBy('id', 'desc');
        $employeeContracts = $query->paginate($request->per_page ?? 10);

        $contractTypes = ContractType::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        $employees = User::whereIn('created_by', getCompanyAndUsersId())
            ->where('type', 'employee')
            ->select('id', 'name')
            ->get();

        return Inertia::render('hr/contracts/employee-contracts/index', [
            'employeeContracts' => $employeeContracts,
            'contractTypes' => $contractTypes,
            'employees' => $employees,
            'filters' => $request->all(['search', 'status', 'contract_type_id', 'employee_id', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:users,id',
            'contract_type_id' => 'required|exists:contract_types,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'basic_salary' => 'required|numeric|min:0',
            'allowances' => 'nullable|array',
            'benefits' => 'nullable|array',
            'terms_conditions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Generate contract number
        $lastContract = EmployeeContract::whereIn('created_by', getCompanyAndUsersId())
            ->orderBy('id', 'desc')
            ->first();
        $nextNumber = $lastContract ? (intval(substr($lastContract->contract_number, -4)) + 1) : 1;
        $contractNumber = 'CON-' . str_pad(creatorId(), 3, '0', STR_PAD_LEFT) . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        EmployeeContract::create([
            'contract_number' => $contractNumber,
            'employee_id' => $request->employee_id,
            'contract_type_id' => $request->contract_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'basic_salary' => $request->basic_salary,
            'allowances' => $request->allowances,
            'benefits' => $request->benefits,
            'terms_conditions' => $request->terms_conditions,
            'created_by' => creatorId(),
        ]);

        return redirect()->back()->with('success', __('Employee contract created successfully'));
    }

    public function update(Request $request, EmployeeContract $employeeContract)
    {
        if (!in_array($employeeContract->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this contract'));
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:users,id',
            'contract_type_id' => 'required|exists:contract_types,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'basic_salary' => 'required|numeric|min:0',
            'allowances' => 'nullable|array',
            'benefits' => 'nullable|array',
            'terms_conditions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $employeeContract->update([
            'employee_id' => $request->employee_id,
            'contract_type_id' => $request->contract_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'basic_salary' => $request->basic_salary,
            'allowances' => $request->allowances,
            'benefits' => $request->benefits,
            'terms_conditions' => $request->terms_conditions,
        ]);

        return redirect()->back()->with('success', __('Employee contract updated successfully'));
    }

    public function destroy(EmployeeContract $employeeContract)
    {
        if (!in_array($employeeContract->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to delete this contract'));
        }

        if ($employeeContract->status === 'Active') {
            return redirect()->back()->with('error', __('Cannot delete active contract'));
        }

        $employeeContract->delete();
        return redirect()->back()->with('success', __('Employee contract deleted successfully'));
    }

    public function updateStatus(Request $request, EmployeeContract $employeeContract)
    {
        if (!in_array($employeeContract->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this contract'));
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Draft,Pending Approval,Active,Expired,Terminated,Renewed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $updateData = ['status' => $request->status];

        if ($request->status === 'Active') {
            $updateData['approved_by'] = creatorId();
            $updateData['approved_at'] = now();
        }

        $employeeContract->update($updateData);
        return redirect()->back()->with('success', __('Contract status updated successfully'));
    }

    public function approve(Request $request, EmployeeContract $employeeContract)
    {
        if (!in_array($employeeContract->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to approve this contract'));
        }

        $employeeContract->update([
            'status' => 'Active',
            'approved_by' => creatorId(),
            'approved_at' => now(),
            'approval_notes' => $request->approval_notes,
        ]);

        return redirect()->back()->with('success', __('Contract approved successfully'));
    }

    public function reject(Request $request, EmployeeContract $employeeContract)
    {
        if (!in_array($employeeContract->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to reject this contract'));
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $employeeContract->update([
            'status' => 'Draft',
            'rejection_reason' => $request->rejection_reason,
            'rejected_by' => creatorId(),
            'rejected_at' => now(),
        ]);

        return redirect()->back()->with('success', __('Contract rejected successfully'));
    }
}