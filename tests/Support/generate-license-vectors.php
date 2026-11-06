<?php

// Reproducible interoperability fixtures. RFC8032 public test seed, NEVER a deployment key.
use App\Actions\LicenseApi\Envelope;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::selectOne('SELECT DATABASE() AS name')->name !== 'tg_test') {
    exit(2);
}
$path = tempnam(sys_get_temp_dir(), 'tg-public-test-vector-');
chmod($path, 0600);
try {
    $pair = sodium_crypto_sign_seed_keypair(hex2bin('9d61b19deffd5a60ba844af492ec2cc44449c5697b326919703bac031cae7f60'));
    file_put_contents($path, base64_encode(sodium_crypto_sign_secretkey($pair)));
    config(['tradergaruda.license_private_key_path' => $path, 'tradergaruda.license_signing_kid' => 'tg-fixture-rfc8032-only']);
    $identity = ['protocol' => 1, 'product_id' => 10, 'account_number' => '12345678', 'server' => 'Synthetic-MT5-Demo', 'account_type' => 'demo', 'version' => '1.0.0', 'request_nonce' => Envelope::encode(str_repeat("\x01", 16))];
    $signed = app(Envelope::class)->sign(['status' => 'valid', ...$identity, 'expires_at' => '2026-12-31T17:00:00Z', 'latest_version' => '1.1.0', 'min_supported_version' => '1.0.0', 'api_base_url' => 'https://example.invalid', 'server_timestamp' => '2026-11-06T00:00:00Z', 'request_id' => '00000000-0000-4000-8000-000000000001']);
    $bytes = base64_decode(strtr($signed['payload_b64'], '-_', '+/'));
    foreach (['valid', 'tampered', 'wrong_nonce', 'unknown_kid'] as $name) {
        $envelope = $signed;
        $expected = $identity;
        if ($name === 'tampered') {
            $envelope['payload_b64'] = Envelope::encode(str_replace('"status":"valid"', '"status":"xalid"', $bytes));
        }
        if ($name === 'wrong_nonce') {
            $expected['request_nonce'] = Envelope::encode(str_repeat("\x02", 16));
        }
        if ($name === 'unknown_kid') {
            $envelope['kid'] = 'unknown-fixture-key';
        }
        $vector = ['fixture_only' => true, 'pinned_keys' => ['tg-fixture-rfc8032-only' => Envelope::encode(sodium_crypto_sign_publickey($pair))], 'expected_request' => $expected, 'envelope' => $envelope, 'payload_hex' => bin2hex(base64_decode(strtr($envelope['payload_b64'], '-_', '+/'))), 'accept' => $name === 'valid'];
        file_put_contents(base_path('tests/fixtures/license_signature_'.$name.'.json'), json_encode($vector, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    }
    echo "Four public synthetic Ed25519 vectors generated. No deployment key written.\n";
} finally {
    unlink($path);
}
