<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Withdrawal;
use App\Security\Access;

class WithdrawalPolicy
{
    public function view(User $user, Withdrawal $withdrawal): bool
    {
        return Access::isOwner($user, (int) $withdrawal->user_id) || Access::isVerifiedAdmin($user);
    }
}
