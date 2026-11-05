<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseActivation extends DomainModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'account_type' => AccountType::class,
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
