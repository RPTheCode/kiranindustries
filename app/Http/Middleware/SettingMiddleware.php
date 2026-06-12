<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class SettingMiddleware
{

    public function handle(Request $request, Closure $next): Response
    {
        $tz = companyDisplayTimezone();
        Config::set('app.timezone', $tz);
        date_default_timezone_set($tz);
        return $next($request);
    }
}
