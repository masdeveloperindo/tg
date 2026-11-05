<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;
use App\Security\Access;

class WalletPolicy
{
    public function view(User $user, Wallet $wallet): bool
    {
        return Access::isOwner($user, (int) $wallet->user_id) || Access::isVerifiedAdmin($user);
    }
}
