<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Imports\DepartmentsImport;
use App\Exports\DepartmentsTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class DepartmentController extends Controller
{
    use Concerns\LogsMasterCrud;

    public function index(Request $request)
    {
        $query = Department::withPermissionCheck()
            ->with(['branch', 'creator'])
            ->withCount([
                'employees' => function ($query) {
                    $query->withoutGlobalScope(\App\Models\Scopes\BranchScope::class);
                },
                'desginations' => function ($query) {
                    $query->withoutGlobalScope(\App\Models\Scopes\BranchScope::class);
                }
            ]);

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('short_code', 'like', '%' . $request->search . '%')
                    ->orWhere('code', 'like', '%' . $request->search . '%');
            });
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

        $departments = $query->paginate($request->per_page ?? 10);
        $branches = Branch::all();

        $filters = $request->all(['search', 'status', 'branch_id', 'sort_field', 'sort_direction', 'per_page']);
        if (!isset($filters['branch_id'])) {
            $filters['branch_id'] = $branchId ? (string) $branchId : 'all';
        }

        return Inertia::render('hr/departments/index', [
            'departments' => $departments,
            'branches' => $branches,
            'activeBranchId' => session('active_branch_id'),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $targetBranchId = $request->input('branch_id') ?? session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'short_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                }),
            ],
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['created_by'] = creatorId();
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['branch_id'] = $targetBranchId;

        if (!$validated['branch_id']) {
            return redirect()->back()->with('error', __('No active branch selected.'));
        }

        $department = Department::create($validated);
        $this->logMasterCreated($department);

        return redirect()->back()->with('success', __('Department created successfully.'));
    }

    public function update(Request $request, $departmentId)
    {
        $department = Department::where('id', $departmentId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$department) {
            return redirect()->back()->with('error', __('Department Not Found.'));
        }

        try {
            $targetBranchId = $request->input('branch_id') ?? $department->branch_id ?? session('active_branch_id');
            $companyUserIds = getCompanyAndUsersId();

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:255',
                'short_code' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('departments')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                        return $query->where('branch_id', $targetBranchId)
                            ->whereIn('created_by', $companyUserIds);
                    })->ignore($departmentId),
                ],
                'status' => 'nullable|in:active,inactive',
            ]);

            $department->update($validated);
            $this->logMasterUpdated($department);

            return redirect()->back()->with('success', __('Department updated successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update department'));
        }
    }

    public function destroy($departmentId)
    {
        $department = Department::where('id', $departmentId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$department) {
            return redirect()->back()->with('error', __('Department Not Found.'));
        }

        try {
            if (class_exists('App\\Models\\Employee')) {
                $employeeCount = \App\Models\User::where('type', 'employee')
                    ->whereHas('employee', function ($q) use ($departmentId) {
                        $q->where('department_id', $departmentId);
                    })->count();
                if ($employeeCount > 0) {
                    return response()->json(['message' => __('Cannot delete department with assigned employees')], 400);
                }
            }
            $this->logMasterDeleted($department);
            $department->delete();

            return redirect()->back()->with('success', __('Department deleted successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete department'));
        }
    }

    public function toggleStatus($departmentId)
    {
        $department = Department::where('id', $departmentId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$department) {
            return redirect()->back()->with('error', __('Department Not Found.'));
        }

        try {
            $department->status = $department->status === 'active' ? 'inactive' : 'active';
            $department->save();
            return redirect()->back()->with('success', __('Department status updated successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update department status'));
        }
    }

    /**
     * Copy a single department to one or more branches.
     */
    public function copyToBranches(Request $request, $departmentId)
    {
        $request->validate([
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $source = Department::whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $departmentId)
            ->first();

        if (!$source) {
            return redirect()->back()->with('error', __('Department Not Found.'));
        }

        $successCount = 0;
        $warnings = [];

        DB::beginTransaction();
        try {
            foreach ($request->branch_ids as $branchId) {
                $existing = Department::where('branch_id', $branchId)
                    ->where('short_code', $source->short_code)
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->first();

                if ($existing) {
                    $branch = Branch::find($branchId);
                    $warnings[] = __("Department code ':code' already exists in Branch ':branch'. Skipped.", [
                        'code' => $source->short_code,
                        'branch' => $branch ? $branch->name : '#' . $branchId,
                    ]);
                    continue;
                }

                $clone = $source->replicate();
                $clone->branch_id = $branchId;
                $clone->created_by = Auth::id();
                $clone->save();
                $successCount++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to copy department to branches.'));
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : __('No departments were copied.'));
        }

        $msg = __(':count department(s) successfully copied.', ['count' => $successCount]);
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Bulk copy multiple departments to one or more branches.
     */
    public function bulkCopyToBranches(Request $request)
    {
        $request->validate([
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'required|integer|exists:departments,id',
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $sources = Department::whereIn('id', $request->department_ids)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->get();

        if ($sources->isEmpty()) {
            return redirect()->back()->with('error', __('Departments Not Found.'));
        }

        $successCount = 0;
        $warnings = [];

        DB::beginTransaction();
        try {
            \App\Services\ActivityLogger::withoutLogging(function () use ($sources, $request, &$successCount, &$warnings) {
                foreach ($sources as $source) {
                    foreach ($request->branch_ids as $branchId) {
                        $existing = Department::where('branch_id', $branchId)
                            ->where('short_code', $source->short_code)
                            ->whereIn('created_by', getCompanyAndUsersId())
                            ->first();

                        if ($existing) {
                            $branch = Branch::find($branchId);
                            $warnings[] = __("':name' (code ':code') already exists in ':branch'. Skipped.", [
                                'name' => $source->name,
                                'code' => $source->short_code,
                                'branch' => $branch ? $branch->name : '#'.$branchId,
                            ]);
                            continue;
                        }

                        $clone = $source->replicate();
                        $clone->branch_id = $branchId;
                        $clone->created_by = Auth::id();
                        $clone->save();
                        $successCount++;
                    }
                }
            });

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to bulk copy departments.'));
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : __('No departments were copied.'));
        }

        if ($successCount > 0 && auth()->check()) {
            \App\Services\ActivityLogger::log(
                'Department',
                'created',
                sprintf('%s bulk-copied %d department(s) to selected branches', auth()->user()->name, $successCount),
                (int) ($request->branch_ids[0] ?? session('active_branch_id'))
            );
        }

        $msg = __(':count department(s) successfully copied to selected branches.', ['count' => $successCount]);
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Import departments from Excel/CSV.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,excel,csv,txt',
        ]);

        try {
            $import = new DepartmentsImport;
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
                $msg .= '</ul></div>';
                return redirect()->back()->with('error', $msg);
            }

            return redirect()->back()->with('success', __('Departments imported successfully.'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('Error importing file:') . ' ' . $e->getMessage());
        }
    }

    /**
     * Download the template for importing departments.
     */
    public function importTemplate()
    {
        return Excel::download(new DepartmentsTemplateExport, 'departments_template.xlsx');
    }
}
