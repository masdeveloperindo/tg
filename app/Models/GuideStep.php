<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuideStep extends DomainModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'step_no' => 'integer',
        ];
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }
}
