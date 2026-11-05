<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends DomainModel
{
    protected $hidden = ['value_encrypted'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'value_encrypted' => 'encrypted',
            'is_secret' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
