<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class OfferController extends Controller
{
    public function index(Request $request)
    {
        $query = Offer::withPermissionCheck()->with(['candidate', 'job', 'department', 'approver']);

            if ($request->has('search') && !empty($request->search)) {
            $query->whereHas('candidate', function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                    ->orWhere('last_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('candidate_id') && !empty($request->candidate_id) && $request->candidate_id !== 'all') {
            $query->where('candidate_id', $request->candidate_id);
        }

        $query->orderBy('id', 'desc');
        $offers = $query->paginate($request->per_page ?? 10);

        $candidates = Candidate::whereIn('created_by', getCompanyAndUsersId())
            ->select('id', 'first_name', 'last_name')
            ->get();

        $departments = Department::with('branch')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->select('id', 'name', 'branch_id')
            ->get();

        $employees = User::whereIn('created_by', getCompanyAndUsersId())
            ->whereIn('type', ['manager', 'hr'])
            ->select('id', 'name')
            ->get();
        
        // Add current user to employees list
        $currentUser = auth()->user();
        if ($currentUser && !$employees->contains('id', $currentUser->id)) {
            $employees->push($currentUser);
        }

        return Inertia::render('hr/recruitment/offers/index', [
            'offers' => $offers,
            'candidates' => $candidates,
            'departments' => $departments,
            'employees' => $employees,
            'currentUser' => auth()->user(),
            'filters' => $request->all(['search', 'status', 'candidate_id', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'candidate_id' => 'required|exists:candidates,id',
            'position' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'salary' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'equity' => 'nullable|string|max:255',
            'benefits' => 'nullable|string',
            'start_date' => 'required|date|after:today',
            'expiration_date' => 'required|date|after:today',
            'approved_by' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $candidate = Candidate::find($request->candidate_id);

        Offer::create([
            'candidate_id' => $request->candidate_id,
            'job_id' => $candidate->job_id,
            'offer_date' => now(),
            'position' => $request->position,
            'department_id' => $request->department_id,
            'salary' => $request->salary,
            'bonus' => $request->bonus,
            'equity' => $request->equity,
            'benefits' => $request->benefits,
            'start_date' => $request->start_date,
            'expiration_date' => $request->expiration_date,
            'approved_by' => $request->approved_by,
            'created_by' => creatorId(),
        ]);

        return redirect()->back()->with('success', __('Offer created successfully'));
    }

    public function update(Request $request, Offer $offer)
    {
        if (!in_array($offer->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this offer'));
        }

        $validator = Validator::make($request->all(), [
            'candidate_id' => 'required|exists:candidates,id',
            'position' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'salary' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'equity' => 'nullable|string|max:255',
            'benefits' => 'nullable|string',
            'start_date' => 'required|date',
            'expiration_date' => 'required|date',
            'approved_by' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $candidate = Candidate::find($request->candidate_id);

        $offer->update([
            'candidate_id' => $request->candidate_id,
            'job_id' => $candidate->job_id,
            'position' => $request->position,
            'department_id' => $request->department_id,
            'salary' => $request->salary,
            'bonus' => $request->bonus,
            'equity' => $request->equity,
            'benefits' => $request->benefits,
            'start_date' => $request->start_date,
            'expiration_date' => $request->expiration_date,
            'approved_by' => $request->approved_by,
        ]);

        return redirect()->back()->with('success', __('Offer updated successfully'));
    }

    public function destroy(Offer $offer)
    {
        if (!in_array($offer->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to delete this offer'));
        }

        $offer->delete();
        return redirect()->back()->with('success', __('Offer deleted successfully'));
    }

    public function updateStatus(Request $request, Offer $offer)
    {
        if (!in_array($offer->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this offer'));
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Draft,Sent,Accepted,Negotiating,Declined,Expired',
            'decline_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $updateData = ['status' => $request->status];
        
        if ($request->status === 'Declined' && $request->decline_reason) {
            $updateData['decline_reason'] = $request->decline_reason;
        }

        if (in_array($request->status, ['Accepted', 'Declined'])) {
            $updateData['response_date'] = now();
        }

        $offer->update($updateData);
        return redirect()->back()->with('success', __('Offer status updated successfully'));
    }
}