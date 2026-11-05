<?php

namespace App\Models;

use App\Enums\OutboundTargetType;

class OutboundClick extends CreatedOnlyModel
{
    protected $hidden = ['visitor_hash'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'target_type' => OutboundTargetType::class,
        ];
    }
}
