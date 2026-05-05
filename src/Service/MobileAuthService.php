<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

final class MobileAuthService
{
    private const TOKEN_TTL_SECONDS = 2592000; // 30 days
    private static bool $schemaReadyGlobal = false;

    private ?bool $schemaReady = null;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function ensureSchemaReady(): bool
    {
        if (self::$schemaReadyGlobal) {
            $this->schemaReady = true;
            return true;
        }

        if ($this->schemaReady !== null) {
            return $this->schemaReady;
        }

        try {
            $this->connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS mobile_access_tokens (
                    idMobileAccessToken INT NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    token_prefix VARCHAR(16) NOT NULL,
                    device_id VARCHAR(140) NOT NULL,
                    device_name VARCHAR(140) NOT NULL DEFAULT \'Mobile device\',
                    platform VARCHAR(40) NULL,
                    app_version VARCHAR(40) NULL,
                    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    last_used_at DATETIME NULL DEFAULT NULL,
                    revoked_at DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (idMobileAccessToken),
                    UNIQUE KEY uniq_mobile_token_hash (token_hash),
                    INDEX idx_mobile_token_user (user_id),
                    INDEX idx_mobile_token_device (user_id, device_id, revoked_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS trusted_mobile_devices (
                    idTrustedDevice INT NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    device_id VARCHAR(140) NOT NULL,
                    device_name VARCHAR(140) NOT NULL DEFAULT \'Mobile device\',
                    platform VARCHAR(40) NULL,
                    app_version VARCHAR(40) NULL,
                    trusted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_seen_at DATETIME NULL DEFAULT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    PRIMARY KEY (idTrustedDevice),
                    UNIQUE KEY uniq_user_device (user_id, device_id),
                    INDEX idx_trusted_user (user_id, is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->schemaReady = true;
            self::$schemaReadyGlobal = true;
        } catch (\Throwable) {
            $this->schemaReady = false;
        }

        return $this->schemaReady;
    }

    /**
     * @return array{token:string, expires_at:string}
     */
    public function issueAccessToken(
        int $userId,
        string $deviceId,
        string $deviceName,
        string $platform = '',
        string $appVersion = ''
    ): array {
        $this->assertSchemaReady();

        $normalizedDeviceId = $this->normalizeDeviceId($deviceId);
        $normalizedDeviceName = $this->normalizeDeviceName($deviceName);
        $normalizedPlatform = $this->normalizeMeta($platform, 40);
        $normalizedAppVersion = $this->normalizeMeta($appVersion, 40);

        if ($normalizedDeviceId === '') {
            throw new \InvalidArgumentException('Device id is required.');
        }

        if ($userId <= 0) {
            throw new \InvalidArgumentException('A valid user is required.');
        }

        $token = $this->generateToken();
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable('+'.self::TOKEN_TTL_SECONDS.' seconds'))->format('Y-m-d H:i:s');

        $this->connection->insert('mobile_access_tokens', [
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'token_prefix' => substr($token, 0, 12),
            'device_id' => $normalizedDeviceId,
            'device_name' => $normalizedDeviceName,
            'platform' => $normalizedPlatform !== '' ? $normalizedPlatform : null,
            'app_version' => $normalizedAppVersion !== '' ? $normalizedAppVersion : null,
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array{user:array<string,mixed>, token:array<string,mixed>, trusted_device:bool}|null
     */
    public function authenticateRequest(Request $request): ?array
    {
        $this->assertSchemaReady();
        $rawToken = $this->extractBearerToken($request);
        if ($rawToken === null) {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);
        $row = $this->connection->fetchAssociative(
            'SELECT t.*, u.*
             FROM mobile_access_tokens t
             INNER JOIN users u ON u.idUser = t.user_id
             WHERE t.token_hash = ?
               AND t.revoked_at IS NULL
               AND t.expires_at >= NOW()
             ORDER BY t.idMobileAccessToken DESC
             LIMIT 1',
            [$tokenHash]
        );

        if (!$row) {
            return null;
        }

        $userId = (int) ($row['idUser'] ?? 0);
        $deviceId = (string) ($row['device_id'] ?? '');
        $tokenId = (int) ($row['idMobileAccessToken'] ?? 0);

        if ($tokenId > 0) {
            $this->connection->executeStatement(
                'UPDATE mobile_access_tokens SET last_used_at = NOW() WHERE idMobileAccessToken = ?',
                [$tokenId]
            );
        }

        $trusted = $this->isDeviceTrusted($userId, $deviceId);
        if ($trusted) {
            $this->connection->executeStatement(
                'UPDATE trusted_mobile_devices
                 SET last_seen_at = NOW()
                 WHERE user_id = ? AND device_id = ? AND is_active = 1',
                [$userId, $deviceId]
            );
        }

        return [
            'user' => $this->extractUserFromJoinedRow($row),
            'token' => [
                'idMobileAccessToken' => $tokenId,
                'device_id' => $deviceId,
                'device_name' => (string) ($row['device_name'] ?? ''),
                'platform' => (string) ($row['platform'] ?? ''),
                'app_version' => (string) ($row['app_version'] ?? ''),
                'expires_at' => (string) ($row['expires_at'] ?? ''),
                'issued_at' => (string) ($row['issued_at'] ?? ''),
            ],
            'trusted_device' => $trusted,
        ];
    }

    public function revokeAccessToken(string $token): void
    {
        $this->assertSchemaReady();
        $trimmed = trim($token);
        if ($trimmed === '') {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE mobile_access_tokens
             SET revoked_at = NOW()
             WHERE token_hash = ? AND revoked_at IS NULL',
            [hash('sha256', $trimmed)]
        );
    }

    public function revokeAccessTokenById(int $tokenId): void
    {
        $this->assertSchemaReady();
        if ($tokenId <= 0) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE mobile_access_tokens
             SET revoked_at = NOW()
             WHERE idMobileAccessToken = ? AND revoked_at IS NULL',
            [$tokenId]
        );
    }

    public function trustDevice(
        int $userId,
        string $deviceId,
        string $deviceName,
        string $platform = '',
        string $appVersion = ''
    ): int {
        $this->assertSchemaReady();
        $normalizedDeviceId = $this->normalizeDeviceId($deviceId);
        $normalizedDeviceName = $this->normalizeDeviceName($deviceName);
        $normalizedPlatform = $this->normalizeMeta($platform, 40);
        $normalizedAppVersion = $this->normalizeMeta($appVersion, 40);

        if ($userId <= 0 || $normalizedDeviceId === '') {
            throw new \InvalidArgumentException('Invalid user/device payload.');
        }

        $existingId = (int) $this->connection->fetchOne(
            'SELECT idTrustedDevice
             FROM trusted_mobile_devices
             WHERE user_id = ? AND device_id = ?
             LIMIT 1',
            [$userId, $normalizedDeviceId]
        );

        if ($existingId > 0) {
            $this->connection->executeStatement(
                'UPDATE trusted_mobile_devices
                 SET device_name = ?,
                     platform = ?,
                     app_version = ?,
                     is_active = 1,
                     trusted_at = NOW(),
                     last_seen_at = NOW()
                 WHERE idTrustedDevice = ?',
                [
                    $normalizedDeviceName,
                    $normalizedPlatform !== '' ? $normalizedPlatform : null,
                    $normalizedAppVersion !== '' ? $normalizedAppVersion : null,
                    $existingId,
                ]
            );

            return $existingId;
        }

        $this->connection->insert('trusted_mobile_devices', [
            'user_id' => $userId,
            'device_id' => $normalizedDeviceId,
            'device_name' => $normalizedDeviceName,
            'platform' => $normalizedPlatform !== '' ? $normalizedPlatform : null,
            'app_version' => $normalizedAppVersion !== '' ? $normalizedAppVersion : null,
            'trusted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'last_seen_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'is_active' => 1,
        ]);

        $trustedDeviceId = (int) $this->connection->fetchOne(
            'SELECT idTrustedDevice
             FROM trusted_mobile_devices
             WHERE user_id = ? AND device_id = ?
             ORDER BY idTrustedDevice DESC
             LIMIT 1',
            [$userId, $normalizedDeviceId]
        );

        if ($trustedDeviceId <= 0) {
            throw new \RuntimeException('Trusted device could not be resolved.');
        }

        return $trustedDeviceId;
    }

    public function isDeviceTrusted(int $userId, string $deviceId): bool
    {
        $this->assertSchemaReady();
        if ($userId <= 0 || trim($deviceId) === '') {
            return false;
        }

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*)
             FROM trusted_mobile_devices
             WHERE user_id = ?
               AND device_id = ?
               AND is_active = 1',
            [$userId, trim($deviceId)]
        ) > 0;
    }

    public function findTrustedDeviceId(int $userId, string $deviceId): ?int
    {
        $this->assertSchemaReady();
        if ($userId <= 0 || trim($deviceId) === '') {
            return null;
        }

        $id = (int) $this->connection->fetchOne(
            'SELECT idTrustedDevice
             FROM trusted_mobile_devices
             WHERE user_id = ?
               AND device_id = ?
               AND is_active = 1
             ORDER BY idTrustedDevice DESC
             LIMIT 1',
            [$userId, trim($deviceId)]
        );

        return $id > 0 ? $id : null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listTrustedDevices(int $userId): array
    {
        $this->assertSchemaReady();
        if ($userId <= 0) {
            return [];
        }

        return $this->connection->fetchAllAssociative(
            'SELECT idTrustedDevice, device_id, device_name, platform, app_version, trusted_at, last_seen_at, is_active
             FROM trusted_mobile_devices
             WHERE user_id = ? AND is_active = 1
             ORDER BY trusted_at DESC, idTrustedDevice DESC',
            [$userId]
        );
    }

    public function normalizeDeviceId(string $value): string
    {
        return $this->normalizeMeta($value, 140);
    }

    public function normalizeDeviceName(string $value): string
    {
        $normalized = $this->normalizeMeta($value, 140);

        return $normalized !== '' ? $normalized : 'Mobile device';
    }

    private function normalizeMeta(string $value, int $maxLength): string
    {
        $normalized = trim($value);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';
        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, $maxLength);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function extractUserFromJoinedRow(array $row): array
    {
        $user = $row;
        foreach ([
            'idMobileAccessToken',
            'user_id',
            'token_hash',
            'token_prefix',
            'device_id',
            'device_name',
            'platform',
            'app_version',
            'issued_at',
            'expires_at',
            'last_used_at',
            'revoked_at',
        ] as $tokenField) {
            unset($user[$tokenField]);
        }

        return $user;
    }

    private function assertSchemaReady(): void
    {
        if (!$this->ensureSchemaReady()) {
            throw new \RuntimeException('Mobile auth schema is not ready.');
        }
    }

    private function generateToken(): string
    {
        $raw = base64_encode(random_bytes(48));
        $token = strtr(rtrim($raw, '='), '+/', '-_');

        return $token;
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = trim((string) $request->headers->get('Authorization', ''));
        if ($header === '') {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) !== 1) {
            return null;
        }

        $token = trim((string) ($matches[1] ?? ''));

        return $token !== '' ? $token : null;
    }
}
