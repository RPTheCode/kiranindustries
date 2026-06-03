<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Branch;

class HandleBranchScope
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->type === 'company') {
            // Get all branches for this company
            $branches = Branch::where('created_by', $user->id)
                ->orWhere('created_by', $user->created_by)
                ->orderBy('name')
                ->get(['id', 'name']);

            if ($branches->isNotEmpty()) {
                // If active_branch_id is not set or invalid, set it to the first branch
                if (!session()->has('active_branch_id') || !$branches->contains('id', session('active_branch_id'))) {
                    session(['active_branch_id' => $branches->first()->id]);
                }

                // Share global data with Inertia
                Inertia::share([
                    'branches' => $branches,
                    'active_branch_id' => session('active_branch_id'),
                ]);
            }
        }

        return $next($request);
    }
}
