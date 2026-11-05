<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends DomainModel
{
    protected $hidden = ['evidence_path', 'bank_reference', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'amount_idr' => 'decimal:2',
            'status' => RefundStatus::class,
            'refunded_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
