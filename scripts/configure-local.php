<?php

// Run only for local development; never overwrite an existing environment.
if (file_exists(__DIR__.'/../.env')) {
    fwrite(STDOUT, ".env sudah ada; tidak diubah.\n");
    exit(0);
}

$contents = file_get_contents(__DIR__.'/../.env.example');
$values = [
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'DB_PASSWORD' => bin2hex(random_bytes(24)),
    'DB_ROOT_PASSWORD' => bin2hex(random_bytes(24)),
];
foreach ($values as $key => $value) {
    $contents = preg_replace('/^'.preg_quote($key, '/').'=$/m', $key.'='.$value, $contents);
}
$oldMask = umask(0077);
$handle = fopen(__DIR__.'/../.env', 'x');
if ($handle === false) {
    throw new RuntimeException('Tidak dapat membuat .env lokal.');
}
fwrite($handle, $contents);
fclose($handle);
umask($oldMask);
fwrite(STDOUT, ".env lokal dibuat dengan kunci dan kredensial acak; nilainya tidak dicetak.\n");
