<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PayrollParameter extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function forFinancialYear(?string $financialYear): ?self
    {
        if (! $financialYear) {
            return static::orderByDesc('financial_year')->first();
        }

        return static::where('financial_year', $financialYear)->first()
            ?? static::orderByDesc('financial_year')->first();
    }

    public static function pfEmployeePct(?self $params): float
    {
        if (! $params) {
            return 12.0;
        }

        $core = (float) ($params->pf_pct ?? 0) + (float) ($params->fpf_pct ?? 0);
        if ($core > 0) {
            return $core;
        }

        return (float) ($params->pf_employee_pct ?? $params->total_pf_pct ?? 12);
    }

    public static function pfEmployerCorePct(?self $params): float
    {
        if (! $params) {
            return 12.0;
        }

        $split = (float) ($params->pf_pct ?? 0) + (float) ($params->fpf_pct ?? 0);

        if ($split > 0) {
            return $split;
        }

        return (float) ($params->pf_employer_pct ?? $params->total_pf_pct ?? 12);
    }

    public static function pfAdminChargePct(?self $params): float
    {
        if (! $params) {
            return 1.0;
        }

        return (float) ($params->pf_admin_charge_pct ?? 1);
    }

    public static function pfEmployerPct(?self $params): float
    {
        return static::pfEmployerCorePct($params) + static::pfAdminChargePct($params);
    }

    public static function esicEmployeePct(?self $params): float
    {
        if (! $params) {
            return 0.75;
        }

        return (float) ($params->esic_employee_pct ?? $params->esic_pct ?? 0.75);
    }

    public static function esicEmployerPct(?self $params): float
    {
        if (! $params) {
            return 3.25;
        }

        return (float) ($params->esic_employer_pct ?? 3.25);
    }

    public static function esicWageLimit(?self $params): float
    {
        if (! $params) {
            return 21000.0;
        }

        return (float) ($params->esic_wage_limit ?? 21000);
    }

    public static function forDate($date = null): ?self
    {
        return static::forFinancialYear(financialYearLabelForDate($date));
    }
}
