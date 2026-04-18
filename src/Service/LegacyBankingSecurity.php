<?php

namespace App\Service;

final class LegacyBankingSecurity
{
    private const AES_KEY = 'NexoraBank@SecureKey#2025!!12345';
    private const PASSWORD_ITERATIONS = 210000;
    private const PASSWORD_SALT_BYTES = 16;
    private const PASSWORD_HASH_BYTES = 32;

    public function encryptAmount(null|float|int|string $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            (string) $amount,
            'AES-256-CBC',
            self::AES_KEY,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Failed to encrypt amount.');
        }

        return base64_encode($iv).':'.base64_encode($ciphertext);
    }

    public function decryptAmount(?string $encryptedData): ?float
    {
        if ($encryptedData === null || trim($encryptedData) === '') {
            return null;
        }

        $encryptedData = trim($encryptedData);

        if (!str_contains($encryptedData, ':')) {
            return is_numeric($encryptedData) ? (float) $encryptedData : null;
        }

        [$ivPart, $cipherPart] = explode(':', $encryptedData, 2);
        $iv = base64_decode($ivPart, true);
        $ciphertext = base64_decode($cipherPart, true);

        if ($iv === false || $ciphertext === false) {
            return null;
        }

        $decrypted = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            self::AES_KEY,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false || !is_numeric($decrypted)) {
            return null;
        }

        return (float) $decrypted;
    }

    public function hashPassword(string $plainPassword): string
    {
        $password = trim($plainPassword);
        if ($password === '') {
            throw new \InvalidArgumentException('Password cannot be empty.');
        }

        $salt = random_bytes(self::PASSWORD_SALT_BYTES);
        $hash = hash_pbkdf2(
            'sha256',
            $password,
            $salt,
            self::PASSWORD_ITERATIONS,
            self::PASSWORD_HASH_BYTES,
            true
        );

        return sprintf(
            'PBKDF2$%d$%s$%s',
            self::PASSWORD_ITERATIONS,
            base64_encode($salt),
            base64_encode($hash)
        );
    }

    public function verifyPassword(string $plainPassword, ?string $storedHash): bool
    {
        $password = trim($plainPassword);
        if ($password === '' || $storedHash === null || trim($storedHash) === '') {
            return false;
        }

        $parts = explode('$', $storedHash);
        if (count($parts) !== 4 || $parts[0] !== 'PBKDF2') {
            return false;
        }

        $iterations = (int) $parts[1];
        $salt = base64_decode($parts[2], true);
        $expectedHash = base64_decode($parts[3], true);

        if ($iterations <= 0 || $salt === false || $expectedHash === false) {
            return false;
        }

        $computed = hash_pbkdf2(
            'sha256',
            $password,
            $salt,
            $iterations,
            strlen($expectedHash),
            true
        );

        return hash_equals($expectedHash, $computed);
    }
}
