<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingAccount extends DomainModel
{
    protected $hidden = ['evidence_note'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'status' => ApprovalStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(Broker::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rebateEntries(): HasMany
    {
        return $this->hasMany(RebateEntry::class);
    }
}
