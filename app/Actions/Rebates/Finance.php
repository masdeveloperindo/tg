<?php

namespace App\Actions\Rebates;

use App\Models\User;
use App\Security\Access;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class Finance
{
    public static function admin(bool $reauth = true): User
    {
        $user = Auth::user()?->fresh();
        abort_unless($user && ($reauth ? Access::hasRecentReauthentication($user) : Access::isVerifiedAdmin($user)), 403);

        return $user;
    }

    public static function owner(): User
    {
        $user = Auth::user()?->fresh();
        abort_unless($user && Access::isOwner($user, $user->id), 403);

        return $user;
    }

    public static function decimal(string $value, int $scale = 4, int $whole = 16, bool $positive = false): string
    {
        if (! preg_match('/\A(?:0|[1-9][0-9]{0,'.($whole - 1).'})(?:\.[0-9]{1,'.$scale.'})?\z/', $value) || ($positive && bccomp($value, '0', $scale) <= 0)) {
            throw ValidationException::withMessages(['amount' => 'Gunakan nominal desimal yang valid tanpa pemisah ribuan.']);
        }

        return bcadd($value, '0', $scale);
    }

    public static function idr(string $usd, string $fx): string
    {
        $value = bcadd(bcmul($usd, $fx, 10), '0.005', 2);
        if (bccomp($value, '999999999999999999.99', 2) > 0 || bccomp($value, '0', 2) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nilai IDR di luar batas pencairan.']);
        }

        return $value;
    }

    public static function usd(string $value): string
    {
        // Display only; financial calculations retain all four decimal places.
        return 'USD '.bcadd($value, '0', 2);
    }
}
