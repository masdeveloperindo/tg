<?php

namespace App\Models;

class Page extends DomainModel
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'is_published' => 'boolean',
        ];
    }
}
