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

    public static function forDate($date = null): ?self
    {
        return static::forFinancialYear(financialYearLabelForDate($date));
    }
}
