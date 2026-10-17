<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use Illuminate\Support\Str;

/**
 * Lightweight, zero-dependency RFC 6238 TOTP service.
 */
class TotpService
{
    private const string BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random Base32 TOTP secret.
     */
    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        $alphabetLength = strlen(self::BASE32_ALPHABET);

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $secret;
    }

    /**
     * Verify a 6-digit TOTP code against a Base32 secret with time drift window.
     */
    public function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $code = trim($code);

        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return false;
        }

        $timeSlice = (int) floor(($timestamp ?? time()) / 30);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->calculateCode($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate a 6-digit TOTP code for a given time slice.
     */
    public function calculateCode(string $secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($secret);

        // Pack time into binary 64-bit big-endian integer
        $timeBytes = pack('N*', 0).pack('N*', $timeSlice);

        // Compute HMAC-SHA1
        $hash = hash_hmac('sha1', $timeBytes, $secretKey, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord(substr($hash, -1)) & 0x0F;
        $hashPart = substr($hash, $offset, 4);

        $value = unpack('N', $hashPart)[1] & 0x7FFFFFFF;
        $code = (string) ($value % 1000000);

        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate standard otpauth:// URL for authenticator apps (Google Authenticator, Authy).
     */
    public function getOtpAuthUri(string $company, string $account, string $secret): string
    {
        $encodedCompany = rawurlencode($company);
        $encodedAccount = rawurlencode($account);

        return "otpauth://totp/{$encodedCompany}:{$encodedAccount}?secret={$secret}&issuer={$encodedCompany}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Generate emergency recovery codes.
     *
     * @return array<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random(10));
        }

        return $codes;
    }

    /**
     * Base32 decode string.
     */
    private function base32Decode(string $b32): string
    {
        $b32 = strtoupper($b32);
        $buffer = 0;
        $bufferBits = 0;
        $binary = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $char = $b32[$i];
            $val = strpos(self::BASE32_ALPHABET, $char);

            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bufferBits += 5;

            if ($bufferBits >= 8) {
                $bufferBits -= 8;
                $binary .= chr(($buffer >> $bufferBits) & 0xFF);
            }
        }

        return $binary;
    }
}
