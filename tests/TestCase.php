<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // This runs before RefreshDatabase can migrate or reset any tables.
        if (! $app->environment('testing')
            || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'tg_test'
            || config('database.connections.mysql.url')) {
            throw new LogicException('Pengujian hanya boleh memakai MySQL development tg_test tanpa DB_URL.');
        }

        $actual = DB::selectOne('SELECT DATABASE() AS name, VERSION() AS version');
        if ($actual->name !== 'tg_test' || ! str_starts_with($actual->version, '8.4.')) {
            throw new LogicException('Database aktual pengujian harus MySQL 8.4 tg_test. Tidak ada migration dijalankan.');
        }

        return $app;
    }
}
