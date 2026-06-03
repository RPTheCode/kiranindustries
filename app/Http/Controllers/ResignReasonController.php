<?php

namespace App\Http\Controllers;

use App\Models\ResignReason;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResignReasonController extends Controller
{
    use Concerns\LogsMasterCrud;
    public function index()
    {
        $reasons = ResignReason::all();
        return Inertia::render('hr/masters/resign-reasons/index', [
            'reasons' => $reasons
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
        ]);

        $reason = ResignReason::create($request->only('name', 'code'));
        $this->logMasterCreated($reason);

            return redirect()->back()->with('success', 'Resign reason created successfully.');
    }

    public function update(Request $request, ResignReason $resignReason)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
        ]);

        $resignReason->update($request->only('name', 'code'));
        $this->logMasterUpdated($resignReason);

            return redirect()->back()->with('success', 'Resign reason updated successfully.');
    }

    public function destroy(ResignReason $resignReason)
    {
        $this->logMasterDeleted($resignReason);
        $resignReason->delete();
            return redirect()->back()->with('success', 'Resign reason deleted successfully.');
    }
}
