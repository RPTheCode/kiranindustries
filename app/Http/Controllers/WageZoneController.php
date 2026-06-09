<?php

namespace App\Http\Controllers;

use App\Models\SkillWageRate;
use App\Models\WageZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WageZoneController extends Controller
{
    use Concerns\LogsMasterCrud;

    public function store(Request $request)
    {
        $companyUserIds = getCompanyAndUsersId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('wage_zones')->where(fn ($query) => $query->whereIn('created_by', $companyUserIds)),
            ],
            'state' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'working_days' => 'required|integer|min:1|max:31',
            'status' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $zone = WageZone::create([
            ...$validated,
            'country' => $validated['country'] ?? 'India',
            'status' => $request->boolean('status', true),
            'created_by' => creatorId(),
        ]);

        $this->logMasterCreated($zone);

        return redirect()->back()->with('success', __('Wage zone created successfully.'));
    }

    public function update(Request $request, WageZone $wageZone)
    {
        $this->assertCompanyAccess($wageZone);
        $companyUserIds = getCompanyAndUsersId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('wage_zones')->where(fn ($query) => $query->whereIn('created_by', $companyUserIds))->ignore($wageZone->id),
            ],
            'state' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'working_days' => 'required|integer|min:1|max:31',
            'status' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $wageZone->update([
            ...$validated,
            'country' => $validated['country'] ?? $wageZone->country ?? 'India',
            'status' => $request->boolean('status', $wageZone->status),
        ]);

        $this->logMasterUpdated($wageZone);

        return redirect()->back()->with('success', __('Wage zone updated successfully.'));
    }

    public function destroy(WageZone $wageZone)
    {
        $this->assertCompanyAccess($wageZone);

        if ($wageZone->branches()->exists()) {
            return redirect()->back()->with('error', __('Cannot delete: this zone is linked to one or more branches.'));
        }

        $this->logMasterDeleted($wageZone);
        $wageZone->delete();

        return redirect()->back()->with('success', __('Wage zone deleted successfully.'));
    }

    public function toggleStatus(WageZone $wageZone)
    {
        $this->assertCompanyAccess($wageZone);

        $wageZone->update(['status' => ! $wageZone->status]);

        return redirect()->back()->with('success', __('Wage zone status updated successfully.'));
    }

    private function assertCompanyAccess(WageZone $wageZone): void
    {
        abort_unless(in_array((int) $wageZone->created_by, getCompanyAndUsersId(), true), 403);
    }
}
