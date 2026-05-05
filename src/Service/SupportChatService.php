<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class SupportChatService
{
    private const ROLE_ADMIN = 'ROLE_ADMIN';
    private const ROLE_USER = 'ROLE_USER';
    private const MAX_MESSAGE_LENGTH = 2000;

    private ?bool $schemaReady = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function isSchemaReady(): bool
    {
        if ($this->schemaReady !== null) {
            return $this->schemaReady;
        }

        try {
            $this->schemaReady = $this->connection->createSchemaManager()->tablesExist(['support_messages']);
        } catch (\Throwable) {
            $this->schemaReady = false;
        }

        return $this->schemaReady;
    }

    public function getSchemaMissingMessage(): string
    {
        return 'Support chat table is missing. Run the SQL query for support_messages first.';
    }

    public function listUserConversation(int $userId, int $limit = 200): array
    {
        if ($userId <= 0 || !$this->isSchemaReady()) {
            return [];
        }

        $limit = max(1, min($limit, 300));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.*,
                    sender.nom AS sender_nom,
                    sender.prenom AS sender_prenom,
                    recipient.nom AS recipient_nom,
                    recipient.prenom AS recipient_prenom
             FROM support_messages m
             LEFT JOIN users sender ON sender.idUser = m.sender_user_id
             LEFT JOIN users recipient ON recipient.idUser = m.recipient_user_id
             WHERE (
                (m.sender_user_id = ? AND m.sender_role = ? AND m.recipient_role = ?)
                OR
                (m.recipient_user_id = ? AND m.sender_role = ? AND m.recipient_role = ?)
             )
             ORDER BY m.created_at ASC, m.idSupportMessage ASC
             LIMIT '.$limit,
            [$userId, self::ROLE_USER, self::ROLE_ADMIN, $userId, self::ROLE_ADMIN, self::ROLE_USER]
        );

        return array_map(fn (array $row): array => $this->decorateMessageRow($row), $rows);
    }

    public function listUserConversationSince(int $userId, int $afterId, int $limit = 120): array
    {
        if ($userId <= 0 || !$this->isSchemaReady()) {
            return [];
        }

        $afterId = max(0, $afterId);
        $limit = max(1, min($limit, 300));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.*,
                    sender.nom AS sender_nom,
                    sender.prenom AS sender_prenom,
                    recipient.nom AS recipient_nom,
                    recipient.prenom AS recipient_prenom
             FROM support_messages m
             LEFT JOIN users sender ON sender.idUser = m.sender_user_id
             LEFT JOIN users recipient ON recipient.idUser = m.recipient_user_id
             WHERE m.idSupportMessage > ?
               AND (
                (m.sender_user_id = ? AND m.sender_role = ? AND m.recipient_role = ?)
                OR
                (m.recipient_user_id = ? AND m.sender_role = ? AND m.recipient_role = ?)
             )
             ORDER BY m.idSupportMessage ASC
             LIMIT '.$limit,
            [$afterId, $userId, self::ROLE_USER, self::ROLE_ADMIN, $userId, self::ROLE_ADMIN, self::ROLE_USER]
        );

        return array_map(fn (array $row): array => $this->decorateMessageRow($row), $rows);
    }

    public function listAdminConversations(string $search = ''): array
    {
        if (!$this->isSchemaReady()) {
            return [];
        }

        $search = trim($search);
        $params = [
            self::ROLE_USER,
            self::ROLE_ADMIN,
            self::ROLE_USER,
            self::ROLE_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_USER,
            self::ROLE_USER,
            self::ROLE_ADMIN,
        ];

        $sql = 'SELECT
                    u.idUser,
                    u.nom,
                    u.prenom,
                    u.email,
                    u.profile_image_path,
                    MAX(m.created_at) AS last_message_at,
                    SUM(
                        CASE
                            WHEN m.sender_role = ? AND m.recipient_role = ? AND m.is_read = 0
                            THEN 1
                            ELSE 0
                        END
                    ) AS unread_count
                FROM users u
                INNER JOIN support_messages m
                    ON (
                        (m.sender_user_id = u.idUser AND m.sender_role = ? AND m.recipient_role = ?)
                        OR
                        (m.recipient_user_id = u.idUser AND m.sender_role = ? AND m.recipient_role = ?)
                    )
                WHERE UPPER(COALESCE(u.role, ?)) <> ?';

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $sql .= ' AND (
                u.nom LIKE ?
                OR u.prenom LIKE ?
                OR u.email LIKE ?
                OR CONCAT(COALESCE(u.prenom, \'\'), \' \', COALESCE(u.nom, \'\')) LIKE ?
            )';
            array_push($params, $needle, $needle, $needle, $needle);
        }

        $sql .= ' GROUP BY u.idUser, u.nom, u.prenom, u.email, u.profile_image_path
                  ORDER BY last_message_at DESC, u.idUser DESC';

        $rows = $this->connection->fetchAllAssociative($sql, $params);
        $result = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['idUser'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $displayName = trim(sprintf('%s %s', (string) ($row['prenom'] ?? ''), (string) ($row['nom'] ?? '')));
            if ($displayName === '') {
                $displayName = sprintf('User #%d', $userId);
            }

            $row['display_name'] = $displayName;
            $row['unread_count'] = (int) ($row['unread_count'] ?? 0);
            $row['last_message_preview'] = $this->getLastMessagePreviewForUser($userId);
            $result[] = $row;
        }

        return $result;
    }

    public function getConversationUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT idUser, nom, prenom, email, role, profile_image_path
             FROM users
             WHERE idUser = ? LIMIT 1',
            [$userId]
        );

        if (!$row || strtoupper((string) ($row['role'] ?? '')) === self::ROLE_ADMIN) {
            return null;
        }

        $row['display_name'] = $this->buildName(
            (string) ($row['prenom'] ?? ''),
            (string) ($row['nom'] ?? ''),
            self::ROLE_USER,
            (int) ($row['idUser'] ?? 0)
        );

        return $row;
    }

    public function listAdminConversationWithUser(int $userId, int $limit = 300): array
    {
        if ($userId <= 0 || !$this->isSchemaReady()) {
            return [];
        }

        $limit = max(1, min($limit, 500));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.*,
                    sender.nom AS sender_nom,
                    sender.prenom AS sender_prenom,
                    recipient.nom AS recipient_nom,
                    recipient.prenom AS recipient_prenom
             FROM support_messages m
             LEFT JOIN users sender ON sender.idUser = m.sender_user_id
             LEFT JOIN users recipient ON recipient.idUser = m.recipient_user_id
             WHERE (
                (m.sender_user_id = ? AND m.sender_role = ? AND m.recipient_role = ?)
                OR
                (m.recipient_user_id = ? AND m.sender_role = ? AND m.recipient_role = ?)
             )
             ORDER BY m.created_at ASC, m.idSupportMessage ASC
             LIMIT '.$limit,
            [$userId, self::ROLE_USER, self::ROLE_ADMIN, $userId, self::ROLE_ADMIN, self::ROLE_USER]
        );

        return array_map(fn (array $row): array => $this->decorateMessageRow($row), $rows);
    }

    public function listAdminConversationWithUserSince(int $userId, int $afterId, int $limit = 150): array
    {
        if ($userId <= 0 || !$this->isSchemaReady()) {
            return [];
        }

        $afterId = max(0, $afterId);
        $limit = max(1, min($limit, 400));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.*,
                    sender.nom AS sender_nom,
                    sender.prenom AS sender_prenom,
                    recipient.nom AS recipient_nom,
                    recipient.prenom AS recipient_prenom
             FROM support_messages m
             LEFT JOIN users sender ON sender.idUser = m.sender_user_id
             LEFT JOIN users recipient ON recipient.idUser = m.recipient_user_id
             WHERE m.idSupportMessage > ?
               AND (
                (m.sender_user_id = ? AND m.sender_role = ? AND m.recipient_role = ?)
                OR
                (m.recipient_user_id = ? AND m.sender_role = ? AND m.recipient_role = ?)
             )
             ORDER BY m.idSupportMessage ASC
             LIMIT '.$limit,
            [$afterId, $userId, self::ROLE_USER, self::ROLE_ADMIN, $userId, self::ROLE_ADMIN, self::ROLE_USER]
        );

        return array_map(fn (array $row): array => $this->decorateMessageRow($row), $rows);
    }

    public function markUserConversationAsRead(int $userId): void
    {
        if ($userId <= 0 || !$this->isSchemaReady()) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE support_messages
             SET is_read = 1, read_at = NOW()
             WHERE recipient_user_id = ?
               AND sender_role = ?
               AND recipient_role = ?
               AND is_read = 0',
            [$userId, self::ROLE_ADMIN, self::ROLE_USER]
        );
    }

    public function markAdminConversationAsRead(int $userId): void
    {
        if ($userId <= 0 || !$this->isSchemaReady()) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE support_messages
             SET is_read = 1, read_at = NOW()
             WHERE sender_user_id = ?
               AND sender_role = ?
               AND recipient_role = ?
               AND is_read = 0',
            [$userId, self::ROLE_USER, self::ROLE_ADMIN]
        );
    }

    public function sendFromUserToAdmin(int $userId, string $message): int
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('A valid authenticated user is required.');
        }

        $this->assertSchemaReady();
        $body = $this->normalizeMessage($message);
        $adminId = $this->resolveAdminRecipientId();

        $this->connection->insert('support_messages', [
            'sender_user_id' => $userId,
            'recipient_user_id' => $adminId,
            'sender_role' => self::ROLE_USER,
            'recipient_role' => self::ROLE_ADMIN,
            'message_text' => $body,
            'is_read' => 0,
        ]);

        $senderName = $this->resolveUserDisplayName($userId, self::ROLE_USER);
        $this->notificationService->createNotification(
            $adminId,
            null,
            $userId,
            'SUPPORT_CHAT',
            'New support message',
            sprintf('%s sent a support message: %s', $senderName, $this->buildNotificationPreview($body))
        );

        return $this->resolveInsertedMessageId(
            $userId,
            $adminId,
            self::ROLE_USER,
            self::ROLE_ADMIN,
            $body
        );
    }

    public function sendFromAdminToUser(int $adminId, int $userId, string $message): int
    {
        if ($adminId <= 0) {
            throw new \InvalidArgumentException('A valid admin account is required.');
        }

        $this->assertSchemaReady();
        if ($userId <= 0) {
            throw new \InvalidArgumentException('A valid target user is required.');
        }

        $target = $this->getConversationUser($userId);
        if ($target === null) {
            throw new \RuntimeException('Target user not found.');
        }

        $body = $this->normalizeMessage($message);
        $this->connection->insert('support_messages', [
            'sender_user_id' => $adminId,
            'recipient_user_id' => $userId,
            'sender_role' => self::ROLE_ADMIN,
            'recipient_role' => self::ROLE_USER,
            'message_text' => $body,
            'is_read' => 0,
        ]);

        $senderName = $this->resolveUserDisplayName($adminId, self::ROLE_ADMIN);
        $this->notificationService->createNotification(
            $userId,
            null,
            $userId,
            'SUPPORT_CHAT',
            'Support reply',
            sprintf('%s replied to your support chat: %s', $senderName, $this->buildNotificationPreview($body))
        );

        return $this->resolveInsertedMessageId(
            $adminId,
            $userId,
            self::ROLE_ADMIN,
            self::ROLE_USER,
            $body
        );
    }

    public function findMessageById(int $messageId): ?array
    {
        if ($messageId <= 0 || !$this->isSchemaReady()) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT m.*,
                    sender.nom AS sender_nom,
                    sender.prenom AS sender_prenom,
                    recipient.nom AS recipient_nom,
                    recipient.prenom AS recipient_prenom
             FROM support_messages m
             LEFT JOIN users sender ON sender.idUser = m.sender_user_id
             LEFT JOIN users recipient ON recipient.idUser = m.recipient_user_id
             WHERE m.idSupportMessage = ?
             LIMIT 1',
            [$messageId]
        );

        if (!$row) {
            return null;
        }

        return $this->decorateMessageRow($row);
    }

    private function assertSchemaReady(): void
    {
        if (!$this->isSchemaReady()) {
            throw new \RuntimeException($this->getSchemaMissingMessage());
        }
    }

    private function normalizeMessage(string $message): string
    {
        $normalized = preg_replace("/\r\n?/", "\n", $message);
        $normalized = trim((string) $normalized);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Message cannot be empty.');
        }

        if (mb_strlen($normalized) > self::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Message is too long (max %d characters).', self::MAX_MESSAGE_LENGTH));
        }

        return $normalized;
    }

    private function resolveAdminRecipientId(): int
    {
        $adminId = $this->nullablePositiveInt($this->connection->fetchOne(
            "SELECT idUser FROM users WHERE role = 'ROLE_ADMIN' AND status = 'ACTIVE' ORDER BY idUser ASC LIMIT 1"
        ));

        if ($adminId === null) {
            $adminId = $this->nullablePositiveInt($this->connection->fetchOne(
                "SELECT idUser FROM users WHERE role = 'ROLE_ADMIN' ORDER BY idUser ASC LIMIT 1"
            ));
        }

        if ($adminId === null) {
            throw new \RuntimeException('No admin account is available to receive support messages.');
        }

        return $adminId;
    }

    private function resolveInsertedMessageId(
        int $senderUserId,
        int $recipientUserId,
        string $senderRole,
        string $recipientRole,
        string $messageText
    ): int {
        $id = $this->nullablePositiveInt($this->connection->fetchOne(
            'SELECT idSupportMessage
             FROM support_messages
             WHERE sender_user_id = ?
               AND recipient_user_id = ?
               AND sender_role = ?
               AND recipient_role = ?
               AND message_text = ?
             ORDER BY idSupportMessage DESC
             LIMIT 1',
            [$senderUserId, $recipientUserId, $senderRole, $recipientRole, $messageText]
        ));

        if ($id === null) {
            throw new \RuntimeException('Support message was inserted but could not be resolved.');
        }

        return $id;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    private function decorateMessageRow(array $row): array
    {
        $senderRole = strtoupper((string) ($row['sender_role'] ?? ''));
        $recipientRole = strtoupper((string) ($row['recipient_role'] ?? ''));

        $row['sender_name'] = $this->buildName(
            (string) ($row['sender_prenom'] ?? ''),
            (string) ($row['sender_nom'] ?? ''),
            $senderRole,
            (int) ($row['sender_user_id'] ?? 0)
        );
        $row['recipient_name'] = $this->buildName(
            (string) ($row['recipient_prenom'] ?? ''),
            (string) ($row['recipient_nom'] ?? ''),
            $recipientRole,
            (int) ($row['recipient_user_id'] ?? 0)
        );

        return $row;
    }

    private function buildName(string $prenom, string $nom, string $role, int $userId): string
    {
        $display = trim(sprintf('%s %s', $prenom, $nom));
        if ($display !== '') {
            return $display;
        }

        return strtoupper($role) === self::ROLE_ADMIN
            ? sprintf('Admin #%d', $userId > 0 ? $userId : 0)
            : sprintf('User #%d', $userId > 0 ? $userId : 0);
    }

    private function getLastMessagePreviewForUser(int $userId): string
    {
        $message = (string) $this->connection->fetchOne(
            'SELECT message_text
             FROM support_messages
             WHERE (
                (sender_user_id = ? AND sender_role = ? AND recipient_role = ?)
                OR
                (recipient_user_id = ? AND sender_role = ? AND recipient_role = ?)
             )
             ORDER BY created_at DESC, idSupportMessage DESC
             LIMIT 1',
            [$userId, self::ROLE_USER, self::ROLE_ADMIN, $userId, self::ROLE_ADMIN, self::ROLE_USER]
        );

        return $this->shortenMessage($message, 120);
    }

    private function resolveUserDisplayName(int $userId, string $fallbackRole): string
    {
        if ($userId <= 0) {
            return strtoupper($fallbackRole) === self::ROLE_ADMIN ? 'Admin' : 'User';
        }

        $row = $this->connection->fetchAssociative(
            'SELECT idUser, nom, prenom, role FROM users WHERE idUser = ? LIMIT 1',
            [$userId]
        ) ?: [];

        return $this->buildName(
            (string) ($row['prenom'] ?? ''),
            (string) ($row['nom'] ?? ''),
            strtoupper((string) ($row['role'] ?? $fallbackRole)),
            (int) ($row['idUser'] ?? $userId)
        );
    }

    private function buildNotificationPreview(string $message): string
    {
        return $this->shortenMessage($message, 90);
    }

    private function shortenMessage(string $message, int $maxLength): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', str_replace("\n", ' ', $message)) ?? '');
        if ($clean === '') {
            return '';
        }

        if (mb_strlen($clean) <= $maxLength) {
            return $clean;
        }

        return rtrim(mb_substr($clean, 0, max(1, $maxLength - 1))).'...';
    }
}
