<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_framework_tables_remain_available_on_mysql_84(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertStringStartsWith('8.4.', DB::selectOne('SELECT VERSION() AS version')->version);

        $tables = Schema::getTableListing(
            schema: DB::connection()->getDatabaseName(),
            schemaQualified: false,
        );
        sort($tables);

        foreach (['cache', 'cache_locks', 'migrations', 'sessions'] as $table) {
            $this->assertContains($table, $tables);
        }
    }

    public function test_database_cache_persists_across_store_instances(): void
    {
        $this->assertSame('database', config('cache.default'));

        $key = 'foundation:'.Str::uuid();
        $value = ['state' => 'not-open', 'revision' => 1];
        $store = Cache::store('database');

        try {
            $this->assertTrue($store->put($key, $value, 60));
            $this->assertDatabaseHas('cache', [
                'key' => $store->getStore()->getPrefix().$key,
            ]);

            Cache::forgetDriver('database');

            $this->assertSame($value, Cache::store('database')->get($key));
        } finally {
            Cache::store('database')->forget($key);
        }
    }

    public function test_database_lock_rejects_a_second_owner_until_released(): void
    {
        $key = 'foundation-lock:'.Str::uuid();
        $first = Cache::store('database')->lock($key, 30);
        $second = Cache::store('database')->lock($key, 30);

        try {
            $this->assertTrue($first->get());
            $this->assertFalse($second->get());
            $this->assertTrue($first->release());
            $this->assertTrue($second->get());
        } finally {
            $first->release();
            $second->release();
        }
    }

    public function test_home_request_persists_a_guest_database_session(): void
    {
        $this->assertSame('database', config('session.driver'));
        $this->assertDatabaseCount('sessions', 0);

        $this->get('/')->assertOk();

        $this->assertDatabaseCount('sessions', 1);
        $session = DB::table('sessions')->first();

        $this->assertNull($session->user_id);
        $this->assertNotEmpty($session->payload);
        $this->assertGreaterThanOrEqual(now()->subMinute()->timestamp, $session->last_activity);

        $this->withCookie(config('session.cookie'), $session->id)
            ->get('/')
            ->assertOk();

        $this->assertDatabaseCount('sessions', 1);
        $this->assertDatabaseHas('sessions', ['id' => $session->id, 'user_id' => null]);
    }
}
