<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->status === UserStatus::Active) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user === null) {
            return redirect()->route('login');
        }

        abort(403);
    }
}
