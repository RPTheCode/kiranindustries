<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Termination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class TerminationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Termination::withPermissionCheck()->with(['employee', 'approver']);

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('employee_id', 'like', '%' . $request->search . '%');
            })
                ->orWhere('termination_type', 'like', '%' . $request->search . '%')
                ->orWhere('reason', 'like', '%' . $request->search . '%')
                ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        // Handle employee filter
        if ($request->has('employee_id') && !empty($request->employee_id)) {
            $query->where('employee_id', $request->employee_id);
        }

        // Handle termination type filter
        if ($request->has('termination_type') && !empty($request->termination_type)) {
            $query->where('termination_type', $request->termination_type);
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Handle date range filter
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->whereDate('termination_date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->whereDate('termination_date', '<=', $request->date_to);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $terminations = $query->paginate($request->per_page ?? 10);

        // Get employees for filter dropdown
        $employees = User::join('employees', 'users.id', '=', 'employees.user_id')
            ->where('users.type', 'employee')
            ->whereIn('users.created_by', getCompanyAndUsersId())
            ->select('users.id', 'users.name', 'employees.employee_id')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->employee_id ?? ''
                ];
            });

        // Get termination types for filter dropdown
        $terminationTypes = Termination::whereIn('created_by', getCompanyAndUsersId())
            ->select('termination_type')
            ->distinct()
            ->pluck('termination_type')
            ->toArray();

        return Inertia::render('hr/terminations/index', [
            'terminations' => $terminations,
            'employees' => $employees,
            'terminationTypes' => $terminationTypes,
            'filters' => $request->all(['search', 'employee_id', 'termination_type', 'status', 'date_from', 'date_to', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:users,id',
            'termination_type' => 'required|string|max:255',
            'termination_date' => 'required|date',
            'notice_date' => 'required|date|before_or_equal:termination_date',
            'notice_period' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'documents' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Check if employee belongs to current company
        $employee = User::find($request->employee_id);
        if (!$employee || !in_array($employee->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('Invalid employee selected'));
        }

        $terminationData = [
            'employee_id' => $request->employee_id,
            'termination_type' => $request->termination_type,
            'termination_date' => $request->termination_date,
            'notice_date' => $request->notice_date,
            'notice_period' => $request->notice_period,
            'reason' => $request->reason,
            'description' => $request->description,
            'status' => 'planned',
            'created_by' => creatorId(),
        ];

        // Handle document from media library
        if ($request->has('documents')) {
            $terminationData['documents'] = $request->documents;
        }

        Termination::create($terminationData);

        return redirect()->back()->with('success', __('Termination created successfully'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Termination $termination)
    {
        // Check if termination belongs to current company
        if (!in_array($termination->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this termination'));
        }

        // Convert checkbox values to proper booleans before validation
        if ($request->has('exit_interview_conducted')) {
            $request->merge([
                'exit_interview_conducted' => $request->exit_interview_conducted === 'true' ||
                    $request->exit_interview_conducted === '1' ||
                    $request->exit_interview_conducted === 1 ||
                    $request->exit_interview_conducted === true
            ]);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:users,id',
            'termination_type' => 'required|string|max:255',
            'termination_date' => 'required|date',
            'notice_date' => 'required|date|before_or_equal:termination_date',
            'notice_period' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:planned,in progress,completed',
            'documents' => 'nullable|string',
            'exit_feedback' => 'nullable|string',
            'exit_interview_conducted' => 'nullable|boolean',
            'exit_interview_date' => 'nullable|date|required_if:exit_interview_conducted,true',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Check if employee belongs to current company
        $employee = User::find($request->employee_id);
        if (!$employee || !in_array($employee->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', 'Invalid employee selected');
        }

        $terminationData = [
            'employee_id' => $request->employee_id,
            'termination_type' => $request->termination_type,
            'termination_date' => $request->termination_date,
            'notice_date' => $request->notice_date,
            'notice_period' => $request->notice_period,
            'reason' => $request->reason,
            'description' => $request->description,
            'exit_feedback' => $request->exit_feedback,
            'exit_interview_conducted' => $request->exit_interview_conducted ?? false,
            'exit_interview_date' => $request->exit_interview_date,
        ];

        // Update status if provided and different from current
        if ($request->has('status') && $request->status !== $termination->status) {
            $terminationData['status'] = $request->status;

            // If status is being set to in progress or completed, set approved_by and approved_at
            if (in_array($request->status, ['in progress', 'completed']) && !$termination->approved_by) {
                $terminationData['approved_by'] = auth()->id();
                $terminationData['approved_at'] = now();
            }
        }

        // Handle document from media library
        if ($request->has('documents')) {
            $terminationData['documents'] = $request->documents;
        }

        $termination->update($terminationData);

        return redirect()->back()->with('success', __('Termination updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Termination $termination)
    {
        // Check if termination belongs to current company
        if (!in_array($termination->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to delete this termination'));
        }
        $termination->delete();

        return redirect()->route('hr.terminations.index')->with('success', __('Termination deleted successfully'));
    }

    /**
     * Change the status of the termination.
     */
    public function changeStatus(Request $request, Termination $termination)
    {
        // Check if termination belongs to current company
        if (!in_array($termination->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this termination'));
        }

        // Convert checkbox values to proper booleans before validation
        if ($request->has('exit_interview_conducted')) {
            $request->merge([
                'exit_interview_conducted' => $request->exit_interview_conducted === 'true' ||
                    $request->exit_interview_conducted === '1' ||
                    $request->exit_interview_conducted === 1 ||
                    $request->exit_interview_conducted === true
            ]);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:planned,in progress,completed',
            'exit_feedback' => 'nullable|string|required_if:status,completed',
            'exit_interview_conducted' => 'nullable|boolean',
            'exit_interview_date' => 'nullable|date|required_if:exit_interview_conducted,true',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $updateData = [
            'status' => $request->status,
        ];

        // If status is being set to in progress or completed, set approved_by and approved_at
        if (in_array($request->status, ['in progress', 'completed']) && !$termination->approved_by) {
            $updateData['approved_by'] = auth()->id();
            $updateData['approved_at'] = now();
        }

        // If status is completed, update exit interview details
        if ($request->status === 'completed') {
            $updateData['exit_feedback'] = $request->exit_feedback;
            $updateData['exit_interview_conducted'] = $request->exit_interview_conducted ?? false;
            $updateData['exit_interview_date'] = $request->exit_interview_date;
        }

        $termination->update($updateData);

        return redirect()->back()->with('success', __('Termination status updated successfully'));
    }

    /**
     * Download document file.
     */
    public function downloadDocument(Termination $termination)
    {
        // Check if termination belongs to current company
        if (!in_array($termination->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to access this document'));
        }

        if (!$termination->documents) {
            return redirect()->back()->with('error', __('Document file not found'));
        }

        $filePath = getStorageFilePath($termination->documents);

        if (!file_exists($filePath)) {
            return redirect()->back()->with('error', __('Document file not found'));
        }

        return response()->download($filePath);
    }
}
