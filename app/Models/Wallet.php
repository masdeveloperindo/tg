<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends DomainModel
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
