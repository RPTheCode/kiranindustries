<?php

namespace App\Http\Controllers;

use App\Models\BankMaster;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankMasterController extends Controller
{
    use Concerns\LogsMasterCrud;
    public function index()
    {
        $banks = BankMaster::get();
        return Inertia::render('hr/masters/banks/index', [
            'banks' => $banks
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                \Illuminate\Validation\Rule::unique('bank_masters')->where(function ($query) {
                    return $query->whereIn('created_by', getCompanyAndUsersId());
                })
            ],
            'bank_name' => 'required|string|max:255',
            'branch_name' => 'required|string|max:255',
            'ifsc_code' => 'required|string|max:20'
        ]);

        $bank = BankMaster::create([
            'code' => $request->code,
            'bank_name' => $request->bank_name,
            'branch_name' => $request->branch_name,
            'ifsc_code' => $request->ifsc_code,
            'created_by' => creatorId(),
            'branch_id' => session('active_branch_id'),
        ]);
        $this->logMasterCreated($bank);

            return redirect()->back()->with('success', 'Bank Master created successfully');
    }

    public function update(Request $request, BankMaster $bankMaster)
    {
        $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                \Illuminate\Validation\Rule::unique('bank_masters')->where(function ($query) {
                    return $query->whereIn('created_by', getCompanyAndUsersId());
                })->ignore($bankMaster->id)
            ],
            'bank_name' => 'required|string|max:255',
            'branch_name' => 'required|string|max:255',
            'ifsc_code' => 'required|string|max:20'
        ]);

        $bankMaster->update($request->only('code', 'bank_name', 'branch_name', 'ifsc_code'));
        $this->logMasterUpdated($bankMaster);

            return redirect()->back()->with('success', 'Bank Master updated successfully');
    }

    public function destroy(BankMaster $bankMaster)
    {
        $this->logMasterDeleted($bankMaster);
        $bankMaster->delete();
            return redirect()->back()->with('success', 'Bank Master deleted successfully');
    }
}
