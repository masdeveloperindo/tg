<?php

/** Synthetic browser fixtures. This script may erase only the e2e tg_test database. */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Support\DomainRecords;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('e2e')
    || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'tg_test'
    || config('database.connections.mysql.url')) {
    fwrite(STDERR, "Browser fixtures require APP_ENV=e2e and MySQL tg_test without DB_URL. No tables changed.\n");
    exit(1);
}

// Validate the actual selected server and schema before invoking destructive migrations.
$database = DB::selectOne('SELECT DATABASE() AS name, VERSION() AS version');
if ($database->name !== 'tg_test' || ! str_starts_with($database->version, '8.4.')) {
    fwrite(STDERR, "Browser fixtures require the actual MySQL 8.4 tg_test database. No tables changed.\n");
    exit(1);
}

if (Artisan::call('migrate:fresh', ['--force' => true]) !== 0) {
    fwrite(STDERR, "Browser fixture migrations failed. No fixture credentials are printed.\n");
    exit(1);
}

DB::transaction(function (): void {
    $records = new DomainRecords;
    $records->user([
        'name' => 'Layout Client',
        'email' => 'layout-client@example.invalid',
        'contact_channel' => 'telegram',
        'contact_value_enc' => Crypt::encryptString('layout_client_fixture'),
    ]);

    $records->user([
        'name' => 'LayoutLong'.str_repeat('NameWithoutSpaces', 6),
        'email' => 'layout-long-'.str_repeat('x', 52).'@'.str_repeat('d', 63).'.example.invalid',
        'contact_channel' => 'telegram',
        'contact_value_enc' => Crypt::encryptString('layout_'.str_repeat('x', 25)),
    ]);

    $records->user([
        'name' => 'Layout Admin',
        'email' => 'layout-admin@example.invalid',
        'role' => 'admin',
        'contact_channel' => 'telegram',
        'contact_value_enc' => Crypt::encryptString('layout_admin_fixture'),
        // Public RFC 6238 test-vector material; never use this fixture in a real account.
        'totp_secret_enc' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'),
        'totp_enabled_at' => now(),
    ]);
});

fwrite(STDOUT, "Browser fixtures created: two synthetic clients and one TOTP-enabled synthetic admin in e2e tg_test.\n");
