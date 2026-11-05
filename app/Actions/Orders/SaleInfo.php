<?php

namespace App\Actions\Orders;

use App\Models\License;
use App\Models\Product;
use App\Security\Access;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class SaleInfo
{
    public function renewals(): array
    {
        $user = Auth::user();
        if (! $user || ! Access::isOwner($user, $user->id)) {
            return [];
        }
        $rows = [];
        foreach (License::where('user_id', $user->id)->where('status', 'active')->with('product')->get() as $license) {
            try {
                $sale = $this->current($license->product);
                if (! $sale || ! $sale['available']) {
                    continue;
                }
                $price = app(CalendarPricing::class)->calculate($license->product, app(CheckoutSettings::class)->pricing(), renewal: $license);
                $rows[] = ['license_id' => $license->id, 'slug' => $license->product->slug, 'name' => $license->product->name, 'mt5_account' => $license->mt5_account, 'amount_idr' => $price['amount_idr'], 'coverage_year' => $price['coverage_year']];
            } catch (ValidationException) {
                continue;
            }
        }

        return $rows;
    }

    public function current(Product $product): ?array
    {
        try {
            $settings = app(CheckoutSettings::class);
            $price = app(CalendarPricing::class)->calculate($product, $settings->pricing());
            $enabled = false;
            if (bccomp($price['amount_idr'], '0', 2) > 0 && app(Checkout::class)->available($product)) {
                $enabled = (bool) $settings->methods() && $settings->telegram() !== null;
            }

            return [...$price, 'available' => $enabled];
        } catch (ValidationException) {
            return null;
        }
    }
}
