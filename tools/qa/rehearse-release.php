<?php

// No host operations: disposable source tree and an independently verified tg_test schema only.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local', 'testing', 'e2e')) {
    fwrite(STDERR, "Release rehearsal is restricted to local/testing/e2e.\n");
    exit(2);
}
$archive = realpath($argv[1] ?? '');
$report = $argv[2] ?? null;
if (! $archive || ! $report || ! is_file($archive)) {
    exit(2);
}
$db = config('database.connections.mysql');
if (config('database.default') !== 'mysql' || ! in_array($db['host'], ['db', 'localhost', '127.0.0.1'], true) || ! empty($db['url'])) {
    fwrite(STDERR, "Local MySQL configuration required; no external database accepted.\n");
    exit(2);
}
$folder = storage_path('framework/testing/release-check-'.bin2hex(random_bytes(8)));
$files = new Filesystem;
$files->makeDirectory($folder, 0755, true);
$env = ['APP_ENV' => 'release-check', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_URL' => 'https://release-check.example.invalid', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => 'tg_test', 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'], 'DB_URL' => '', 'DB_SOCKET' => '', 'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'database', 'SESSION_ENCRYPT' => 'true', 'SESSION_SECURE_COOKIE' => 'true', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'stack', 'LOG_STACK' => 'single', 'LOG_LEVEL' => 'warning', 'TG_PRIVATE_STORAGE_PATH' => $folder.'/storage/app/private', 'TG_HEALTH_TOKEN' => bin2hex(random_bytes(24)), 'TG_LICENSE_ED25519_PRIVATE_KEY_PATH' => '', 'TG_LICENSE_ED25519_KID' => '', 'TG_TELEGRAM_USERNAME' => '', 'TG_BROKER_REFERRAL_HOSTS' => '', 'COMPOSER_DISABLE_NETWORK' => '1'];
$steps = [];
$execute = function (array $command, string $name, ?string $cwd = null) use ($env, $folder, &$steps): string {
    $process = new Process($command, $cwd ?? $folder, $env);
    $process->setTimeout(120);
    $process->run();
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Candidate step failed: '.$name.'. Inspect only isolated runtime; no secret configuration is printed.');
    }
    $steps[] = $name;

    return $process->getOutput();
};
try {
    $package = new PharData($archive);
    $manifest = json_decode($package['.release-manifest.json']->getContent(), true, 512, JSON_THROW_ON_ERROR);
    $inventory = $manifest['files'];
    $seen = [];
    foreach (new RecursiveIteratorIterator($package) as $entry) {
        $name = substr($entry->getPathname(), strlen('phar://'.$archive.'/'));
        if ($entry->isDir()) {
            continue;
        }
        if ($entry->isLink() || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
            throw new RuntimeException('Unsafe candidate archive entry.');
        }
        if ($name === '.release-manifest.json') {
            continue;
        }
        $record = $inventory[$name] ?? null;
        if (! $record || $entry->getSize() !== $record['bytes'] || hash('sha256', $entry->getContent()) !== $record['sha256']) {
            throw new RuntimeException('Candidate inventory/checksum mismatch.');
        }
        $seen[] = $name;
    }
    if (count($seen) !== count($inventory)) {
        throw new RuntimeException('Candidate inventory incomplete.');
    }
    $steps[] = 'archive manifest/checksums verified';
    $execute(['tar', '-xzf', $archive, '--no-same-owner', '-C', $folder], 'candidate extracted', base_path());
    $files->copyDirectory(base_path('vendor'), $folder.'/vendor');
    $execute(['composer', 'install', '--no-dev', '--no-scripts', '--no-interaction', '--no-progress', '--prefer-dist'], 'production dependency set installed without network');
    $execute([PHP_BINARY, $folder.'/artisan', 'package:discover', '--ansi'], 'package discovery');
    $worker = base_path('tools/qa/release-smoke-worker.php');
    $execute([PHP_BINARY, $worker, $folder, 'guard'], 'actual MySQL8.4 tg_test guard');
    $execute([PHP_BINARY, $folder.'/artisan', 'migrate:fresh', '--force'], 'fresh migrations on dedicated tg_test');
    $execute([PHP_BINARY, $folder.'/artisan', 'migrate:rollback', '--step=1', '--force'], 'latest additive migration rollback on tg_test');
    $execute([PHP_BINARY, $folder.'/artisan', 'migrate', '--force'], 'migration reapplied');
    foreach (['config:cache', 'route:cache', 'view:cache', 'event:cache'] as $command) {
        $execute([PHP_BINARY, $folder.'/artisan', $command], $command);
    }
    foreach (['tg:expire-orders', 'tg:detect-license-anomalies', 'tg:reconcile-rebates'] as $command) {
        $execute([PHP_BINARY, $folder.'/artisan', $command], $command.' on empty candidate');
    }
    $result = json_decode($execute([PHP_BINARY, $worker, $folder], 'cached production-style HTTP/health smoke'), true, 512, JSON_THROW_ON_ERROR);
    $execute([PHP_BINARY, $folder.'/artisan', 'optimize:clear'], 'cache clear rehearsal');
    $result['steps'] = $steps;
    $result['archive_sha256'] = hash_file('sha256', $archive);
    $result['dependencies'] = 'composer --no-dev, copied cache only, no network';
    $result['cleanup'] = 'candidate including generated keys/config cache removed';
    $files->ensureDirectoryExists(dirname($report));
    file_put_contents($report, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
    echo "PASS: isolated candidate migrations, caches, routes and private health. No deployment performed.\n";
} finally {
    $files->deleteDirectory($folder);
}
