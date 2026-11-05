<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Security\Access;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::isVerifiedAdmin($user);
    }

    public function create(User $user): bool
    {
        return Access::isVerifiedAdmin($user);
    }

    public function update(User $user, Product $product): bool
    {
        return Access::isVerifiedAdmin($user);
    }

    public function delete(User $user, Product $product): bool
    {
        return Access::isVerifiedAdmin($user);
    }
}
