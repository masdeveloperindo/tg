<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends AppendOnlyModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'summary_json' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
