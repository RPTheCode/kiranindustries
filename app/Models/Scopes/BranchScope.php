<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Session;

class BranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $table = $model->getTable();

        if (session()->has('active_branch_id')) {
            $builder->where(function ($query) use ($table) {
                $query->where($table . '.branch_id', session('active_branch_id'))
                      ->orWhereNull($table . '.branch_id');
            });
        } else {
            // If no specific branch is selected, only show records from active branches
            // This prevents data from inactive branches appearing in "All Branches" view
            $builder->where(function($q) use ($table) {
                $q->whereIn($table . '.branch_id', function($sub) {
                    $sub->select('id')->from('branches')->where('status', 'active');
                })->orWhereNull($table . '.branch_id');
            });
        }
    }
}
