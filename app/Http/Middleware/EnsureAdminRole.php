<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === UserRole::Admin && $request->user()?->status === UserStatus::Active, 403);

        return $next($request);
    }
}
