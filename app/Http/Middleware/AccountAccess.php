<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AccountAccess
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs('logout')) {
            return $next($request);
        }
        $user = $request->user()->refresh();
        abort_unless($user->active && ($user->is_admin || $user->branch_id), 403);

        return $next($request);
    }
}
