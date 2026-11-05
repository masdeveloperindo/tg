<?php

namespace App\Models\Concerns;

trait StoresUtcDatetimes
{
    public function setAttribute($key, $value)
    {
        // DATETIME carries no timezone: normalize instants before serializing them.
        // DATE casts are calendar dates and must not shift when crossing midnight.
        if ($value !== null && $value !== ''
            && in_array($this->getCasts()[$key] ?? null, ['datetime', 'immutable_datetime'], true)) {
            $value = $this->asDateTime($value)->utc();
        }

        return parent::setAttribute($key, $value);
    }
}
