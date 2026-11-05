<?php

namespace App\Models;

class ApiNonce extends CreatedOnlyModel
{
    protected $hidden = ['nonce_sha256'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'expires_at' => 'immutable_datetime',
        ];
    }
}
