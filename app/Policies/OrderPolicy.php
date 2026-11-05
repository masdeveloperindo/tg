<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Security\Access;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return Access::isOwner($user, (int) $order->user_id) || Access::isVerifiedAdmin($user);
    }
}
