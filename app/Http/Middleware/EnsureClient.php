<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->role === UserRole::Client && $user->status === UserStatus::Active, 403);

        return $next($request);
    }
}
