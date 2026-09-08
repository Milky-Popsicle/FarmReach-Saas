<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login')->with('error', 'Please sign in to access this page.');
        }

        $user = Auth::user();

        if (! in_array($user->role, $roles)) {
            // Redirect to appropriate dashboard based on their actual role
            return match ($user->role) {
                'admin' => redirect()->route('admin.dashboard')->with('error', 'Unauthorized access area.'),
                'farmer' => redirect()->route('farmer.marketplace')->with('error', 'Unauthorized access area.'),
                default => redirect()->route('marketplace')->with('error', 'Unauthorized access area.'),
            };
        }

        return $next($request);
    }
}
