<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\LicenseStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends DomainModel
{
    protected $hidden = ['key_hash', 'key_ciphertext'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'key_ciphertext' => 'encrypted',
            'account_type' => AccountType::class,
            'status' => LicenseStatus::class,
            'coverage_year' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function issuedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'issued_order_id');
    }

    public function renewalOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'renewal_license_id');
    }

    public function accountChangeRequests(): HasMany
    {
        return $this->hasMany(LicenseAccountChangeRequest::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LicenseLog::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(LicenseFlag::class);
    }
}
