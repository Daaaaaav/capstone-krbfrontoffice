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

                $routeName = match ($role) {
                    'Manager'      => 'manager.dashboard',
                    'Receptionist' => 'receptionist.dashboard',
                    default        => 'login',
                };

                // Abaikan intended, biar bener-bener ke role page
                return redirect()->route($routeName);
            }
        }

        return $next($request);
    }
}
