<?php

namespace App\Models;

class CheckoutQuote extends AppendOnlyModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['payment_details', 'pricing_snapshot'];

    protected function casts(): array
    {
        return [...parent::casts(), 'amount_idr' => 'decimal:2', 'coverage_year' => 'integer', 'payment_minutes' => 'integer', 'coverage_expires_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'pricing_snapshot' => 'array', 'payment_details' => 'encrypted:array'];
    }
}
