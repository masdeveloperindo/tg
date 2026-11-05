<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseLog extends CreatedOnlyModel
{
    protected $hidden = ['ip_enc', 'ip_hash'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'ip_enc' => 'encrypted',
            'latency_ms' => 'integer',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
