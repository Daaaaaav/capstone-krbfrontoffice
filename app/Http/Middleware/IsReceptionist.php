<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class IsReceptionist
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        $roleName = $user->role->name ?? $user->role ?? null;

        if ($user && $roleName === 'Receptionist') {
            return $next($request);
        }
        else if ($user && $roleName === 'Manager') {
            return $next($request);
        }

        abort(403, 'Unauthorized');
    }
}
