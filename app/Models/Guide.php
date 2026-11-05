<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guide extends DomainModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(GuideStep::class);
    }
}
