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
            'pf_pct' => 'nullable|numeric|min:0|max:100',
            'fpf_pct' => 'nullable|numeric|min:0|max:100',
            'pf_admin_charge_pct' => 'nullable|numeric|min:0|max:100',
            'max_pf_amount' => 'nullable|numeric|min:0',
            'esic_employee_pct' => 'nullable|numeric|min:0|max:100',
            'esic_employer_pct' => 'nullable|numeric|min:0|max:100',
            'esic_wage_limit' => 'nullable|numeric|min:0',
        ]);

        $corePf = (float) ($validated['pf_pct'] ?? 0) + (float) ($validated['fpf_pct'] ?? 0);
        $validated['pf_employee_pct'] = $corePf;
        $validated['total_pf_pct'] = $corePf;
        $validated['pf_employer_pct'] = $corePf + (float) ($validated['pf_admin_charge_pct'] ?? 0);
        $validated['esic_pct'] = $validated['esic_employee_pct'] ?? null;

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
            return $this->normalizeParametersForUi($parameters->toArray());
        }

        return $this->emptyParameters($financialYear);
    }

    private function normalizeParametersForUi(array $data): array
    {
        if (empty($data['pf_pct']) && empty($data['fpf_pct'])) {
            $data['pf_pct'] = 8.33;
            $data['fpf_pct'] = 3.67;
        }
        if (empty($data['pf_admin_charge_pct'])) {
            $data['pf_admin_charge_pct'] = 1;
        }
        $core = (float) ($data['pf_pct'] ?? 0) + (float) ($data['fpf_pct'] ?? 0);
        if ($core > 0) {
            $data['pf_employee_pct'] = $core;
        } elseif (empty($data['pf_employee_pct']) && ! empty($data['total_pf_pct'])) {
            $data['pf_employee_pct'] = $data['total_pf_pct'];
        }
        if (empty($data['esic_employee_pct']) && ! empty($data['esic_pct'])) {
            $data['esic_employee_pct'] = $data['esic_pct'];
        }
        if (empty($data['esic_employer_pct'])) {
            $data['esic_employer_pct'] = 3.25;
        }
        if (empty($data['esic_wage_limit'])) {
            $data['esic_wage_limit'] = 21000;
        }

        return $data;
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
            'pf_pct' => '',
            'fpf_pct' => '',
            'pf_admin_charge_pct' => '',
            'max_pf_amount' => '',
            'esic_employee_pct' => '',
            'esic_employer_pct' => '',
            'esic_wage_limit' => '',
        ];
    }
}
