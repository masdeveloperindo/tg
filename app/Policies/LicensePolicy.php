<?php

namespace App\Policies;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\User;
use App\Security\Access;

class LicensePolicy
{
    public function manage(User $user, License $license): bool
    {
        return Access::hasRecentReauthentication($user);
    }

    public function view(User $user, License $license): bool
    {
        return Access::isOwner($user, (int) $license->user_id) || Access::isVerifiedAdmin($user);
    }

    public function download(User $user, License $license): bool
    {
        return Access::isOwner($user, (int) $license->user_id)
            && $license->status === LicenseStatus::Active
            && $license->expires_at !== null
            && $license->expires_at->isFuture();
    }
}
