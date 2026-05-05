<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

final class QrSessionService
{
    private ?bool $schemaReady = null;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function ensureSchemaReady(): bool
    {
        if ($this->schemaReady !== null) {
            return $this->schemaReady;
        }

        try {
            $this->connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS qr_trust_sessions (
                    idQrTrustSession INT NOT NULL AUTO_INCREMENT,
                    session_token CHAR(64) NOT NULL,
                    user_id INT NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT \'PENDING\',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    consumed_at DATETIME NULL DEFAULT NULL,
                    trusted_device_id INT NULL DEFAULT NULL,
                    PRIMARY KEY (idQrTrustSession),
                    UNIQUE KEY uniq_qr_trust_token (session_token),
                    INDEX idx_qr_trust_lookup (session_token, status, expires_at),
                    INDEX idx_qr_trust_user (user_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS qr_login_sessions (
                    idQrLoginSession INT NOT NULL AUTO_INCREMENT,
                    session_token CHAR(64) NOT NULL,
                    browser_session_id VARCHAR(190) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT \'PENDING\',
                    requested_ip VARCHAR(64) NULL DEFAULT NULL,
                    requested_user_agent VARCHAR(255) NULL DEFAULT NULL,
                    approved_user_id INT NULL DEFAULT NULL,
                    approved_device_id INT NULL DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    approved_at DATETIME NULL DEFAULT NULL,
                    consumed_at DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (idQrLoginSession),
                    UNIQUE KEY uniq_qr_login_token (session_token),
                    INDEX idx_qr_login_lookup (session_token, status, expires_at),
                    INDEX idx_qr_login_browser (browser_session_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->schemaReady = true;
        } catch (\Throwable) {
            $this->schemaReady = false;
        }

        return $this->schemaReady;
    }

    /**
     * @return array{token:string, expires_at:string}
     */
    public function createTrustSession(int $userId, int $ttlSeconds = 300): array
    {
        $this->assertSchemaReady();
        if ($userId <= 0) {
            throw new \InvalidArgumentException('A valid user is required.');
        }

        $ttl = max(60, min($ttlSeconds, 900));
        $token = $this->generateToken();
        $expiresAt = (new \DateTimeImmutable('+'.$ttl.' seconds'))->format('Y-m-d H:i:s');

        $this->connection->insert('qr_trust_sessions', [
            'session_token' => $token,
            'user_id' => $userId,
            'status' => 'PENDING',
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function consumeTrustSession(string $token, int $userId, int $trustedDeviceId): void
    {
        $this->assertSchemaReady();
        $session = $this->findTrustSession($token);
        if ($session === null) {
            throw new \RuntimeException('Trust QR session not found.');
        }

        if ((int) ($session['user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('Trust QR does not belong to this account.');
        }

        $status = strtoupper((string) ($session['status'] ?? ''));
        if ($status !== 'PENDING') {
            throw new \RuntimeException('Trust QR was already consumed or expired.');
        }

        if ($this->isExpired((string) ($session['expires_at'] ?? ''))) {
            $this->expireTrustSession((int) ($session['idQrTrustSession'] ?? 0));
            throw new \RuntimeException('Trust QR expired. Generate a new code.');
        }

        $this->connection->executeStatement(
            'UPDATE qr_trust_sessions
             SET status = ?, consumed_at = NOW(), trusted_device_id = ?
             WHERE idQrTrustSession = ?',
            ['CONSUMED', $trustedDeviceId > 0 ? $trustedDeviceId : null, (int) ($session['idQrTrustSession'] ?? 0)]
        );
    }

    /**
     * @return array{token:string, expires_at:string}
     */
    public function createWebLoginSession(
        string $browserSessionId,
        string $requestedIp = '',
        string $requestedUserAgent = '',
        int $ttlSeconds = 180
    ): array {
        $this->assertSchemaReady();
        $browserSessionId = trim($browserSessionId);
        if ($browserSessionId === '') {
            throw new \InvalidArgumentException('Browser session id is required.');
        }

        $ttl = max(60, min($ttlSeconds, 420));
        $token = $this->generateToken();
        $expiresAt = (new \DateTimeImmutable('+'.$ttl.' seconds'))->format('Y-m-d H:i:s');

        $this->connection->insert('qr_login_sessions', [
            'session_token' => $token,
            'browser_session_id' => mb_substr($browserSessionId, 0, 190),
            'status' => 'PENDING',
            'requested_ip' => $this->normalize($requestedIp, 64),
            'requested_user_agent' => $this->normalize($requestedUserAgent, 255),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function approveWebLoginSession(string $token, int $userId, int $trustedDeviceId): void
    {
        $this->assertSchemaReady();
        if ($userId <= 0) {
            throw new \InvalidArgumentException('A valid user is required for QR approval.');
        }

        $session = $this->findWebLoginSession($token);
        if ($session === null) {
            throw new \RuntimeException('Web login QR session not found.');
        }

        $status = strtoupper((string) ($session['status'] ?? ''));
        if (!in_array($status, ['PENDING', 'APPROVED'], true)) {
            throw new \RuntimeException('Web login QR is not pending anymore.');
        }

        if ($this->isExpired((string) ($session['expires_at'] ?? ''))) {
            $this->expireWebLoginSession((int) ($session['idQrLoginSession'] ?? 0));
            throw new \RuntimeException('Web login QR expired. Refresh login page and retry.');
        }

        $this->connection->executeStatement(
            'UPDATE qr_login_sessions
             SET status = ?, approved_user_id = ?, approved_device_id = ?, approved_at = NOW()
             WHERE idQrLoginSession = ?',
            ['APPROVED', $userId, $trustedDeviceId > 0 ? $trustedDeviceId : null, (int) ($session['idQrLoginSession'] ?? 0)]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function readWebLoginSessionForBrowser(string $token, string $browserSessionId): ?array
    {
        $this->assertSchemaReady();
        $trimmedToken = trim($token);
        if ($trimmedToken === '') {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT *
             FROM qr_login_sessions
             WHERE session_token = ?
               AND browser_session_id = ?
             LIMIT 1',
            [$trimmedToken, trim($browserSessionId)]
        );

        return $row ?: null;
    }

    public function consumeApprovedWebLoginSession(string $token, string $browserSessionId): ?int
    {
        $session = $this->readWebLoginSessionForBrowser($token, $browserSessionId);
        if ($session === null) {
            return null;
        }

        $status = strtoupper((string) ($session['status'] ?? ''));
        if ($status !== 'APPROVED') {
            return null;
        }

        if ($this->isExpired((string) ($session['expires_at'] ?? ''))) {
            $this->expireWebLoginSession((int) ($session['idQrLoginSession'] ?? 0));

            return null;
        }

        $id = (int) ($session['idQrLoginSession'] ?? 0);
        if ($id > 0) {
            $this->connection->executeStatement(
                'UPDATE qr_login_sessions
                 SET status = ?, consumed_at = NOW()
                 WHERE idQrLoginSession = ?',
                ['CONSUMED', $id]
            );
        }

        $userId = (int) ($session['approved_user_id'] ?? 0);

        return $userId > 0 ? $userId : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findTrustSession(string $token): ?array
    {
        $this->assertSchemaReady();
        $trimmed = trim($token);
        if ($trimmed === '') {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM qr_trust_sessions WHERE session_token = ? LIMIT 1',
            [$trimmed]
        );

        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findWebLoginSession(string $token): ?array
    {
        $this->assertSchemaReady();
        $trimmed = trim($token);
        if ($trimmed === '') {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM qr_login_sessions WHERE session_token = ? LIMIT 1',
            [$trimmed]
        );

        return $row ?: null;
    }

    public function buildQrSvg(string $payload, int $size = 260): string
    {
        $builder = new Builder(
            writer: new SvgWriter(),
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: max(180, min($size, 720)),
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return $builder->build()->getString();
    }

    public function buildQrPng(string $payload, int $size = 260): string
    {
        $builder = new Builder(
            writer: new PngWriter(),
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: max(180, min($size, 720)),
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return $builder->build()->getString();
    }

    private function expireTrustSession(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE qr_trust_sessions
             SET status = CASE WHEN status = ? THEN ? ELSE status END
             WHERE idQrTrustSession = ?',
            ['PENDING', 'EXPIRED', $id]
        );
    }

    private function expireWebLoginSession(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE qr_login_sessions
             SET status = CASE WHEN status = ? THEN ? ELSE status END
             WHERE idQrLoginSession = ?',
            ['PENDING', 'EXPIRED', $id]
        );
    }

    private function normalize(string $value, int $maxLength): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, $maxLength);
    }

    private function isExpired(string $expiresAt): bool
    {
        if (trim($expiresAt) === '') {
            return true;
        }

        try {
            $expiry = new \DateTimeImmutable($expiresAt);
        } catch (\Throwable) {
            return true;
        }

        return $expiry < new \DateTimeImmutable('now');
    }

    private function assertSchemaReady(): void
    {
        if (!$this->ensureSchemaReady()) {
            throw new \RuntimeException('QR auth schema is not ready.');
        }
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
