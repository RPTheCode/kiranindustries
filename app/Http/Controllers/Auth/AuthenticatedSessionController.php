<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    use \App\Traits\LogsActivity;

    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        $demoBusinesses = [];

        if (config('app.is_demo')) {
            // Get the company user
            $companyUser = \App\Models\User::where('email', 'company@example.com')->first();
        }

        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'settings' => settings(),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $this->logActivity('Authentication', 'logged_in', 'User successfully logged into the system.');

        // Restore last active branch if available
        $user = $request->user();
        if ($user->last_active_branch_id) {
            $branch = $user->branches()->where('branches.id', $user->last_active_branch_id)->first();
            if ($branch) {
                session(['active_branch_id' => $branch->id]);
            } else {
                // If last active is invalid, pick the first available
                $firstBranch = $user->branches()->first();
                if ($firstBranch) {
                    session(['active_branch_id' => $firstBranch->id]);
                    $user->update(['last_active_branch_id' => $firstBranch->id]);
                }
            }
        } else {
            // No last active, set first available for non-company users
            if ($user->type !== 'company' && !$user->isSuperAdmin()) {
                $firstBranch = $user->branches()->first();
                if ($firstBranch) {
                    session(['active_branch_id' => $firstBranch->id]);
                    $user->update(['last_active_branch_id' => $firstBranch->id]);
                }
            }
        }

        // Check if email verification is enabled and user is not verified
        $emailVerificationEnabled = getSetting('emailVerification', false);
        if ($emailVerificationEnabled && !$request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->route('dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->logActivity('Authentication', 'logged_out', 'User logged out of the system.');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
