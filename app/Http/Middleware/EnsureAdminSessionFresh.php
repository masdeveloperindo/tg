<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use App\Security\Access;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSessionFresh
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->role === UserRole::Admin && ! Access::adminSessionIsFresh($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
