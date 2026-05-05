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

    public function sendAccountCreatedEmail(array $account, array $user): void
    {
        $dsn = $_ENV['MAILER_DSN'] ?? $_SERVER['MAILER_DSN'] ?? '';
        if ($dsn === '' || str_starts_with($dsn, 'null://')) {
            return;
        }

        $recipientEmail = trim((string) ($user['email'] ?? ''));
        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $senderEmail = trim((string) ($_ENV['NEXORA_SMTP_EMAIL'] ?? $_SERVER['NEXORA_SMTP_EMAIL'] ?? 'noreply@nexora.local'));
        $senderName  = trim((string) ($_ENV['NEXORA_SMTP_FROM_NAME'] ?? $_SERVER['NEXORA_SMTP_FROM_NAME'] ?? 'NEXORA Bank'));

        $nom    = htmlspecialchars(trim(($user['prenom'] ?? '').' '.($user['nom'] ?? '')), ENT_QUOTES, 'UTF-8');
        $numero = htmlspecialchars((string) ($account['numeroCompte'] ?? '—'), ENT_QUOTES, 'UTF-8');
        $type   = htmlspecialchars((string) ($account['typeCompte']   ?? '—'), ENT_QUOTES, 'UTF-8');
        $statut = htmlspecialchars((string) ($account['statutCompte'] ?? '—'), ENT_QUOTES, 'UTF-8');
        $solde  = number_format((float) ($account['solde'] ?? 0), 2, '.', ' ');
        $date   = htmlspecialchars((string) ($account['dateOuverture'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8');
        $retrait  = number_format((float) ($account['plafondRetrait']  ?? 0), 2, '.', ' ');
        $virement = number_format((float) ($account['plafondVirement'] ?? 0), 2, '.', ' ');

        $html = <<<HTML
        <div style="font-family:'Segoe UI',Arial,sans-serif;background:#f0f4f9;padding:32px 16px;">
          <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10);">

            <!-- Header -->
            <div style="background:linear-gradient(135deg,#0a2540 0%,#0f8a86 100%);padding:28px 32px;text-align:center;">
              <h1 style="margin:0;color:#fff;font-size:22px;font-weight:800;letter-spacing:.5px;">NEXORA Bank</h1>
              <p style="margin:6px 0 0;color:rgba(255,255,255,.75);font-size:13px;">Confirmation de création de compte</p>
            </div>

            <!-- Body -->
            <div style="padding:28px 32px;">
              <p style="margin:0 0 20px;font-size:15px;color:#1a2b3c;">Bonjour <strong>{$nom}</strong>,</p>
              <p style="margin:0 0 24px;font-size:14px;color:#4a5568;line-height:1.6;">
                Votre compte bancaire a été créé avec succès par l'administration Nexora. Voici le récapitulatif complet :
              </p>

              <!-- Info table -->
              <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr style="background:#f8fafc;">
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;width:45%;">N° de Compte</td>
                  <td style="padding:12px 16px;color:#0f172a;font-weight:700;border-bottom:1px solid #e5e7eb;font-family:monospace;">{$numero}</td>
                </tr>
                <tr>
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Type de Compte</td>
                  <td style="padding:12px 16px;color:#0f172a;font-weight:700;border-bottom:1px solid #e5e7eb;">{$type}</td>
                </tr>
                <tr style="background:#f8fafc;">
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Statut</td>
                  <td style="padding:12px 16px;font-weight:700;border-bottom:1px solid #e5e7eb;">
                    <span style="background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:20px;font-size:12px;">{$statut}</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Solde Initial</td>
                  <td style="padding:12px 16px;color:#0f172a;font-weight:700;border-bottom:1px solid #e5e7eb;">{$solde} DT</td>
                </tr>
                <tr style="background:#f8fafc;">
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Date d'Ouverture</td>
                  <td style="padding:12px 16px;color:#0f172a;font-weight:700;border-bottom:1px solid #e5e7eb;">{$date}</td>
                </tr>
                <tr>
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Plafond Retrait</td>
                  <td style="padding:12px 16px;color:#0f172a;font-weight:700;border-bottom:1px solid #e5e7eb;">{$retrait} DT</td>
                </tr>
                <tr style="background:#f8fafc;">
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;">Plafond Virement</td>
                  <td style="padding:12px 16px;color:#0f172a;font-weight:700;">{$virement} DT</td>
                </tr>
              </table>

              <p style="margin:24px 0 0;font-size:13px;color:#9ca3af;text-align:center;">
                Cet email a été envoyé automatiquement par NEXORA Bank. Ne pas répondre.
              </p>
            </div>

            <!-- Footer -->
            <div style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb;">
              <p style="margin:0;font-size:12px;color:#9ca3af;">© 2026 NEXORA Bank — Tous droits réservés</p>
            </div>
          </div>
        </div>
        HTML;

        try {
            $email = (new Email())
                ->from(new Address($senderEmail, $senderName))
                ->to($recipientEmail)
                ->subject('[NEXORA] Votre compte bancaire a été créé')
                ->html($html);
            $this->mailer->send($email);
        } catch (\Throwable) {
            // Email failure must never block the account creation
        }
    }

    public function notifyAdminsAboutPendingAccount(array $account, array $user): void
    {
        $name = trim(sprintf('%s %s', $user['prenom'] ?? '', $user['nom'] ?? ''));
        $name = $name !== '' ? $name : ($user['email'] ?? 'Utilisateur inconnu');

        $this->createNotification(
            null,
            self::ROLE_ADMIN,
            (int) ($user['idUser'] ?? 0),
            'ACCOUNT_PENDING',
            'Nouvelle demande de compte bancaire',
            sprintf(
                'Le client %s a soumis une demande de création de compte %s (N° %s). En attente de validation.',
                $name,
                $account['typeCompte'] ?? 'Courant',
                $account['numeroCompte'] ?? '-'
            )
        );
    }

    public function sendSms(string $phone, string $message): bool
    {
        $accountSid = trim((string) ($_ENV['TWILIO_ACCOUNT_SID'] ?? $_SERVER['TWILIO_ACCOUNT_SID'] ?? ''));
        $authToken  = trim((string) ($_ENV['TWILIO_AUTH_TOKEN']  ?? $_SERVER['TWILIO_AUTH_TOKEN']  ?? ''));
        $fromNumber = trim((string) ($_ENV['TWILIO_FROM_NUMBER'] ?? $_SERVER['TWILIO_FROM_NUMBER'] ?? ''));

        if ($accountSid === '' || $authToken === '' || $fromNumber === ''
            || str_starts_with($accountSid, 'ACxxx')) {
            return false;
        }

        // Auto-prefix Tunisian numbers if no country code
        $phone = trim($phone);
        if ($phone !== '' && !str_starts_with($phone, '+')) {
            $phone = '+216' . ltrim($phone, '0');
        }

        if ($phone === '') {
            return false;
        }

        try {
            $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $accountSid);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_USERPWD        => $accountSid.':'.$authToken,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'From' => $fromNumber,
                    'To'   => $phone,
                    'Body' => $message,
                ]),
            ]);
            curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode >= 200 && $httpCode < 300;
        } catch (\Throwable) {
            return false;
        }
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

    public function sendAccountValidationEmail(array $account, array $user, bool $accepted): void
    {
        $recipientEmail = trim((string) ($user['email'] ?? ''));
        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $clientName = trim(sprintf('%s %s', (string) ($user['prenom'] ?? ''), (string) ($user['nom'] ?? '')));
        if ($clientName === '') {
            $clientName = (string) ($user['email'] ?? 'Client');
        }

        $statusLabel = $accepted ? 'Active' : 'Refuse';
        $statusColor = $accepted ? '#16a34a' : '#dc2626';
        $headline = $accepted ? 'Compte bancaire active' : 'Compte bancaire refuse';
        $intro = $accepted
            ? 'Bienvenue a Nexora Bank, ce compte est maintenant active et visible avec toutes ses informations.'
            : 'Le compte est refuse. Veuillez contacter l administration.';

        $rows = [
            ['Client', $clientName],
            ['Email', (string) ($user['email'] ?? '-')],
            ['Telephone', (string) ($user['telephone'] ?? '-')],
            ['Numero de compte', (string) ($account['numeroCompte'] ?? '-')],
            ['Type de compte', (string) ($account['typeCompte'] ?? '-')],
            ['Statut', $statusLabel],
            ['Solde initial', number_format((float) ($account['solde'] ?? 0), 2, '.', ' ').' DT'],
            ['Date d ouverture', (string) ($account['dateOuverture'] ?? '-')],
            ['Plafond retrait', number_format((float) ($account['plafondRetrait'] ?? 0), 2, '.', ' ').' DT'],
            ['Plafond virement', number_format((float) ($account['plafondVirement'] ?? 0), 2, '.', ' ').' DT'],
        ];

        $tableRows = '';
        foreach ($rows as [$label, $value]) {
            $tableRows .= sprintf(
                '<tr><td style="padding:12px 16px;background:#f8fafc;color:#64748b;font-weight:700;border-bottom:1px solid #e5e7eb;width:42%%;">%s</td><td style="padding:12px 16px;color:#0f172a;font-weight:600;border-bottom:1px solid #e5e7eb;">%s</td></tr>',
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
            );
        }

        $html = sprintf(
            '<div style="font-family:Segoe UI,Arial,sans-serif;background:#eef3f8;padding:32px 16px;">'
            .'<div style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 12px 32px rgba(15,23,42,.12);">'
            .'<div style="background:linear-gradient(135deg,#0a2540 0%%,#0f8a86 100%%);padding:28px 32px;">'
            .'<div style="font-size:24px;font-weight:800;color:#ffffff;">%s</div>'
            .'<div style="margin-top:8px;font-size:14px;color:rgba(255,255,255,.78);">%s</div>'
            .'</div>'
            .'<div style="padding:28px 32px;">'
            .'<p style="margin:0 0 18px;font-size:15px;color:#1e293b;">Bonjour <strong>%s</strong>,</p>'
            .'<p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#475569;">%s</p>'
            .'<div style="display:inline-block;margin:0 0 20px;padding:8px 14px;border-radius:999px;background:%s;color:#ffffff;font-size:12px;font-weight:800;letter-spacing:.03em;">%s</div>'
            .'<table style="width:100%%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">%s</table>'
            .'<p style="margin:22px 0 0;font-size:13px;line-height:1.7;color:#64748b;">Cet email a ete envoye automatiquement par Nexora Bank. Pour toute question, contactez l administration.</p>'
            .'</div>'
            .'</div>'
            .'</div>',
            htmlspecialchars($headline, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($accepted ? 'Confirmation officielle d activation du compte' : 'Notification officielle de refus du compte', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($intro, ENT_QUOTES, 'UTF-8'),
            $statusColor,
            htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'),
            $tableRows
        );

        $this->sendHtmlEmail([$recipientEmail], '[NEXORA] '.$headline, $html, 'NEXORA Bank');
    }

    public function createNotification(
        ?int $recipientUserId,
        ?string $recipientRole,
        ?int $relatedUserId,
        string $type,
        string $title,
        string $message,
        bool $sendEmail = true
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
        if ($sendEmail && $recipientEmails !== []) {
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

    public function deleteNotification(int $notificationId, int $userId, string $role): void
    {
        if (strtoupper($role) === self::ROLE_ADMIN) {
            $this->connection->executeStatement(
                'DELETE FROM notifications WHERE idNotification = ?',
                [$notificationId]
            );

            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM notifications WHERE idNotification = ? AND recipient_user_id = ?',
            [$notificationId, $userId]
        );
    }

    public function sendVirementEmail(string $recipientEmail, string $recipientName, float $amount, string $currency, array $sender): void
    {
        $dsn = $_ENV['MAILER_DSN'] ?? $_SERVER['MAILER_DSN'] ?? '';
        if ($dsn === '' || str_starts_with($dsn, 'null://')) {
            return;
        }

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $senderEmail = trim((string) ($_ENV['NEXORA_SMTP_EMAIL'] ?? $_SERVER['NEXORA_SMTP_EMAIL'] ?? 'noreply@nexora.local'));
        $senderName  = trim((string) ($_ENV['NEXORA_SMTP_FROM_NAME'] ?? $_SERVER['NEXORA_SMTP_FROM_NAME'] ?? 'NEXORA Bank'));

        $safeName    = htmlspecialchars($recipientName !== '' ? $recipientName : 'Client', ENT_QUOTES, 'UTF-8');
        $safeAmount  = number_format($amount, 3, '.', ' ');
        $safeCurrency = htmlspecialchars($currency, ENT_QUOTES, 'UTF-8');
        $senderFullName = htmlspecialchars(
            trim(($sender['prenom'] ?? '').' '.($sender['nom'] ?? '')) ?: 'Un client Nexora',
            ENT_QUOTES, 'UTF-8'
        );
        $date = date('d/m/Y à H:i');

        $html = <<<HTML
        <div style="font-family:'Segoe UI',Arial,sans-serif;background:#f0f4f9;padding:32px 16px;">
          <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10);">
            <div style="background:linear-gradient(135deg,#0a2540 0%,#0f8a86 100%);padding:28px 32px;text-align:center;">
              <h1 style="margin:0;color:#fff;font-size:22px;font-weight:800;letter-spacing:.5px;">NEXORA Bank</h1>
              <p style="margin:6px 0 0;color:rgba(255,255,255,.75);font-size:13px;">Notification de virement reçu</p>
            </div>
            <div style="padding:28px 32px;">
              <p style="margin:0 0 20px;font-size:15px;color:#1a2b3c;">Bonjour <strong>{$safeName}</strong>,</p>
              <p style="margin:0 0 24px;font-size:14px;color:#4a5568;line-height:1.6;">
                Vous avez reçu un virement bancaire via <strong>NEXORA Bank</strong>. Voici les détails :
              </p>
              <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr style="background:#f8fafc;">
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;width:45%;">Expéditeur</td>
                  <td style="padding:12px 16px;color:#0f172a;font-weight:700;border-bottom:1px solid #e5e7eb;">{$senderFullName}</td>
                </tr>
                <tr>
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Montant</td>
                  <td style="padding:12px 16px;font-weight:700;border-bottom:1px solid #e5e7eb;">
                    <span style="background:#dcfce7;color:#16a34a;padding:4px 12px;border-radius:20px;font-size:15px;">{$safeAmount} {$safeCurrency}</span>
                  </td>
                </tr>
                <tr style="background:#f8fafc;">
                  <td style="padding:12px 16px;color:#6b7280;font-weight:600;">Date</td>
                  <td style="padding:12px 16px;color:#0f172a;font-weight:700;">{$date}</td>
                </tr>
              </table>
              <p style="margin:24px 0 0;font-size:13px;color:#9ca3af;text-align:center;">
                Cet email a été envoyé automatiquement par NEXORA Bank. Ne pas répondre.
              </p>
            </div>
            <div style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb;">
              <p style="margin:0;font-size:12px;color:#9ca3af;">© 2026 NEXORA Bank — Tous droits réservés</p>
            </div>
          </div>
        </div>
        HTML;

        try {
            $email = (new Email())
                ->from(new Address($senderEmail, $senderName))
                ->to($recipientEmail)
                ->subject('[NEXORA] Vous avez reçu un virement de '.$safeAmount.' '.$safeCurrency)
                ->html($html);
            $this->mailer->send($email);
        } catch (\Throwable) {
            // L'email ne doit jamais bloquer la transaction
        }
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

    private function sendHtmlEmail(array $recipientEmails, string $subject, string $html, string $senderName): void
    {
        $dsn = $_ENV['MAILER_DSN'] ?? $_SERVER['MAILER_DSN'] ?? '';
        if ($dsn === '' || str_starts_with($dsn, 'null://')) {
            return;
        }

        $senderEmail = trim((string) ($_ENV['NEXORA_SMTP_EMAIL'] ?? $_SERVER['NEXORA_SMTP_EMAIL'] ?? ''));
        if ($senderEmail === '') {
            $senderEmail = 'noreply@nexora.local';
        }

        foreach ($recipientEmails as $recipientEmail) {
            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                $email = (new Email())
                    ->from(new Address($senderEmail, $senderName))
                    ->to($recipientEmail)
                    ->subject($subject)
                    ->html($html);
                $this->mailer->send($email);
            } catch (\Throwable) {
                // Keep the business flow working even if SMTP fails.
            }
        }
    }
}
