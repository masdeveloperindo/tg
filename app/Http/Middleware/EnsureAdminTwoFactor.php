<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Security\Access;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->role === UserRole::Admin && $user->status === UserStatus::Active, 403);

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        if ($user->totp_enabled_at === null || ! $user->getRawOriginal('totp_secret_enc')) {
            $request->session()->forget(['auth.totp_user_id', 'auth.totp_verified_at', 'auth.admin_last_activity', 'auth.reauthenticated_at']);

            return redirect()->route('admin.2fa.setup');
        }

        $verifiedId = $request->session()->get('auth.totp_user_id');
        if ((! is_int($verifiedId) && ! is_string($verifiedId)) || (string) $verifiedId !== (string) $user->getAuthIdentifier()) {
            $request->session()->forget(['auth.totp_user_id', 'auth.totp_verified_at', 'auth.admin_last_activity', 'auth.reauthenticated_at']);

            return redirect()->route('admin.2fa.challenge');
        }

        if (! Access::timestampIsPast($request->session()->get('auth.totp_verified_at'))
            || ! Access::timestampIsRecent($request->session()->get('auth.admin_last_activity'), Access::ADMIN_IDLE_SECONDS)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        $request->session()->put('auth.admin_last_activity', now()->getTimestamp());

        return $next($request);
    }
}
