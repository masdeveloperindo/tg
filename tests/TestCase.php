<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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

        return $app;
    }
}
