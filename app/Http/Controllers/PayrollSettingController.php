<?php

namespace App\Http\Controllers;

use App\Models\IncomeTaxSlab;
use App\Models\PayrollParameter;
use App\Models\ProfessionalTaxSlab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PayrollSettingController extends Controller
{
    public function index(Request $request)
    {
        $defaultFinancialYear = currentFinancialYearLabel();
        $financialYearOptions = buildFinancialYearSelectOptions();

        $selectedYear = $request->filled('financial_year')
            ? $request->string('financial_year')->toString()
            : $defaultFinancialYear;

        if (! isAllowedFinancialYearOption($selectedYear)) {
            $selectedYear = $defaultFinancialYear;
        }

        $parameters = $this->resolveParametersForYear($selectedYear);
        $ptSlabs = $this->resolvePtSlabsForYear($selectedYear);
        $itSlabs = $this->resolveItSlabsForYear($selectedYear);

        return Inertia::render('hr/payroll-settings/index', [
            'parameters' => $parameters,
            'ptSlabs' => $ptSlabs,
            'itSlabs' => $itSlabs,
            'selectedFinancialYear' => $selectedYear,
            'defaultFinancialYear' => $defaultFinancialYear,
            'nextFinancialYear' => nextFinancialYearLabel(),
            'financialYearOptions' => $financialYearOptions,
        ]);
    }

    public function updateParameters(Request $request)
    {
        $validated = $request->validate([
            'financial_year' => 'required|string|max:20|in:' . implode(',', financialYearSelectOptions()),
            'pf_pct' => 'nullable|numeric',
            'fpf_pct' => 'nullable|numeric',
            'total_pf_pct' => 'nullable|numeric',
            'max_pf_amount' => 'nullable|numeric',
            'esic_pct' => 'nullable|numeric',
            'karchi_pct' => 'nullable|numeric',
            'bonus_pct' => 'nullable|numeric',
            'bonus_max_limit' => 'nullable|numeric',
            'max_el' => 'nullable|integer',
            'max_sl' => 'nullable|integer',
            'max_cl' => 'nullable|integer',
        ]);

        PayrollParameter::updateOrCreate(
            ['financial_year' => $validated['financial_year']],
            $validated
        );

        return redirect()
            ->route('hr.payroll-settings.index', ['financial_year' => $validated['financial_year']])
            ->with('success', 'Payroll parameters updated successfully');
    }

    public function updateSlabs(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:pt,it',
            'financial_year' => 'required|string|max:20|in:' . implode(',', financialYearSelectOptions()),
            'slabs' => 'required|array',
            'slabs.*.min_amt' => 'required|numeric',
            'slabs.*.max_amt' => 'nullable|numeric',
            'slabs.*.pt_amt' => 'required_if:type,pt|nullable|numeric',
            'slabs.*.it_pct' => 'required_if:type,it|nullable|numeric',
        ]);

        $type = $validated['type'];
        $financialYear = $validated['financial_year'];
        $slabs = $validated['slabs'];

        DB::transaction(function () use ($type, $financialYear, $slabs) {
            if ($type === 'pt') {
                ProfessionalTaxSlab::where('financial_year', $financialYear)->delete();
                foreach ($slabs as $slab) {
                    ProfessionalTaxSlab::create([
                        'financial_year' => $financialYear,
                        'min_amt' => $slab['min_amt'],
                        'max_amt' => $slab['max_amt'] !== '' && $slab['max_amt'] !== null ? $slab['max_amt'] : null,
                        'pt_amt' => $slab['pt_amt'],
                    ]);
                }
            } else {
                IncomeTaxSlab::where('financial_year', $financialYear)->delete();
                foreach ($slabs as $slab) {
                    IncomeTaxSlab::create([
                        'financial_year' => $financialYear,
                        'min_amt' => $slab['min_amt'],
                        'max_amt' => $slab['max_amt'] !== '' && $slab['max_amt'] !== null ? $slab['max_amt'] : null,
                        'it_pct' => $slab['it_pct'],
                    ]);
                }
            }
        });

        return redirect()
            ->route('hr.payroll-settings.index', ['financial_year' => $financialYear])
            ->with('success', strtoupper($type) . ' slabs updated successfully');
    }

    private function resolveParametersForYear(string $financialYear): array
    {
        $parameters = PayrollParameter::where('financial_year', $financialYear)->first();

        if ($parameters) {
            return $parameters->toArray();
        }

        return $this->emptyParameters($financialYear);
    }

    private function resolvePtSlabsForYear(string $financialYear): array
    {
        $slabs = ProfessionalTaxSlab::where('financial_year', $financialYear)
            ->orderBy('min_amt')
            ->get();

        if ($slabs->isNotEmpty()) {
            return $slabs->toArray();
        }

        return [];
    }

    private function resolveItSlabsForYear(string $financialYear): array
    {
        $slabs = IncomeTaxSlab::where('financial_year', $financialYear)
            ->orderBy('min_amt')
            ->get();

        if ($slabs->isNotEmpty()) {
            return $slabs->toArray();
        }

        return [];
    }

    private function emptyParameters(string $financialYear): array
    {
        return [
            'id' => null,
            'financial_year' => $financialYear,
            'total_pf_pct' => '',
            'pf_pct' => '',
            'fpf_pct' => '',
            'max_pf_amount' => '',
            'esic_pct' => '',
            'karchi_pct' => '',
            'bonus_pct' => '',
            'bonus_max_limit' => '',
            'max_el' => '',
            'max_sl' => '',
            'max_cl' => '',
        ];
    }
}
