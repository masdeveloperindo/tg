<?php

namespace App\Models;

use App\Models\Concerns\AppendOnlyBuilder;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

abstract class AppendOnlyModel extends CreatedOnlyModel
{
    public function newEloquentBuilder($query): AppendOnlyBuilder
    {
        return new AppendOnlyBuilder($query);
    }

    protected function performUpdate(Builder $query)
    {
        throw new LogicException('Catatan append-only tidak dapat diubah; buat catatan koreksi baru.');
    }

    protected function performDeleteOnModel()
    {
        throw new LogicException('Catatan append-only tidak dapat dihapus.');
    }

    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        throw new LogicException('Catatan append-only tidak dapat diubah; buat catatan koreksi baru.');
    }

    protected function incrementOrDecrementEach(array $columns, array $extra, string $method)
    {
        throw new LogicException('Catatan append-only tidak dapat diubah; buat catatan koreksi baru.');
    }
}
