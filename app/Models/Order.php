<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends DomainModel
{
    protected $hidden = ['payment_note', 'payment_reference', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'account_type' => AccountType::class,
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'coverage_year' => 'integer',
            'price_locked_idr' => 'decimal:2',
            'expires_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'payment_checked_at' => 'immutable_datetime',
        ];
    }

    public function checkoutQuote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function renewalLicense(): BelongsTo
    {
        return $this->belongsTo(License::class, 'renewal_license_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function issuedLicense(): HasOne
    {
        return $this->hasOne(License::class, 'issued_order_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
