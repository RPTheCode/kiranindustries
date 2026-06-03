<?php

namespace App\Http\Controllers;

use App\Models\PfMaster;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PfMasterController extends Controller
{
    public function index()
    {
        $pfs = PfMaster::get();
        return Inertia::render('hr/masters/pfs/index', [
            'pfs' => $pfs
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'percentage_employee' => 'required|numeric|min:0|max:100',
            'percentage_employer' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string'
        ]);

        PfMaster::create([
            'name' => $request->name,
            'percentage_employee' => $request->percentage_employee,
            'percentage_employer' => $request->percentage_employer,
            'description' => $request->description,
            'created_by' => creatorId()
        ]);

        return redirect()->back()->with('success', 'PF Master created successfully');
    }

    public function update(Request $request, PfMaster $pfMaster)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'percentage_employee' => 'required|numeric|min:0|max:100',
            'percentage_employer' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string'
        ]);

        $pfMaster->update($request->only('name', 'percentage_employee', 'percentage_employer', 'description'));

        return redirect()->back()->with('success', 'PF Master updated successfully');
    }

    public function destroy(PfMaster $pfMaster)
    {
        $pfMaster->delete();
        return redirect()->back()->with('success', 'PF Master deleted successfully');
    }
}
