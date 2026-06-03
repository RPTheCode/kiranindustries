<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Imports\DesignationsImport;
use App\Exports\DesignationsTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class DesignationController extends Controller
{
    use Concerns\LogsMasterCrud;

    public function report(Request $request)
    {
        $query = Designation::with(['department', 'branch'])
            ->withCount(['employees']);

        $branchId = $request->input('branch_id') ?? session('active_branch_id');
        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $designations = $query->get();

        return Inertia::render('hr/designations/Report', [
            'designations' => $designations,
            'company_name' => 'KIRAN INDUSTRIES PVT LTD-PALSANA',
            'print_date' => date('d-m-Y')
        ]);
    }

    public function index(Request $request)
    {
        $query = Designation::withPermissionCheck()->with(['department', 'department.branch', 'branch'])
            ->withCount([
                'employees' => function ($query) {
                    $query->withoutGlobalScope(\App\Models\Scopes\BranchScope::class);
                }
            ]);

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('code', 'like', '%' . $request->search . '%');
            });
        }

        // Handle department filter
        if ($request->has('department') && !empty($request->department) && $request->department !== 'all') {
            $query->where('department_id', $request->department);
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Branch-wise filtering: use request branch_id or fall back to active session branch
        $branchId = $request->input('branch_id') ?? session('active_branch_id');
        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $designations = $query->paginate($request->per_page ?? 10);

        // Get departments for dropdown - scoped by branch if filtered
        $departmentsQuery = Department::with('branch')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($branchId && $branchId !== 'all') {
            $departmentsQuery->where('branch_id', $branchId);
        }
        $departments = $departmentsQuery->get();

        $branches = Branch::all();

        $filters = $request->all(['search', 'status', 'branch_id', 'sort_field', 'sort_direction', 'per_page', 'department']);
        if (!isset($filters['branch_id'])) {
            $filters['branch_id'] = $branchId ? (string) $branchId : 'all';
        }

        return Inertia::render('hr/designations/index', [
            'designations' => $designations,
            'departments' => $departments,
            'branches' => $branches,
            'activeBranchId' => session('active_branch_id'),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $department = Department::where('id', $request->department_id)
                ->whereIn('created_by', getCompanyAndUsersId())
                ->first();

            if (!$department) {
                return redirect()->back()->with('error', __('Selected department does not belong to your company'));
            }

            $targetBranchId = $department->branch_id;
            $companyUserIds = getCompanyAndUsersId();

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('designations')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                        return $query->where('branch_id', $targetBranchId)
                            ->whereIn('created_by', $companyUserIds);
                    })
                ],
                'rate' => 'nullable|numeric|min:0',
                'department_id' => 'required|exists:departments,id',
                'status' => 'nullable|in:active,inactive',
            ]);

            $validated['created_by'] = creatorId();
            $validated['branch_id'] = $targetBranchId;
            $validated['status'] = $validated['status'] ?? 'active';

            $designation = Designation::create($validated);
            $this->logMasterCreated($designation);

            return redirect()->back()->with('success', __('Designation created successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to create designation'));
        }
    }

    public function update(Request $request, $designationId)
    {
        $designation = Designation::where('id', $designationId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($designation) {
            try {
                $department = Department::where('id', $request->department_id)
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->first();

                if (!$department) {
                    return redirect()->back()->with('error', __('Selected department does not belong to your company.'));
                }

                $targetBranchId = $department->branch_id;
                $companyUserIds = getCompanyAndUsersId();

                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'code' => [
                        'required',
                        'string',
                        'max:50',
                        Rule::unique('designations')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                            return $query->where('branch_id', $targetBranchId)
                                ->whereIn('created_by', $companyUserIds);
                        })->ignore($designationId)
                    ],
                    'rate' => 'nullable|numeric|min:0',
                    'department_id' => 'required|exists:departments,id',
                    'status' => 'nullable|in:active,inactive',
                ]);

                $validated['branch_id'] = $targetBranchId;

                $designation->update($validated);
                $this->logMasterUpdated($designation);

                return redirect()->back()->with('success', __('Designation updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update designation'));
            }
        } else {
            return redirect()->back()->with('error', __('Designation Not Found.'));
        }
    }

    public function destroy($designationId)
    {
        $designation = Designation::where('id', $designationId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($designation) {
            try {
                $this->logMasterDeleted($designation);
                $designation->delete();

                return redirect()->back()->with('success', __('Designation deleted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete designation'));
            }
        } else {
            return redirect()->back()->with('error', __('Designation Not Found.'));
        }
    }

    public function toggleStatus($designationId)
    {
        $designation = Designation::where('id', $designationId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($designation) {
            try {
                $designation->status = $designation->status === 'active' ? 'inactive' : 'active';
                $designation->save();
                return redirect()->back()->with('success', __('Designation status updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update designation status'));
            }
        } else {
            return redirect()->back()->with('error', __('Designation Not Found.'));
        }
    }

    /**
     * Copy a single designation to one or more branches.
     */
    public function copyToBranches(Request $request, $designationId)
    {
        $request->validate([
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $source = Designation::with('department')->whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $designationId)
            ->first();

        if (!$source) {
            return redirect()->back()->with('error', __('Designation Not Found.'));
        }

        $successCount = 0;
        $warnings = [];

        DB::beginTransaction();
        try {
            foreach ($request->branch_ids as $branchId) {
                // Find or clone the department in the target branch
                $targetDept = Department::where('branch_id', $branchId)
                    ->where('name', $source->department->name)
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->first();

                if (!$targetDept) {
                    // Clone the department to this branch
                    $targetDept = $source->department->replicate();
                    $targetDept->branch_id = $branchId;
                    $targetDept->created_by = Auth::id();
                    $targetDept->save();
                }

                // Check if designation with the same code already exists in that branch
                $existing = Designation::where('branch_id', $branchId)
                    ->where('code', $source->code)
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->first();

                if ($existing) {
                    $branch = Branch::find($branchId);
                    $warnings[] = __("Designation code ':code' already exists in Branch ':branch'. Skipped.", [
                        'code' => $source->code,
                        'branch' => $branch ? $branch->name : '#' . $branchId,
                    ]);
                    continue;
                }

                $clone = $source->replicate();
                $clone->branch_id = $branchId;
                $clone->department_id = $targetDept->id;
                $clone->created_by = Auth::id();
                $clone->save();
                $successCount++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to copy designation to branches.'));
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : __('No designations were copied.'));
        }

        $msg = __(':count designation(s) successfully copied.', ['count' => $successCount]);
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Bulk copy multiple designations to one or more branches.
     */
    public function bulkCopyToBranches(Request $request)
    {
        $request->validate([
            'designation_ids' => 'required|array|min:1',
            'designation_ids.*' => 'required|integer|exists:designations,id',
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $sources = Designation::with('department')->whereIn('id', $request->designation_ids)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->get();

        if ($sources->isEmpty()) {
            return redirect()->back()->with('error', __('Designations Not Found.'));
        }

        $successCount = 0;
        $warnings = [];

        DB::beginTransaction();
        try {
            foreach ($sources as $source) {
                foreach ($request->branch_ids as $branchId) {
                    // Find or clone the department in the target branch
                    $targetDept = Department::where('branch_id', $branchId)
                        ->where('name', $source->department->name)
                        ->whereIn('created_by', getCompanyAndUsersId())
                        ->first();

                    if (!$targetDept) {
                        // Clone the department to this branch
                        $targetDept = $source->department->replicate();
                        $targetDept->branch_id = $branchId;
                        $targetDept->created_by = Auth::id();
                        $targetDept->save();
                    }

                    // Check if designation with the same code already exists in that branch
                    $existing = Designation::where('branch_id', $branchId)
                        ->where('code', $source->code)
                        ->whereIn('created_by', getCompanyAndUsersId())
                        ->first();

                    if ($existing) {
                        $branch = Branch::find($branchId);
                        $warnings[] = __("':name' (code ':code') already exists in ':branch'. Skipped.", [
                            'name' => $source->name,
                            'code' => $source->code,
                            'branch' => $branch ? $branch->name : '#' . $branchId,
                        ]);
                        continue;
                    }

                    $clone = $source->replicate();
                    $clone->branch_id = $branchId;
                    $clone->department_id = $targetDept->id;
                    $clone->created_by = Auth::id();
                    $clone->save();
                    $successCount++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to bulk copy designations.'));
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : __('No designations were copied.'));
        }

        $msg = __(':count designation(s) successfully copied to selected branches.', ['count' => $successCount]);
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Import designations from Excel/CSV.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,excel,csv,txt',
        ]);

        try {
            $import = new DesignationsImport;
            Excel::import($import, $request->file('file'));

            $failures = $import->failures();
            $savedCount = $import->rowsSaved;
            $failedCount = $failures->count();

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

            return redirect()->back()->with('success', __('Designations imported successfully.'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('Error importing file:') . ' ' . $e->getMessage());
        }
    }

    /**
     * Download the template for importing designations.
     */
    public function importTemplate()
    {
        return Excel::download(new DesignationsTemplateExport, 'designations_template.xlsx');
    }
}