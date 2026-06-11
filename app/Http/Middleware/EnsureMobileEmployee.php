<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => __('Unauthenticated.')], 401);
        }

        if ((int) $user->is_enable_login !== 1 || $user->status !== 'active') {
            return response()->json(['message' => __('Your account is not enabled for login.')], 403);
        }

        if (! userCanAccessMobileApp($user)) {
            return response()->json(['message' => __('Mobile app access is not enabled for your account.')], 403);
        }

        return $next($request);
    }
}
