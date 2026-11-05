<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Faq extends DomainModel
{
    protected $table = 'faqs';

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
