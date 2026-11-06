<?php

namespace App\Actions\LicenseApi;

final class Envelope
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public function sign(array $payload): array
    {
        $path = realpath((string) config('tradergaruda.license_private_key_path'));
        $public = realpath(public_path());
        abort_unless($path && is_file($path) && is_readable($path) && $path !== $public && ! str_starts_with($path, $public.DIRECTORY_SEPARATOR) && (fileperms($path) & 0077) === 0, 503);
        $encoded = trim((string) file_get_contents($path));
        $secret = base64_decode($encoded, true);
        abort_unless(is_string($secret) && strlen($secret) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, 503);
        // Reject malformed secret/public concatenations as well as invalid lengths.
        $derived = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(substr($secret, 0, 32)));
        abort_unless(hash_equals($derived, $secret), 503);
        $kid = config('tradergaruda.license_signing_kid');
        abort_unless(is_string($kid) && preg_match('/\A[a-zA-Z0-9_-]{1,80}\z/', $kid), 503);
        $payload = ['protocol' => 1, 'kid' => $kid, ...$payload];
        $bytes = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        try {
            return ['payload_b64' => self::encode($bytes), 'signature' => self::encode(sodium_crypto_sign_detached($bytes, $secret)), 'kid' => $kid];
        } finally {
            sodium_memzero($secret);
            sodium_memzero($derived);
        }
    }
}
