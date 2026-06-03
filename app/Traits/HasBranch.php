<?php

namespace App\Traits;

use App\Models\Scopes\BranchScope;
use Illuminate\Support\Facades\Session;

trait HasBranch
{
    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model) {
            // Only auto-assign branch if not already set AND not created by company (ID 1)
            // Or if you want branch users to always be restricted, but company can choose.
            if (session()->has('active_branch_id') && !$model->branch_id) {
                if (auth()->check() && auth()->user()->type !== 'company') {
                    $model->branch_id = session('active_branch_id');
                }
            }
        });
    }
}
