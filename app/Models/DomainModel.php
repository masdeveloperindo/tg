<?php

namespace App\Models;

use App\Models\Concerns\StoresUtcDatetimes;
use Illuminate\Database\Eloquent\Model;

abstract class DomainModel extends Model
{
    use StoresUtcDatetimes;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    // Write actions explicitly assign authorized fields; request data is not trusted.
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
