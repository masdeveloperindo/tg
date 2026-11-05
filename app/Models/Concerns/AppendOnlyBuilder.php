<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Prevents mutations through Eloquent, including bulk writes and quiet methods.
 * Raw SQL remains privileged and must not be used by business actions to rewrite history.
 */
class AppendOnlyBuilder extends Builder
{
    public function update(array $values)
    {
        throw new LogicException('Catatan append-only tidak dapat diubah.');
    }

    public function delete()
    {
        throw new LogicException('Catatan append-only tidak dapat dihapus.');
    }

    public function forceDelete()
    {
        throw new LogicException('Catatan append-only tidak dapat dihapus.');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new LogicException('Gunakan insert untuk catatan append-only, bukan upsert.');
    }

    public function updateOrInsert(array $attributes, array|callable $values = [])
    {
        throw new LogicException('Gunakan insert untuk catatan append-only, bukan updateOrInsert.');
    }

    public function touch($column = null)
    {
        throw new LogicException('Catatan append-only tidak dapat diubah.');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Catatan append-only tidak dapat diubah.');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Catatan append-only tidak dapat diubah.');
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        throw new LogicException('Catatan append-only tidak dapat diubah.');
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        throw new LogicException('Catatan append-only tidak dapat diubah.');
    }

    public function truncate(): never
    {
        throw new LogicException('Catatan append-only tidak dapat dihapus.');
    }
}
