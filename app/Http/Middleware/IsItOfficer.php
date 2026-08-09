<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class IsItOfficer
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        $roleName = $user->role->name ?? $user->role ?? null;

        if ($user && in_array($roleName, ['IT Officer', 'Admin', 'Superadmin'])) {
            return $next($request);
        }

        abort(403, 'Unauthorized');
    }
}
