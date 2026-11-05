<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends DomainModel
{
    protected $hidden = ['destination_enc', 'evidence_path', 'bank_reference', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'amount_usd' => 'decimal:4',
            'fx_rate_idr' => 'decimal:6',
            'amount_idr' => 'decimal:2',
            'destination_enc' => 'encrypted',
            'status' => WithdrawalStatus::class,
            'reserved_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
