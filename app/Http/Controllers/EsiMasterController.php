<?php

namespace App\Http\Controllers;

use App\Models\EsiMaster;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EsiMasterController extends Controller
{
    public function index()
    {
        $esis = EsiMaster::get();
        return Inertia::render('hr/masters/esis/index', [
            'esis' => $esis
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

        EsiMaster::create([
            'name' => $request->name,
            'percentage_employee' => $request->percentage_employee,
            'percentage_employer' => $request->percentage_employer,
            'description' => $request->description,
            'created_by' => creatorId()
        ]);

        return redirect()->back()->with('success', 'ESI Master created successfully');
    }

    public function update(Request $request, EsiMaster $esiMaster)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'percentage_employee' => 'required|numeric|min:0|max:100',
            'percentage_employer' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string'
        ]);

        $esiMaster->update($request->only('name', 'percentage_employee', 'percentage_employer', 'description'));

        return redirect()->back()->with('success', 'ESI Master updated successfully');
    }

    public function destroy(EsiMaster $esiMaster)
    {
        $esiMaster->delete();
        return redirect()->back()->with('success', 'ESI Master deleted successfully');
    }
}
