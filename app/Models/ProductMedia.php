<?php

namespace App\Models;

use App\Enums\MediaSection;
use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends DomainModel
{
    protected $table = 'product_media';

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'media_type' => MediaType::class,
            'section' => MediaSection::class,
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
