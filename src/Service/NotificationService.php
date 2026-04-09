<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class NotificationService
{
    private const ROLE_ADMIN = 'ROLE_ADMIN';

    public function __construct(
        private readonly Connection $connection,
        private readonly MailerInterface $mailer,
    )
    {
    }

    public function getRecentNotificationsFor(int $userId, string $role, int $limit = 15): array
    {
        $limit = max(1, min($limit, 50));

        if (strtoupper($role) === self::ROLE_ADMIN) {
            return $this->connection->fetchAllAssociative(
                'SELECT * FROM notifications
                 WHERE recipient_user_id = ? OR recipient_role = ?
                 ORDER BY created_at DESC, idNotification DESC
                 LIMIT '.$limit,
                [$userId, self::ROLE_ADMIN]
            );
        }

        return $this->connection->fetchAllAssociative(
            'SELECT * FROM notifications
             WHERE recipient_user_id = ?
             ORDER BY created_at DESC, idNotification DESC
             LIMIT '.$limit,
            [$userId]
        );
    }

    public function countUnreadFor(int $userId, string $role): int
    {
        if (strtoupper($role) === self::ROLE_ADMIN) {
            return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND (recipient_user_id = ? OR recipient_role = ?)',
                [$userId, self::ROLE_ADMIN]
            );
        }

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND recipient_user_id = ?',
            [$userId]
        );
    }

    public function markAllAsRead(int $userId, string $role): void
    {
        if (strtoupper($role) === self::ROLE_ADMIN) {
            $this->connection->executeStatement(
                'UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND (recipient_user_id = ? OR recipient_role = ?)',
                [$userId, self::ROLE_ADMIN]
            );

            return;
        }

        $this->connection->executeStatement(
            'UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND recipient_user_id = ?',
            [$userId]
        );
    }

    public function notifyAdminsAboutPendingUser(array $pendingUser): void
    {
        $name = trim(sprintf('%s %s', $pendingUser['prenom'] ?? '', $pendingUser['nom'] ?? ''));
        $name = $name !== '' ? $name : ($pendingUser['email'] ?? 'Unknown user');

        $this->createNotification(
            null,
            self::ROLE_ADMIN,
            (int) ($pendingUser['idUser'] ?? 0),
            'USER_SIGNUP_PENDING',
            'New user pending approval',
            sprintf(
                'User %s (%s) created an account and is waiting for admin review.',
                $name,
                $pendingUser['email'] ?? '-'
            )
        );
    }

    public function notifyUserAccountStatusChanged(int $userId, string $newStatus): void
    {
        $status = strtoupper(trim($newStatus));
        $title = 'Account status updated';
        $message = 'Your account status was updated by admin.';

        if ($status === 'ACTIVE') {
            $title = 'Account approved';
            $message = 'Your account is now ACTIVE and ready to use.';
        } elseif ($status === 'DECLINED') {
            $title = 'Account declined';
            $message = 'Your account request was declined by admin.';
        } elseif ($status === 'BANNED') {
            $title = 'Account banned';
            $message = 'Your account was banned by admin.';
        } elseif ($status === 'INACTIVE') {
            $title = 'Account inactive';
            $message = 'Your account was set to INACTIVE by admin.';
        }

        $this->createNotification($userId, null, $userId, 'ACCOUNT_STATUS', $title, $message);
    }

    public function notifyCashbackSubmitted(array $cashback): void
    {
        $this->createNotification(
            null,
            self::ROLE_ADMIN,
            (int) ($cashback['id_user'] ?? 0),
            'CASHBACK_SUBMITTED',
            'New cashback submitted',
            sprintf(
                'User #%d submitted a cashback request at partner "%s" for purchase amount %.2f DT.',
                (int) ($cashback['id_user'] ?? 0),
                $cashback['partenaire_nom'] ?? 'Unknown partner',
                (float) ($cashback['montant_achat'] ?? 0)
            )
        );
    }

    public function notifyCashbackRewardGranted(array $cashback, float $bonusAmount, string $note = ''): void
    {
        $partner = $cashback['partenaire_nom'] ?? 'Unknown partner';
        $userId = (int) ($cashback['id_user'] ?? 0);
        $suffix = trim($note) !== '' ? ' Note: '.trim($note) : '';

        $this->createNotification(
            $userId,
            null,
            $userId,
            'CASHBACK_REWARD',
            'Reward received',
            sprintf('Admin granted you +%.2f DT for partner "%s".%s', $bonusAmount, $partner, $suffix)
        );

        $this->createNotification(
            null,
            self::ROLE_ADMIN,
            $userId,
            'CASHBACK_REWARD',
            'Cashback reward granted',
            sprintf('Admin granted +%.2f DT to user #%d for partner "%s".%s', $bonusAmount, $userId, $partner, $suffix)
        );
    }

    public function createNotification(
        ?int $recipientUserId,
        ?string $recipientRole,
        ?int $relatedUserId,
        string $type,
        string $title,
        string $message
    ): void {
        $normalizedType = strtoupper(trim($type)) !== '' ? strtoupper(trim($type)) : 'INFO';
        $normalizedTitle = trim($title) !== '' ? trim($title) : 'Notification';
        $normalizedMessage = trim($message) !== '' ? trim($message) : 'You have a new notification.';

        $this->connection->insert('notifications', [
            'recipient_user_id' => $recipientUserId,
            'recipient_role' => $recipientRole !== null ? strtoupper($recipientRole) : null,
            'related_user_id' => $relatedUserId,
            'type' => $normalizedType,
            'title' => $normalizedTitle,
            'message' => $normalizedMessage,
            'is_read' => 0,
        ]);

        $recipientEmails = $this->resolveRecipientEmails($recipientUserId, $recipientRole);
        if ($recipientEmails !== []) {
            $this->sendEmailNotification($recipientEmails, $normalizedType, $normalizedTitle, $normalizedMessage);
        }
    }

    private function resolveRecipientEmails(?int $recipientUserId, ?string $recipientRole): array
    {
        $emails = [];

        if ($recipientUserId !== null && $recipientUserId > 0) {
            $userEmail = $this->connection->fetchOne('SELECT email FROM users WHERE idUser = ? LIMIT 1', [$recipientUserId]);
            if (is_string($userEmail) && trim($userEmail) !== '') {
                $emails[] = trim($userEmail);
            }
        }

        if ($recipientRole !== null && strtoupper(trim($recipientRole)) === self::ROLE_ADMIN) {
            $adminRows = $this->connection->fetchFirstColumn(
                "SELECT email FROM users WHERE role = 'ROLE_ADMIN' AND status = 'ACTIVE'"
            );
            foreach ($adminRows as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $emails[] = trim($value);
                }
            }
        }

        $unique = [];
        foreach ($emails as $email) {
            $normalized = strtolower($email);
            if (filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                $unique[$normalized] = $normalized;
            }
        }

        return array_values($unique);
    }

    private function sendEmailNotification(array $recipientEmails, string $type, string $title, string $message): void
    {
        $dsn = $_ENV['MAILER_DSN'] ?? $_SERVER['MAILER_DSN'] ?? '';
        if ($dsn === '' || str_starts_with($dsn, 'null://')) {
            return;
        }

        $senderEmail = trim((string) ($_ENV['NEXORA_SMTP_EMAIL'] ?? $_SERVER['NEXORA_SMTP_EMAIL'] ?? ''));
        $senderName = trim((string) ($_ENV['NEXORA_SMTP_FROM_NAME'] ?? $_SERVER['NEXORA_SMTP_FROM_NAME'] ?? 'NEXORA Notification'));
        if ($senderEmail === '') {
            $senderEmail = 'noreply@nexora.local';
        }

        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $safeType = htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = sprintf(
            '<div style="font-family:Segoe UI,Arial,sans-serif;background:#f4f7fb;padding:24px;">'
            .'<div style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #e6ebf2;border-radius:12px;padding:24px;">'
            .'<h2 style="margin:0 0 10px;color:#0A2540;">%s</h2>'
            .'<p style="margin:0 0 8px;color:#334155;">%s</p>'
            .'<small style="display:inline-block;margin-top:8px;color:#64748b;">Type: %s</small>'
            .'</div></div>',
            $safeTitle,
            $safeMessage,
            $safeType
        );

        foreach ($recipientEmails as $recipientEmail) {
            try {
                $email = (new Email())
                    ->from(new Address($senderEmail, $senderName))
                    ->to($recipientEmail)
                    ->subject('[NEXORA] '.$title)
                    ->html($html);
                $this->mailer->send($email);
            } catch (\Throwable) {
                // Keep in-app notifications working even if SMTP fails.
            }
        }
    }
}
