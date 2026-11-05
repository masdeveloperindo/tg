<?php

namespace App\Security;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

final class Access
{
    public const ADMIN_IDLE_SECONDS = 1800;

    public const REAUTHENTICATION_SECONDS = 600;

    public static function adminSessionIsFresh(User $user): bool
    {
        $request = request();
        if (! $request->hasSession()) {
            return false;
        }

        $verifiedId = $request->session()->get('auth.totp_user_id');
        if ($verifiedId !== null) {
            return (is_int($verifiedId) || is_string($verifiedId))
                && (string) $verifiedId === (string) $user->getAuthIdentifier()
                && self::timestampIsRecent($request->session()->get('auth.admin_last_activity'), self::ADMIN_IDLE_SECONDS);
        }

        return self::timestampIsRecent($request->session()->get('auth.password_confirmed_at'), self::ADMIN_IDLE_SECONDS);
    }

    public static function isVerifiedAdmin(User $user): bool
    {
        if ($user->role !== UserRole::Admin || $user->status !== UserStatus::Active || $user->must_change_password) {
            return false;
        }

        if ($user->totp_enabled_at === null || ! $user->getRawOriginal('totp_secret_enc')) {
            return false;
        }

        $request = request();
        if (! $request->hasSession()) {
            return false;
        }

        $verifiedId = $request->session()->get('auth.totp_user_id');

        return (is_int($verifiedId) || is_string($verifiedId))
            && (string) $verifiedId === (string) $user->getAuthIdentifier()
            && self::timestampIsPast($request->session()->get('auth.totp_verified_at'))
            && self::timestampIsRecent($request->session()->get('auth.admin_last_activity'), self::ADMIN_IDLE_SECONDS);
    }

    public static function hasRecentReauthentication(User $user): bool
    {
        return self::isVerifiedAdmin($user)
            && self::timestampIsRecent(request()->session()->get('auth.reauthenticated_at'), self::REAUTHENTICATION_SECONDS);
    }

    public static function isOwner(User $user, int $ownerId): bool
    {
        return $user->role === UserRole::Client
            && $user->status === UserStatus::Active
            && ! $user->must_change_password
            && (int) $user->getAuthIdentifier() === $ownerId;
    }

    public static function timestampIsRecent(mixed $timestamp, int $maximumAge): bool
    {
        return self::timestampIsPast($timestamp) && now()->getTimestamp() - (int) $timestamp <= $maximumAge;
    }

    public static function timestampIsPast(mixed $timestamp): bool
    {
        if (! is_int($timestamp) && ! (is_string($timestamp) && ctype_digit($timestamp))) {
            return false;
        }

        $timestamp = (int) $timestamp;

        return $timestamp > 0 && $timestamp <= now()->getTimestamp();
    }
}
