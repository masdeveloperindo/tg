<?php

namespace App\Security;

use InvalidArgumentException;

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        $buffer = 0;
        $bits = 0;
        $encoded = '';

        foreach (unpack('C*', random_bytes(20)) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 31];
            }

            $buffer &= (1 << $bits) - 1;
        }

        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $encoded;
    }

    public function code(string $secret, ?int $timestamp = null, int $digits = 6): string
    {
        $timestamp ??= time();
        if ($timestamp < 0 || $digits < 6 || $digits > 8) {
            throw new InvalidArgumentException('Parameter TOTP tidak valid.');
        }

        return $this->codeAtCounter($secret, intdiv($timestamp, 30), $digits);
    }

    public function matchingCounter(string $secret, string $code, ?int $timestamp = null): ?int
    {
        if (! preg_match('/\A[0-9]{6}\z/', $code)) {
            return null;
        }

        $timestamp ??= time();
        if ($timestamp < 0) {
            return null;
        }

        $current = intdiv($timestamp, 30);
        $matched = null;
        foreach ([-1, 0, 1] as $offset) {
            $counter = $current + $offset;
            if ($counter >= 0 && hash_equals($this->codeAtCounter($secret, $counter, 6), $code)) {
                $matched = $counter;
            }
        }

        return $matched;
    }

    private function codeAtCounter(string $secret, int $counter, int $digits): string
    {
        $counterBytes = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
        $digest = hash_hmac('sha1', $counterBytes, $this->decodeSecret($secret), true);
        $offset = ord($digest[19]) & 15;
        $binary = unpack('N', substr($digest, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    private function decodeSecret(string $secret): string
    {
        $secret = rtrim(strtoupper($secret), '=');
        if ($secret === '' || ! preg_match('/\A[A-Z2-7]+\z/', $secret)) {
            throw new InvalidArgumentException('Secret TOTP tidak valid.');
        }

        $buffer = 0;
        $bits = 0;
        $decoded = '';
        foreach (str_split($secret) as $character) {
            $buffer = ($buffer << 5) | strpos(self::ALPHABET, $character);
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 255);
            }
            $buffer &= (1 << $bits) - 1;
        }

        if ($decoded === '' || ($bits > 0 && $buffer !== 0)) {
            throw new InvalidArgumentException('Secret TOTP tidak valid.');
        }

        return $decoded;
    }
}
