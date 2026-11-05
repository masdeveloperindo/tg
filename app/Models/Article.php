<?php

namespace App\Models;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends DomainModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'category' => ArticleCategory::class,
            'visibility' => ArticleVisibility::class,
            'status' => ArticleStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(ArticleImage::class);
    }
}
