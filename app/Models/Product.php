<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends DomainModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'sale_enabled' => 'boolean',
            'base_price_idr' => 'decimal:2',
            'minimum_price_idr' => 'decimal:2',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class);
    }

    public function performances(): HasMany
    {
        return $this->hasMany(ProductPerformance::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function guides(): HasMany
    {
        return $this->hasMany(Guide::class);
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }
}
