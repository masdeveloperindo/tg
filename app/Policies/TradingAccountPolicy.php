<?php

namespace App\Policies;

use App\Models\TradingAccount;
use App\Models\User;
use App\Security\Access;

class TradingAccountPolicy
{
    public function view(User $user, TradingAccount $tradingAccount): bool
    {
        return Access::isOwner($user, (int) $tradingAccount->user_id) || Access::isVerifiedAdmin($user);
    }
}
