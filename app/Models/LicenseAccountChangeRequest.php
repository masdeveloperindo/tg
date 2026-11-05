<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseAccountChangeRequest extends DomainModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'status' => ApprovalStatus::class,
            'requested_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
