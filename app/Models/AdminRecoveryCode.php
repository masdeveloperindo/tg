<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminRecoveryCode extends CreatedOnlyModel
{
    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'used_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
