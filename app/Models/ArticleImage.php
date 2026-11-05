<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleImage extends CreatedOnlyModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'bytes' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
