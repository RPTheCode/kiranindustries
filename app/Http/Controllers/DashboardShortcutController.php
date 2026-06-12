<?php

namespace App\Http\Controllers;

use App\Services\Nav\DashboardShortcutService;
use Illuminate\Http\Request;

class DashboardShortcutController extends Controller
{
    public function __construct(
        private readonly DashboardShortcutService $shortcuts
    ) {}

    public function update(Request $request)
    {
        $validated = $request->validate([
            'shortcuts' => ['required', 'array', 'max:'.DashboardShortcutService::MAX_SHORTCUTS],
            'shortcuts.*' => ['required', 'string', 'max:512'],
        ]);

        $user = $request->user();
        $saved = $this->shortcuts->saveForUser($user, $validated['shortcuts']);

        if ($request->header('X-Inertia')) {
            return back()->with('success', __('Quick access saved.'));
        }

        return response()->json([
            'shortcuts' => $saved,
            'message' => __('Quick access saved.'),
        ]);
    }

    public function updateVisibility(Request $request)
    {
        $validated = $request->validate([
            'hidden' => ['required', 'boolean'],
        ]);

        $hidden = $this->shortcuts->setHiddenForUser($request->user(), $validated['hidden']);

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json([
            'hidden' => $hidden,
        ]);
    }
}
