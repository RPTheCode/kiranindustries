<?php

namespace App\Http\Controllers;

use App\Models\IncentiveDeductionType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IncentiveDeductionTypeController extends Controller
{
    public function index()
    {
        $types = IncentiveDeductionType::orderBy('type')->orderBy('name')->get();
        
        return Inertia::render('hr/masters/incentive-types/index', [
            'types' => $types
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:earning,deduction',
            'mode' => 'required|in:amount,day',
            'is_active' => 'boolean',
        ]);

        IncentiveDeductionType::create(array_merge($validated, [
            'branch_id' => auth()->user()->branch_id,
            'created_by' => auth()->id()
        ]));

        return redirect()->back()->with('success', 'Type created successfully.');
    }

    public function update(Request $request, IncentiveDeductionType $incentiveType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:earning,deduction',
            'mode' => 'required|in:amount,day',
            'is_active' => 'boolean',
        ]);

        $incentiveType->update($validated);

        return redirect()->back()->with('success', 'Type updated successfully.');
    }

    public function destroy(IncentiveDeductionType $incentiveType)
    {
        // Check if it has entries
        if ($incentiveType->details()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete type that has existing entries. Deactivate it instead.');
        }

        $incentiveType->delete();

        return redirect()->back()->with('success', 'Type deleted successfully.');
    }

    public function toggleStatus(IncentiveDeductionType $incentiveType)
    {
        $incentiveType->update([
            'is_active' => !$incentiveType->is_active
        ]);

        return redirect()->back()->with('success', 'Status updated successfully.');
    }
}
