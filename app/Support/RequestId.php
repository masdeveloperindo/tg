<?php

namespace App\Support;

use Illuminate\Support\Str;

final class RequestId
{
    public static function current(): string
    {
        $request = request();
        $id = $request->attributes->get('request_id');
        if (! is_string($id) || ! Str::isUuid($id)) {
            $id = (string) Str::uuid();
            $request->attributes->set('request_id', $id);
        }

        return $id;
    }
}
