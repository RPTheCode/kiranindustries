<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BranchController extends Controller
{
    use Concerns\LogsMasterCrud;

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        try {
            $import = new \App\Imports\BranchesImport;
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

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

            return redirect()->back()->with('success', __('Branches imported successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to import branches: ') . $e->getMessage());
        }
    }

    public function importTemplate()
    {
        $filename = 'branches_import_template.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BranchesTemplateExport, $filename);
    }
    public function index(Request $request)
    {
        $query = Branch::withPermissionCheck()
            ->withCount([
                'employees' => function ($query) {
                    $query->withoutGlobalScope(\App\Models\Scopes\BranchScope::class);
                },
                'departments' => function ($query) {
                    $query->withoutGlobalScope(\App\Models\Scopes\BranchScope::class);
                }
            ]);

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%')
                ->orWhere('phone', 'like', '%' . $request->search . '%');
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $branches = $query->paginate($request->per_page ?? 10);

        return Inertia::render('hr/branches/index', [
            'branches' => $branches,
            'filters' => $request->all(['search', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'zip_code' => 'nullable|string|max:20',
                'phone' => 'nullable|strict_phone',
                'email' => 'nullable|strict_email|max:255',
                'in_charge_name' => 'nullable|string|max:255',
                'in_charge_contact' => 'nullable|strict_phone',
                'status' => 'nullable|in:active,inactive',
            ]);

            $validated['created_by'] = creatorId();
            $validated['status'] = $validated['status'] ?? 'active';

            // Check if branch with same name already exists
            $exists = Branch::where('name', $validated['name'])
                ->whereIn('created_by', getCompanyAndUsersId())
                ->exists();

            if ($exists) {
                return redirect()->back()->with('error', __('Branch with this name already exists.'));
            }

            $branch = Branch::create($validated);
            $this->logMasterCreated($branch);

            // If this is the first branch, set it as active
            $branchCount = Branch::whereIn('created_by', getCompanyAndUsersId())->count();
            if ($branchCount === 1) {
                session(['active_branch_id' => $branch->id]);
                if (Auth::user()) {
                    Auth::user()->update(['last_active_branch_id' => $branch->id]);
                }
            }

            return redirect()->back()->with('success', __('Branch created successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to create branch'));
        }
    }


    public function update(Request $request, $branchId)
    {
        $branch = Branch::where('id', $branchId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($branch) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'address' => 'nullable|string',
                    'city' => 'nullable|string|max:100',
                    'state' => 'nullable|string|max:100',
                    'country' => 'nullable|string|max:100',
                    'zip_code' => 'nullable|string|max:20',
                    'phone' => 'nullable|strict_phone',
                    'email' => 'nullable|strict_email|max:255',
                    'in_charge_name' => 'nullable|string|max:255',
                    'in_charge_contact' => 'nullable|strict_phone',
                    'status' => 'nullable|in:active,inactive',
                ]);

                // Check if branch with same name already exists (excluding current branch)
                $exists = Branch::where('name', $validated['name'])
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->where('id', '!=', $branchId)
                    ->exists();

                if ($exists) {
                    return redirect()->back()->with('error', __('Branch with this name already exists.'));
                }

                $branch->update($validated);
                $this->logMasterUpdated($branch);

                return redirect()->back()->with('success', __('Branch updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update branch'));
            }
        } else {
            return redirect()->back()->with('error', __('Branch not found.'));
        }
    }


    public function destroy($branchId)
    {
        $branch = Branch::where('id', $branchId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($branch) {
            try {
                // Check if branch has departments
                if (class_exists('App\\Models\\Department')) {
                    $departmentCount = \App\Models\Department::where('branch_id', $branchId)->count();
                    if ($departmentCount > 0) {
                        return redirect()->back()->with('error', __('Cannot delete branch with assigned departments'));
                    }
                }

                $this->logMasterDeleted($branch);
                $branch->delete();

                return redirect()->back()->with('success', __('Branch deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete branch'));
            }
        } else {
            return redirect()->back()->with('error', __('Branch not found.'));
        }
    }

    public function toggleStatus($branchId)
    {
        $branch = Branch::where('id', $branchId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($branch) {
            try {
                $branch->status = $branch->status === 'active' ? 'inactive' : 'active';
                $branch->save();
                $this->logMasterUpdated($branch);

                // If branch becomes inactive and it was the active branch in session, clear it
                if ($branch->status === 'inactive' && session('active_branch_id') == $branch->id) {
                    session()->forget('active_branch_id');
                    if (auth()->user()) {
                        auth()->user()->update(['last_active_branch_id' => null]);
                    }
                }

                return redirect()->back()->with('success', __('Branch status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update branch status'));
            }
        } else {
            return redirect()->back()->with('error', __('Branch not found.'));
        }
    }
    public function setActive(Request $request)
    {
        $user = Auth::user();

        // Check if user is trying to clear the branch (All Branches)
        if ($request->branch_id === 'all' || !$request->branch_id) {
            // Only company/admin can see all branches
            if ($user->type === 'company' || $user->isSuperAdmin()) {
                session()->forget('active_branch_id');
                $user->update(['last_active_branch_id' => null]);
                return redirect()->back()->with('success', __('Showing all branches data.'));
            }
            return redirect()->back()->with('error', __('Regular users must select a branch.'));
        }

        $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $branchQuery = Branch::where('id', $request->branch_id)
            ->where('status', 'active')
            ->whereIn('created_by', getCompanyAndUsersId());

        if ($user->type !== 'company' && !$user->isSuperAdmin()) {
            // Check if the branch is assigned to the user
            if (!$user->assignedBranches()->where('branches.id', $request->branch_id)->exists()) {
                return redirect()->back()->with('error', __('You do not have access to this branch.'));
            }
        }

        $branch = $branchQuery->firstOrFail();

        session(['active_branch_id' => $branch->id]);

        // Save as last active branch for the user
        if ($user) {
            $user->last_active_branch_id = $branch->id;
            $user->save();
        }

        return redirect()->back()->with('success', __('Active branch updated to :name.', ['name' => $branch->name]));
    }
}
