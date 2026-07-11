<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::user();
                $role = $user->role->name ?? $user->role ?? null;

                // If role is unrecognized, log out and let them reach the guest page
                if (!in_array($role, ['Manager', 'Admin', 'Superadmin', 'Receptionist'])) {
                    Auth::guard($guard)->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return $next($request);
                }

                $routeName = match ($role) {
                    'Manager', 'Admin', 'Superadmin' => 'manager.dashboard',
                    'Receptionist'                   => 'receptionist.dashboard',
                };

                return redirect()->route($routeName);
            }
        }

        return $next($request);
    }
}
