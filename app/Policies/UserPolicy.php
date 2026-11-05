<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Security\Access;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::isVerifiedAdmin($user);
    }

    public function view(User $user, User $subject): bool
    {
        return Access::isVerifiedAdmin($user);
    }

    public function resetPassword(User $user, User $subject): bool
    {
        return Access::hasRecentReauthentication($user)
            && $subject->role === UserRole::Client
            && (string) $user->getAuthIdentifier() !== (string) $subject->getAuthIdentifier();
    }
}
