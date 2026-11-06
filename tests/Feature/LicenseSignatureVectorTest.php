<?php

namespace Tests\Feature;

use Tests\TestCase;

class LicenseSignatureVectorTest extends TestCase
{
    public function test_public_interoperability_fixtures_reject_tampering_nonce_and_unknown_kid(): void
    {
        $files = glob(base_path('tests/fixtures/license_signature_*.json'));
        $this->assertCount(4, $files);
        foreach ($files as $file) {
            $vector = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $envelope = $vector['envelope'];
            $decode = fn ($value) => base64_decode(strtr($value, '-_', '+/'));
            $payloadBytes = $decode($envelope['payload_b64']);
            $this->assertSame($vector['payload_hex'], bin2hex($payloadBytes));
            $key = $vector['pinned_keys'][$envelope['kid']] ?? null;
            $accepted = false;
            if ($key && sodium_crypto_sign_verify_detached($decode($envelope['signature']), $payloadBytes, $decode($key))) {
                $payload = json_decode($payloadBytes, true, 512, JSON_THROW_ON_ERROR);
                $accepted = $payload['kid'] === $envelope['kid'] && $payload['status'] === 'valid';
                foreach ($vector['expected_request'] as $name => $value) {
                    $accepted = $accepted && $payload[$name] === $value;
                }
            }
            $this->assertSame($vector['accept'], $accepted, basename($file));
        }
    }
}
