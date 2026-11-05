<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends DomainModel
{
    protected $hidden = ['consent_record'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'rating' => 'integer',
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
