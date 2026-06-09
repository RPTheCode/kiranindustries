<?php

namespace App\Services\SalaryPayroll;

class PfChallanBreakdownService
{
    /**
     * EPFO challan heads from stored PF components.
     *
     * @return array{
     *     pf_admin_employer: float,
     *     pf_challan_ac1: float,
     *     pf_challan_ac2: float,
     *     pf_challan_ac10: float,
     *     pf_challan_total: float
     * }
     */
    public function build(
        float $pfEmployee,
        float $pfEpsEmployer,
        float $pfEpfEmployer,
        float $pfEmployer = 0.0,
        ?float $pfAdminEmployer = null
    ): array {
        $eps = round(max(0, $pfEpsEmployer), 0);
        $epf = round(max(0, $pfEpfEmployer), 0);
        $employee = round(max(0, $pfEmployee), 0);

        $admin = $pfAdminEmployer !== null
            ? round(max(0, $pfAdminEmployer), 0)
            : max(0, round($pfEmployer > 0 ? $pfEmployer - $eps - $epf : 0, 0));

        $ac1 = $employee + $epf;
        $ac2 = $eps;
        $ac10 = $admin;

        return [
            'pf_admin_employer' => $admin,
            'pf_challan_ac1' => $ac1,
            'pf_challan_ac2' => $ac2,
            'pf_challan_ac10' => $ac10,
            'pf_challan_total' => $ac1 + $ac2 + $ac10,
        ];
    }
}
