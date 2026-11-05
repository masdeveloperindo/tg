<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Broker extends DomainModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'rate_usd_per_lot' => 'decimal:4',
            'valid_from' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }

    public function tradingAccounts(): HasMany
    {
        return $this->hasMany(TradingAccount::class);
    }
}
