<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVersion extends DomainModel
{
    protected $hidden = ['path', 'active_product_id'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'is_active' => 'boolean',
            'released_at' => 'immutable_datetime',
            'bytes' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function performances(): HasMany
    {
        return $this->hasMany(ProductPerformance::class, 'version_id');
    }
}
