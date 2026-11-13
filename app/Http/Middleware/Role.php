<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Role
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        abort_unless(in_array($request->user()->role, $roles), 403);
        abort_if($request->user()->role === 'department' && ! $request->user()->department_id, 403, 'Akun belum terhubung dengan bagian.');

        return $next($request);
    }
}
