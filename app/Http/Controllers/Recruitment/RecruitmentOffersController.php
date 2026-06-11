<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Offer;
use App\Models\OfferTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecruitmentOffersController extends Controller
{
    public function index(Request $request)
    {
        $companyUserIds = getCompanyAndUsersId();
        $tab = $request->get('tab', 'offers');

        $offersQuery = Offer::withPermissionCheck()
            ->with(['candidate', 'job', 'department', 'approver'])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $offersQuery->whereHas('candidate', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $offersQuery->where('status', $request->status);
        }

        $selectionCandidates = Candidate::withPermissionCheck()
            ->with(['job:id,title', 'offers'])
            ->whereIn('status', ['Offer', 'Hired'])
            ->orderByDesc('updated_at')
            ->get();

        $candidates = Candidate::whereIn('created_by', $companyUserIds)
            ->select('id', 'first_name', 'last_name')->orderBy('first_name')->get();

        $departments = Department::with('branch')
            ->whereIn('created_by', $companyUserIds)
            ->where('status', 'active')
            ->select('id', 'name', 'branch_id')->get();

        $employees = User::whereIn('created_by', $companyUserIds)
            ->whereIn('type', ['manager', 'hr'])
            ->select('id', 'name')->get();

        $currentUser = auth()->user();
        if ($currentUser && ! $employees->contains('id', $currentUser->id)) {
            $employees->push($currentUser);
        }

        $offerTemplates = OfferTemplate::whereIn('created_by', $companyUserIds)
            ->orderBy('name')->get();

        return Inertia::render('hr/recruitment/offers/workspace', [
            'tab' => $tab,
            'offers' => $offersQuery->paginate($request->per_page ?? 12)->withQueryString(),
            'selectionCandidates' => $selectionCandidates,
            'offerTemplates' => $offerTemplates,
            'candidates' => $candidates,
            'departments' => $departments,
            'employees' => $employees,
            'currentUser' => $currentUser,
            'filters' => $request->only(['search', 'status', 'tab', 'per_page']),
        ]);
    }
}
