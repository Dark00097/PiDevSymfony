<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class ActivityService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function log(int $userId, string $actionType, ?string $source = null, ?string $details = null): void
    {
        if ($userId <= 0 || trim($actionType) === '') {
            return;
        }

        $this->connection->insert('user_activity_log', [
            'idUser' => $userId,
            'action_type' => strtoupper(trim($actionType)),
            'action_source' => $source !== null ? trim($source) : null,
            'details' => $details !== null ? trim($details) : null,
        ]);
    }

    public function listRecent(int $userId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        return $this->connection->fetchAllAssociative(
            'SELECT * FROM user_activity_log WHERE idUser = ? ORDER BY created_at DESC, idAction DESC LIMIT '.$limit,
            [$userId]
        );
    }

    public function countByType(int $userId, string $actionType, int $days = 30): int
    {
        if ($userId <= 0 || trim($actionType) === '') {
            return 0;
        }

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*)
             FROM user_activity_log
             WHERE idUser = ?
               AND action_type = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$userId, strtoupper(trim($actionType)), max(1, $days)]
        );
    }
}
