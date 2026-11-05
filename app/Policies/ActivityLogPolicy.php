<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;
use App\Security\Access;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::isVerifiedAdmin($user);
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        return Access::isVerifiedAdmin($user);
    }
}
