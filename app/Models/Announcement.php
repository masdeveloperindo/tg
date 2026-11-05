<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends DomainModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'audience' => AnnouncementAudience::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
