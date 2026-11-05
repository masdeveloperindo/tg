<?php

namespace App\Models;

use App\Enums\FlagSeverity;
use App\Enums\LicenseFlagStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseFlag extends DomainModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'severity' => FlagSeverity::class,
            'status' => LicenseFlagStatus::class,
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
