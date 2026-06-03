<?php

namespace App\Http\Controllers;

use App\Models\Overtime;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OvertimeController extends Controller
{
    use Concerns\LogsMasterCrud;
    public function index()
    {
        $overtimes = Overtime::all();
        return Inertia::render('hr/masters/overtimes/index', [
            'overtimes' => $overtimes
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $overtime = Overtime::create($request->only('name'));
        $this->logMasterCreated($overtime);

            return redirect()->back()->with('success', 'Overtime option created successfully.');
    }

    public function update(Request $request, Overtime $overtime)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $overtime->update($request->only('name'));
        $this->logMasterUpdated($overtime);

            return redirect()->back()->with('success', 'Overtime option updated successfully.');
    }

    public function destroy(Overtime $overtime)
    {
        $this->logMasterDeleted($overtime);
        $overtime->delete();
            return redirect()->back()->with('success', 'Overtime option deleted successfully.');
    }
}
