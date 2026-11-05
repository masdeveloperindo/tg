<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RebateEntry extends AppendOnlyModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'lots' => 'decimal:4',
            'amount_usd' => 'decimal:4',
        ];
    }

    public function tradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class);
    }

    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(RebateEntry::class, 'correction_of_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(RebateEntry::class, 'correction_of_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
