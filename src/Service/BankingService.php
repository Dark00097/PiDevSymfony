<?php

namespace App\Service;

use App\Entity\Credit;
use App\Entity\Garantiecredit;
use Doctrine\DBAL\Connection;

final class BankingService
{
    private const DEFAULT_ACCOUNT_LIMIT = 10.0;
    private const BAD_WORDS = [
        'fuck',
        'shit',
        'merde',
        'stupid',
        'idiot',
        'hate',
    ];
    private const ALLOWED_USER_ROLES = ['ROLE_USER', 'ROLE_ADMIN'];
    private const ALLOWED_USER_STATUS = ['PENDING', 'ACTIVE', 'DECLINED', 'INACTIVE', 'BANNED'];
    /**
     * @var array<string, array<int, string>>
     */
    private array $tableColumnsCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly LegacyBankingSecurity $security,
        private readonly NotificationService $notificationService,
        private readonly ActivityService $activityService,
    ) {
    }

    public function getLandingStats(): array
    {
        return [
            'transactions' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM transactions'),
            'users' => (int) $this->connection->fetchOne("SELECT COUNT(*) FROM users WHERE status = 'ACTIVE'"),
            'availability' => 99.9,
        ];
    }

    public function getAdminDashboard(): array
    {
        $allTransactions = $this->listTransactions();

        return [
            'users_total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users'),
            'users_pending' => (int) $this->connection->fetchOne("SELECT COUNT(*) FROM users WHERE status = 'PENDING'"),
            'accounts_total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM compte'),
            'transactions_total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM transactions'),
            'credits_total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM credit'),
            'credits_pending' => (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM credit WHERE LOWER(TRIM(statut)) IN ('en attente', 'pending', 'a traiter')"
            ),
            'cashback_total' => (float) $this->connection->fetchOne('SELECT COALESCE(SUM(montant_cashback), 0) FROM cashback_entries'),
            'deposits_total' => (float) $this->connection->fetchOne('SELECT COALESCE(SUM(solde), 0) FROM compte'),
            'reclamations_total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM reclamation'),
            'recent_transactions' => array_slice($allTransactions, 0, 6),
            'monthly_transactions' => $this->buildMonthlyTransactionsSeries($allTransactions),
            'account_type_distribution' => $this->buildAccountTypeDistribution(),
            'recent_notifications' => $this->connection->fetchAllAssociative(
                'SELECT * FROM notifications ORDER BY created_at DESC, idNotification DESC LIMIT 8'
            ),
        ];
    }

    public function getUserDashboard(int $userId): array
    {
        $accounts = $this->listAccounts($userId);
        $transactions = $this->listTransactions($userId);
        $credits = $this->listCredits($userId);

        return [
            'accounts_total' => count($accounts),
            'transactions_total' => count($transactions),
            'credits_total' => count($credits),
            'cashback_pending' => (float) $this->connection->fetchOne(
                "SELECT COALESCE(SUM(montant_cashback), 0) FROM cashback_entries WHERE id_user = ? AND statut IN ('En attente', 'Valide')",
                [$userId]
            ),
            'cashback_credited' => (float) $this->connection->fetchOne(
                "SELECT COALESCE(SUM(montant_cashback), 0) FROM cashback_entries WHERE id_user = ? AND statut = 'Credite'",
                [$userId]
            ),
            'recent_transactions' => array_slice($transactions, 0, 6),
            'recent_credits' => array_slice($credits, 0, 5),
        ];
    }

    public function listUsers(string $search = ''): array
    {
        $search = trim($search);
        if ($search === '') {
            return $this->connection->fetchAllAssociative('SELECT * FROM users ORDER BY created_at DESC, idUser DESC');
        }

        $needle = '%'.$search.'%';

        return $this->connection->fetchAllAssociative(
            'SELECT * FROM users
             WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR telephone LIKE ?
             ORDER BY created_at DESC, idUser DESC',
            [$needle, $needle, $needle, $needle]
        );
    }

    public function saveUser(array $data, ?int $id = null): void
    {
        if ($id !== null && $id <= 0) {
            $id = null;
        }

        $nom = trim((string) ($data['nom'] ?? ''));
        $prenom = trim((string) ($data['prenom'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $telephone = trim((string) ($data['telephone'] ?? ''));
        $role = strtoupper(trim((string) ($data['role'] ?? '')));
        $status = strtoupper(trim((string) ($data['status'] ?? '')));

        if ($id === null) {
            if ($nom === '' || $prenom === '' || $email === '' || $telephone === '') {
                throw new \InvalidArgumentException('Last name, first name, email and phone are required.');
            }

            $this->assertValidUserName($nom, 'Last name');
            $this->assertValidUserName($prenom, 'First name');
            $this->assertValidUserEmail($email);
            if (!$this->isValidPhone($telephone)) {
                throw new \InvalidArgumentException('Phone format is invalid.');
            }

            $resolvedRole = $role !== '' ? $role : 'ROLE_USER';
            $resolvedStatus = $status !== '' ? $status : 'PENDING';
            $this->assertValidUserRole($resolvedRole);
            $this->assertValidUserStatus($resolvedStatus);

            if ($this->emailExistsForAnotherUser($email, null)) {
                throw new \InvalidArgumentException('This email is already used by another user.');
            }

            $plainPassword = (string) ($data['password'] ?? '');
            if ($plainPassword === '') {
                throw new \InvalidArgumentException('Password is required to create a user.');
            }
            $this->assertStrongPassword($plainPassword);

            $payload = [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'role' => $resolvedRole,
                'status' => $resolvedStatus,
                'biometric_enabled' => (int) ((string) ($data['biometric_enabled'] ?? '0') === '1'),
            ];

            if (array_key_exists('profile_image_path', $data)) {
                $profileImagePath = trim((string) $data['profile_image_path']);
                $payload['profile_image_path'] = $profileImagePath !== '' ? $profileImagePath : null;
            }

            $payload['password'] = $this->security->hashPassword($plainPassword);
            $payload['account_opened_from'] = 'Symfony admin';
            $payload['account_opened_location'] = 'Unknown location';
            $payload['profile_image_path'] ??= null;
            $this->connection->insert('users', $payload);

            return;
        }

        $existing = $this->connection->fetchAssociative('SELECT * FROM users WHERE idUser = ? LIMIT 1', [$id]);
        if (!$existing) {
            throw new \RuntimeException('User not found. Leave ID empty to create a new user.');
        }

        $payload = [
            'nom' => $nom !== '' ? $nom : (string) ($existing['nom'] ?? ''),
            'prenom' => $prenom !== '' ? $prenom : (string) ($existing['prenom'] ?? ''),
            'email' => $email !== '' ? $email : (string) ($existing['email'] ?? ''),
            'telephone' => $telephone !== '' ? $telephone : (string) ($existing['telephone'] ?? ''),
            'role' => $role !== '' ? $role : (string) ($existing['role'] ?? 'ROLE_USER'),
            'status' => $status !== '' ? $status : (string) ($existing['status'] ?? 'PENDING'),
            'biometric_enabled' => array_key_exists('biometric_enabled', $data)
                ? (int) ((string) $data['biometric_enabled'] === '1')
                : (int) ($existing['biometric_enabled'] ?? 0),
        ];

        $this->assertValidUserName((string) $payload['nom'], 'Last name');
        $this->assertValidUserName((string) $payload['prenom'], 'First name');
        $this->assertValidUserEmail((string) $payload['email']);
        $this->assertValidUserRole((string) $payload['role']);
        $this->assertValidUserStatus((string) $payload['status']);
        if (!$this->isValidPhone((string) $payload['telephone'])) {
            throw new \InvalidArgumentException('Phone format is invalid.');
        }

        if ($this->emailExistsForAnotherUser((string) $payload['email'], $id)) {
            throw new \InvalidArgumentException('This email is already used by another user.');
        }

        if (array_key_exists('profile_image_path', $data)) {
            $profileImagePath = trim((string) $data['profile_image_path']);
            $payload['profile_image_path'] = $profileImagePath !== '' ? $profileImagePath : null;
        }

        if (($data['password'] ?? '') !== '') {
            $plainPassword = (string) $data['password'];
            $this->assertStrongPassword($plainPassword);
            $payload['password'] = $this->security->hashPassword($plainPassword);
        }

        $this->connection->update('users', $payload, ['idUser' => $id]);
    }

    public function updateUserStatus(int $userId, string $status): void
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('A valid user ID is required.');
        }

        $normalized = strtoupper(trim($status));
        $this->assertValidUserStatus($normalized);
        $exists = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE idUser = ?', [$userId]);
        if ($exists === 0) {
            throw new \RuntimeException('User not found.');
        }

        $this->connection->update('users', ['status' => $normalized], ['idUser' => $userId]);
        $this->notificationService->notifyUserAccountStatusChanged($userId, $normalized);
    }

    public function deleteUser(int $userId): void
    {
        $this->connection->delete('users', ['idUser' => $userId]);
    }

    public function listPendingAccounts(): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT c.*, CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,'')) AS owner_name,
                    u.telephone AS owner_phone, u.email AS owner_email
             FROM compte c
             LEFT JOIN users u ON u.idUser = c.idUser
             WHERE LOWER(TRIM(c.statutCompte)) = 'en attente'
             ORDER BY c.idCompte DESC"
        );
    }

    public function listAccounts(?int $userId = null): array
    {
        $sql = 'SELECT c.*, CONCAT(COALESCE(u.prenom, \'\'), \' \', COALESCE(u.nom, \'\')) AS owner_name
                FROM compte c
                LEFT JOIN users u ON u.idUser = c.idUser';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE c.idUser = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY c.idCompte DESC';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function saveAccount(array $data, ?int $id = null, ?int $forcedUserId = null): int
    {
        $existingAccount = null;
        if ($id !== null) {
            $existingAccount = $this->connection->fetchAssociative(
                'SELECT * FROM compte WHERE idCompte = ? LIMIT 1',
                [$id]
            );
            if (!$existingAccount) {
                throw new \RuntimeException('Compte introuvable.');
            }
        }

        $isPortalCreate = ($id === null && $forcedUserId !== null);
        if ($isPortalCreate) {
            $data['statutCompte'] = 'En attente';
        }

        $userId = $forcedUserId
            ?? $this->nullableInt($data['idUser'] ?? null)
            ?? $this->nullableInt($existingAccount['idUser'] ?? null);

        if ($userId === null) {
            throw new \InvalidArgumentException('Utilisateur obligatoire pour ce compte.');
        }
        if (!$this->userExists($userId)) {
            throw new \RuntimeException('Utilisateur introuvable.');
        }

        $payload = [
            'numeroCompte'   => trim((string) ($data['numeroCompte'] ?? '')),
            'solde'          => (float) ($data['solde'] ?? 0),
            'dateOuverture'  => $this->resolveAccountDateValue(
                $data['dateOuverture'] ?? null,
                $existingAccount['dateOuverture'] ?? null
            ),
            'statutCompte'   => $isPortalCreate
                ? 'En attente'
                : $this->resolveAccountTextValue($data['statutCompte'] ?? null, $existingAccount['statutCompte'] ?? null, 'Actif'),
            'plafondRetrait' => $this->resolveAccountLimitValue(
                $data['plafondRetrait'] ?? null,
                $existingAccount['plafondRetrait'] ?? null
            ),
            'plafondVirement'=> $this->resolveAccountLimitValue(
                $data['plafondVirement'] ?? null,
                $existingAccount['plafondVirement'] ?? null
            ),
            'typeCompte'     => $this->resolveAccountTextValue($data['typeCompte'] ?? null, $existingAccount['typeCompte'] ?? null, 'Courant'),
            'idUser'         => $userId,
        ];

        if ($id === null) {
            $this->connection->insert('compte', $payload);
            $newId = (int) $this->connection->lastInsertId(); // must be called immediately after insert

            if ($userId !== null) {
                $this->activityService->log($userId, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.');

                $user = $this->connection->fetchAssociative('SELECT * FROM users WHERE idUser = ? LIMIT 1', [$userId]);
                if ($user) {
                    $payload['idCompte'] = $newId;
                    if ($isPortalCreate) {
                        // Portal creation → notify admins (pending validation)
                        $this->notificationService->notifyAdminsAboutPendingAccount($payload, $user);
                    } else {
                        // Admin creation → send confirmation email to the client
                        try {
                            $this->notificationService->sendAccountCreatedEmail($payload, $user);
                        } catch (\Throwable) {
                            // email failure must never block account creation
                        }
                    }
                }
            }

            return $newId;
        }

        $criteria = ['idCompte' => $id];
        if ($forcedUserId !== null) {
            $criteria['idUser'] = $forcedUserId;
        }

        $this->connection->update('compte', $payload, $criteria);
        if ($userId !== null) {
            $this->activityService->log($userId, 'ACCOUNT_UPDATE', 'Symfony portal', sprintf('Bank account #%d updated.', $id));
        }

        return $id;
    }

    private function resolveAccountDateValue(mixed $value, mixed $fallback = null): string
    {
        $resolved = trim((string) $value);
        if ($resolved !== '') {
            return $resolved;
        }

        $fallbackValue = trim((string) $fallback);

        return $fallbackValue !== '' ? $fallbackValue : date('Y-m-d');
    }

    private function resolveAccountTextValue(mixed $value, mixed $fallback = null, string $default = ''): string
    {
        $resolved = trim((string) $value);
        if ($resolved !== '') {
            return $resolved;
        }

        $fallbackValue = trim((string) $fallback);
        if ($fallbackValue !== '') {
            return $fallbackValue;
        }

        return $default;
    }

    private function resolveAccountLimitValue(mixed $value, mixed $fallback = null): float
    {
        $resolved = trim((string) $value);
        if ($resolved !== '') {
            return (float) $resolved;
        }

        $fallbackValue = trim((string) $fallback);
        if ($fallbackValue !== '') {
            return (float) $fallbackValue;
        }

        return self::DEFAULT_ACCOUNT_LIMIT;
    }

    public function validateAccount(int $idCompte, bool $accept): void
    {
        $account = $this->connection->fetchAssociative('SELECT * FROM compte WHERE idCompte = ? LIMIT 1', [$idCompte]);
        if (!$account) {
            return;
        }

        // Accepter → statut Actif | Refuser → supprimer le compte
        if ($accept) {
            $this->connection->update('compte', ['statutCompte' => 'Actif'], ['idCompte' => $idCompte]);
            $account['statutCompte'] = 'Actif';
        } else {
            $this->connection->delete('compte', ['idCompte' => $idCompte]);
        }

        // Notifications and SMS are best-effort — never block the action
        try {
            $userId = $this->nullableInt($account['idUser'] ?? null);
            if ($userId === null) {
                return;
            }

            $user = $this->connection->fetchAssociative('SELECT * FROM users WHERE idUser = ? LIMIT 1', [$userId]);
            if (!$user) {
                return;
            }

            $phone = trim((string) ($user['telephone'] ?? ''));

            if ($accept) {
                $smsMsg     = 'Bienvenue à Nexora Bank, ce compte est maintenant actif et visible avec toutes ses informations.';
                $notifTitle = 'Compte bancaire activé';
                $notifMsg   = sprintf('Votre compte %s (N° %s) a été accepté et est maintenant actif.', $account['typeCompte'] ?? '', $account['numeroCompte'] ?? '');
            } else {
                $smsMsg     = "Ce compte n'a pas été accepté et n'est pas accessible.";
                $notifTitle = 'Compte bancaire refusé';
                $notifMsg   = sprintf('Votre demande de compte %s (N° %s) a été refusée et supprimée.', $account['typeCompte'] ?? '', $account['numeroCompte'] ?? '');
            }

            if ($accept) {
                $notifTitle = 'Compte bancaire activé';
                $notifMsg = 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.';
                $smsMsg = $notifMsg;
            } else {
                $notifTitle = 'Compte bancaire refusé';
                $notifMsg = "Le compte est refusé. Veuillez contacter l’administration.";
                $smsMsg = $notifMsg;
            }

            try {
                $this->notificationService->createNotification(
                    $userId,
                    null,
                    $userId,
                    'ACCOUNT_VALIDATION',
                    $notifTitle,
                    $notifMsg,
                    false
                );
            } catch (\Throwable) {
                // notification failure must not block the validation
            }

            try {
                $this->notificationService->sendAccountValidationEmail($account, $user, $accept);
            } catch (\Throwable) {
                // email failure must not block the validation
            }

            if ($phone !== '') {
                try {
                    $this->notificationService->sendSms($phone, $smsMsg);
                } catch (\Throwable) {
                    // SMS failure must not block the validation
                }
            }
        } catch (\Throwable) {
            // Any notification error is silently ignored
        }
    }

    public function deleteAccount(int $id, ?int $forcedUserId = null): void
    {
        $criteria = ['idCompte' => $id];
        if ($forcedUserId !== null) {
            $criteria['idUser'] = $forcedUserId;
        }
        $this->connection->delete('compte', $criteria);
        if ($forcedUserId !== null) {
            $this->activityService->log($forcedUserId, 'ACCOUNT_DELETE', 'Symfony portal', sprintf('Bank account #%d deleted.', $id));
        }
    }

    public function listTransactions(?int $userId = null): array
    {
        $sql = 'SELECT t.*,
                       c.idUser AS compte_user_id,
                       c.numeroCompte AS compte_numero,
                       c.typeCompte AS compte_type,
                       c.statutCompte AS compte_status,
                       c.plafondRetrait AS compte_plafond_retrait,
                       c.plafondVirement AS compte_plafond_virement,
                       COALESCE(t.idUser, c.idUser) AS resolved_user_id,
                       CONCAT(COALESCE(u.prenom, \'\'), \' \', COALESCE(u.nom, \'\')) AS user_name,
                       u.email AS user_email
                FROM transactions t
                LEFT JOIN compte c ON c.idCompte = t.idCompte
                LEFT JOIN users u ON u.idUser = COALESCE(t.idUser, c.idUser)';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE (t.idUser = ? OR c.idUser = ?)';
            $params[] = $userId;
            $params[] = $userId;
        }
        $sql .= ' ORDER BY t.idTransaction DESC';

        $rows = $this->connection->fetchAllAssociative($sql, $params);
        foreach ($rows as &$row) {
            $row['montant_value'] = $this->security->decryptAmount($row['montant'] ?? null) ?? 0.0;
            $row['montantPaye_value'] = $this->security->decryptAmount($row['montantPaye'] ?? null) ?? 0.0;
            $row['is_anomalie'] = $this->isTransactionAnomaly((int) ($row['resolved_user_id'] ?? $row['idUser'] ?? 0), (float) $row['montant_value']);
            
            // Résolution logique du type de transaction (CREDIT/DEBIT)
            // Règles métier officielles :
            // DEPOT / VERSEMENT               → CREDIT (entrée d'argent)
            // PAIEMENT / RETRAIT              → DEBIT  (sortie d'argent)
            // VIREMENT :
            //   idCompteDestinataire == compte courant de la ligne → CREDIT (virement reçu)
            //   idCompteDestinataire != compte courant de la ligne → DEBIT  (virement envoyé)
            //   idCompteDestinataire NULL/0                        → DEBIT  (inconnu → DEBIT)
            $typeRaw = strtolower(trim((string) ($row['typeTransaction'] ?? '')));
            $resolvedType = 'UNKNOWN';

            if (str_contains($typeRaw, 'depot') || str_contains($typeRaw, 'versement')) {
                $resolvedType = 'CREDIT';
            } elseif (str_contains($typeRaw, 'paiement') || str_contains($typeRaw, 'retrait') || str_contains($typeRaw, 'paimenet')) {
                $resolvedType = 'DEBIT';
            } elseif (str_contains($typeRaw, 'virement')) {
                $dest = (int) ($row['idCompteDestinataire'] ?? 0);
                $currentAccount = (int) ($row['idCompte'] ?? 0);
                // CREDIT si idCompteDestinataire == compte courant (réception)
                // CREDIT si idCompteDestinataire NULL/0 (inconnu → CREDIT)
                // DEBIT  si idCompteDestinataire est un compte différent (envoi)
                $resolvedType = ($dest === 0 || $dest === $currentAccount) ? 'CREDIT' : 'DEBIT';
            }

            $row['resolved_type'] = $resolvedType;
        }

        return $rows;
    }

    public function saveTransaction(array $data, ?int $id = null, ?int $forcedUserId = null): int
    {
        $existing = null;
        if ($id !== null) {
            $existing = $this->connection->fetchAssociative(
                'SELECT * FROM transactions WHERE idTransaction = ? LIMIT 1',
                [$id]
            );
            if (!$existing) {
                throw new \RuntimeException('Transaction introuvable.');
            }
        }

        $accountId = $this->nullableInt($data['idCompte'] ?? null) ?? $this->nullableInt($existing['idCompte'] ?? null);
        if ($accountId === null) {
            throw new \InvalidArgumentException('Compte requis pour enregistrer une transaction.');
        }

        $account = $this->connection->fetchAssociative(
            'SELECT idCompte, idUser FROM compte WHERE idCompte = ? LIMIT 1',
            [$accountId]
        );
        if (!$account) {
            throw new \RuntimeException('Compte introuvable.');
        }

        $accountOwnerId = $this->nullableInt($account['idUser'] ?? null);
        $userId = $forcedUserId
            ?? $this->nullableInt($data['idUser'] ?? null)
            ?? $accountOwnerId
            ?? $this->nullableInt($existing['idUser'] ?? null);
        if ($userId === null) {
            throw new \InvalidArgumentException('Utilisateur introuvable pour cette transaction.');
        }

        $amount = (float) ($data['montant'] ?? 0);
        // Pour un PAIEMENT, montantPaye = 0 à la création (sera mis à jour après paiement Stripe)
        $typeForPaid = strtoupper(trim((string) ($data['typeTransaction'] ?? '')));
        $paidAmount = ($typeForPaid === 'PAIEMENT' && $id === null)
            ? 0.0
            : (float) ($data['montantPaye'] ?? $amount);

        // Calculer soldeApres automatiquement si non fourni
        $soldeApres = null;
        if (($data['soldeApres'] ?? '') !== '') {
            $soldeApres = (float) $data['soldeApres'];
        } else {
            $accountRow = $this->connection->fetchAssociative(
                'SELECT solde FROM compte WHERE idCompte = ? LIMIT 1',
                [$accountId]
            );
            if ($accountRow) {
                $currentSolde = (float) ($accountRow['solde'] ?? 0);
                $type = strtoupper(trim((string) ($data['typeTransaction'] ?? 'CREDIT')));
                $soldeApres = match ($type) {
                    'DEBIT', 'RETRAIT', 'PAIEMENT' => round($currentSolde - $amount, 2),
                    default => round($currentSolde + $amount, 2),
                };
            }
        }

        $payload = [
            'idCompte' => $accountId,
            'idUser' => $userId,
            'categorie' => trim((string) ($data['categorie'] ?? '')),
            'dateTransaction' => trim((string) ($data['dateTransaction'] ?? date('Y-m-d'))),
            'montant' => $this->security->encryptAmount($amount),
            'typeTransaction' => trim((string) ($data['typeTransaction'] ?? 'Credit')),
            'soldeApres' => $soldeApres,
            'description' => trim((string) ($data['description'] ?? '')),
            'montantPaye' => $this->security->encryptAmount($paidAmount),
            // Champs virement
            'idCompteDestinataire' => trim((string) ($data['compteDestinataire'] ?? $data['idCompteDestinataire'] ?? '')) ?: null,
            'nomDestinataire'      => trim((string) ($data['nomDestinataire']      ?? '')) ?: null,
            'emailDestinataire'    => trim((string) ($data['emailDestinataire']    ?? '')) ?: null,
            // Champs conversion devise
            'original_amount'  => ($data['original_amount']  ?? '') !== '' ? (float) $data['original_amount']  : null,
            'original_currency'=> trim((string) ($data['original_currency'] ?? 'TND')) ?: 'TND',
            'exchange_rate'    => ($data['exchange_rate']    ?? '') !== '' ? (float) $data['exchange_rate']    : null,
            'conversion_fee'   => ($data['conversion_fee']   ?? '') !== '' ? (float) $data['conversion_fee']   : 0.0,
        ];

        if ($id === null) {
            $this->connection->insert('transactions', $payload);
            $newTransactionId = (int) $this->connection->lastInsertId();
            $this->activityService->log($userId, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.');

            // Envoyer email de virement au destinataire si applicable
            $typeTransaction = strtoupper(trim((string) ($data['typeTransaction'] ?? '')));
            if ($typeTransaction === 'VIREMENT') {
                $emailDestinataire = trim((string) ($data['emailDestinataire'] ?? ''));
                $nomDestinataire   = trim((string) ($data['nomDestinataire']   ?? ''));
                if ($emailDestinataire !== '' && filter_var($emailDestinataire, FILTER_VALIDATE_EMAIL)) {
                    $senderUser = $this->connection->fetchAssociative(
                        'SELECT * FROM users WHERE idUser = ? LIMIT 1',
                        [$userId]
                    );
                    try {
                        $this->notificationService->sendVirementEmail(
                            $emailDestinataire,
                            $nomDestinataire,
                            $amount,
                            (string) ($data['original_currency'] ?? 'TND'),
                            $senderUser ?: []
                        );
                    } catch (\Throwable) {
                        // L'email ne doit jamais bloquer la transaction
                    }
                }
            }

            return $newTransactionId;
        }

        $criteria = ['idTransaction' => $id];
        if ($forcedUserId !== null) {
            $criteria['idUser'] = $forcedUserId;
        }

        $this->connection->update('transactions', $payload, $criteria);
        $this->activityService->log($userId, 'TRANSACTION_UPDATE', 'Symfony portal', sprintf('Transaction #%d updated.', $id));

        return $id;
    }

    public function deleteTransaction(int $id, ?int $forcedUserId = null): void
    {
        $criteria = ['idTransaction' => $id];
        if ($forcedUserId !== null) {
            $criteria['idUser'] = $forcedUserId;
        }
        $this->connection->delete('transactions', $criteria);
        if ($forcedUserId !== null) {
            $this->activityService->log($forcedUserId, 'TRANSACTION_DELETE', 'Symfony portal', sprintf('Transaction #%d deleted.', $id));
        }
    }

    public function listCredits(?int $userId = null): array
    {
        $sql = 'SELECT c.*,
                       cp.numeroCompte AS compte_numero,
                       cp.idUser AS compte_user_id,
                       COALESCE(c.idUser, cp.idUser) AS resolved_user_id,
                       CONCAT(COALESCE(u.prenom, \'\'), \' \', COALESCE(u.nom, \'\')) AS user_name
                FROM credit c
                LEFT JOIN compte cp ON cp.idCompte = c.idCompte
                LEFT JOIN users u ON u.idUser = COALESCE(c.idUser, cp.idUser)';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE (c.idUser = ? OR cp.idUser = ?)';
            $params[] = $userId;
            $params[] = $userId;
        }
        $sql .= ' ORDER BY c.idCredit DESC';

        $credits = $this->connection->fetchAllAssociative($sql, $params);
        foreach ($credits as &$credit) {
            $credit['risk_score'] = $this->calculateCreditRiskScore($credit);
        }

        return $credits;
    }

    public function getCreditTypeDistribution(?int $userId = null): array
    {
        $sql = 'SELECT COALESCE(NULLIF(TRIM(typeCredit), \'\'), \'Inconnu\') AS label, COUNT(*) AS total
                FROM credit';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE idUser = ?';
            $params[] = $userId;
        }
        $sql .= ' GROUP BY COALESCE(NULLIF(TRIM(typeCredit), \'\'), \'Inconnu\')
                  ORDER BY total DESC, label ASC';

        return $this->buildTypeDistribution($this->connection->fetchAllAssociative($sql, $params));
    }

    public function getGarantieTypeDistribution(?int $userId = null): array
    {
        $sql = 'SELECT COALESCE(NULLIF(TRIM(g.typeGarantie), \'\'), \'Inconnue\') AS label, COUNT(*) AS total
                FROM garantiecredit g';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE g.idUser = ?';
            $params[] = $userId;
        }
        $sql .= ' GROUP BY COALESCE(NULLIF(TRIM(g.typeGarantie), \'\'), \'Inconnue\')
                  ORDER BY total DESC, label ASC';

        return $this->buildTypeDistribution($this->connection->fetchAllAssociative($sql, $params));
    }

    public function saveCredit(array $data, ?int $id = null, ?int $forcedUserId = null): void
    {
        $existingCredit = $this->resolveCreditForWrite($id, $forcedUserId);
        if ($existingCredit === null) {
            $id = null;
        }

        $accountId = (int) ($data['idCompte'] ?? 0);
        if ($accountId <= 0) {
            throw new \InvalidArgumentException('Compte requis pour le credit.');
        }

        $account = $this->connection->fetchAssociative(
            'SELECT idCompte, idUser FROM compte WHERE idCompte = ? LIMIT 1',
            [$accountId]
        );
        if (!$account) {
            throw new \RuntimeException('Compte introuvable.');
        }

        $accountOwnerId = $this->nullableInt($account['idUser'] ?? null);
        $requestedUserId = $forcedUserId ?? $this->nullableInt($data['idUser'] ?? null);
        if ($accountOwnerId !== null && $requestedUserId !== null && $requestedUserId !== $accountOwnerId) {
            throw new \InvalidArgumentException('Le compte selectionne n\'appartient pas a l\'utilisateur choisi.');
        }

        $effectiveUserId = $requestedUserId ?? $accountOwnerId;
        if ($effectiveUserId === null) {
            throw new \InvalidArgumentException('Utilisateur requis pour enregistrer un credit.');
        }

        $selectedGarantieId = (int) ($data['idGarantie'] ?? 0);
        if ($selectedGarantieId > 0) {
            $selectedGarantie = $this->connection->fetchAssociative(
                'SELECT g.idGarantie,
                        g.idUser,
                        c.idUser AS credit_user_id,
                        cp.idUser AS compte_user_id
                 FROM garantiecredit g
                 LEFT JOIN credit c ON c.idCredit = g.idCredit
                 LEFT JOIN compte cp ON cp.idCompte = c.idCompte
                 WHERE g.idGarantie = ?
                 LIMIT 1',
                [$selectedGarantieId]
            );
            if (!$selectedGarantie) {
                throw new \InvalidArgumentException('Garantie selectionnee introuvable.');
            }

            $garantieOwnerId = $this->nullableInt($selectedGarantie['idUser'] ?? null)
                ?? $this->nullableInt($selectedGarantie['credit_user_id'] ?? null)
                ?? $this->nullableInt($selectedGarantie['compte_user_id'] ?? null);

            if ($garantieOwnerId !== null && $garantieOwnerId !== $effectiveUserId) {
                throw new \InvalidArgumentException('La garantie selectionnee n\'appartient pas a cet utilisateur.');
            }
        }

        $rawTypeCredit = trim((string) ($data['typeCredit'] ?? ''));
        $typeCredit = Credit::normalizeTypeCreditValue($rawTypeCredit) ?? $rawTypeCredit;
        if ($typeCredit === '') {
            throw new \InvalidArgumentException('Type de credit requis.');
        }

        $amount = (float) ($data['montantDemande'] ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Montant demande invalide.');
        }

        $rate = max(0, (float) ($data['tauxInteret'] ?? 0));
        $duration = (int) ($data['duree'] ?? 0);
        if ($duration <= 0) {
            throw new \InvalidArgumentException('Duree invalide.');
        }

        $autoFunding = ($data['autofinancement'] ?? '') !== '' ? (float) $data['autofinancement'] : null;
        if ($autoFunding !== null && $autoFunding < 0) {
            throw new \InvalidArgumentException('Autofinancement invalide.');
        }

        $monthlyPayment = ($data['mensualite'] ?? '') !== ''
            ? (float) $data['mensualite']
            : $this->calculateMonthlyPayment($amount, $rate, $duration);
        if ($monthlyPayment <= 0) {
            throw new \InvalidArgumentException('Mensualite invalide.');
        }

        $approvedAmount = ($data['montantAccorde'] ?? '') !== ''
            ? (float) $data['montantAccorde']
            : $amount;
        if ($approvedAmount < 0) {
            throw new \InvalidArgumentException('Montant accorde invalide.');
        }

        $dateDemande = $this->normalizeIsoDateNotFuture((string) ($data['dateDemande'] ?? ''), 'Date de demande invalide.');

        $payload = [
            'idCompte' => $accountId,
            'typeCredit' => $typeCredit,
            'montantDemande' => $amount,
            'autofinancement' => $autoFunding,
            'duree' => $duration,
            'tauxInteret' => $rate,
            'mensualite' => $monthlyPayment,
            'montantAccorde' => $approvedAmount,
            'dateDemande' => $dateDemande,
            'statut' => $this->resolveStatusForSave('credit', 'idCredit', $data, $id, $forcedUserId),
            'idUser' => $effectiveUserId,
            'salaire' => ($data['salaire'] ?? '') !== '' ? (float) $data['salaire'] : 0,
            'typeContrat' => trim((string) ($data['typeContrat'] ?? '')),
            'ancienneteAnnees' => max(0, (int) ($data['ancienneteAnnees'] ?? 0)),
        ];

        if ($id === null) {
            $this->connection->insert('credit', $payload);
            $createdId = (int) $this->connection->lastInsertId();

            if ($selectedGarantieId > 0) {
                $this->connection->update(
                    'garantiecredit',
                    ['idCredit' => $createdId, 'idUser' => $effectiveUserId],
                    ['idGarantie' => $selectedGarantieId]
                );
            }

            $this->activityService->log((int) $payload['idUser'], 'CREDIT_CREATE', 'Symfony portal', 'Credit dossier created.');
            $this->notificationService->createNotification(
                (int) $payload['idUser'],
                null,
                (int) $payload['idUser'],
                'CREDIT_CREATE',
                'Credit enregistre',
                sprintf(
                    'Votre credit #%d (%s) a ete enregistre. Montant: %.2f DT. Statut: %s.',
                    $createdId,
                    (string) $payload['typeCredit'],
                    (float) $payload['montantDemande'],
                    (string) $payload['statut']
                )
            );

            return;
        }

        $this->connection->update('credit', $payload, ['idCredit' => $id]);

        if ($selectedGarantieId > 0) {
            $this->connection->update(
                'garantiecredit',
                ['idCredit' => $id, 'idUser' => $effectiveUserId],
                ['idGarantie' => $selectedGarantieId]
            );
        }

        $this->activityService->log((int) $payload['idUser'], 'CREDIT_UPDATE', 'Symfony portal', sprintf('Credit #%d updated.', $id));
        $this->notificationService->createNotification(
            (int) $payload['idUser'],
            null,
            (int) $payload['idUser'],
            'CREDIT_UPDATE',
            'Credit mis a jour',
            sprintf(
                'Votre credit #%d (%s) a ete mis a jour. Montant: %.2f DT. Statut: %s.',
                (int) $id,
                (string) $payload['typeCredit'],
                (float) $payload['montantDemande'],
                (string) $payload['statut']
            )
        );
    }

    public function deleteCredit(int $id, ?int $forcedUserId = null): void
    {
        $existing = $this->resolveCreditForWrite($id, $forcedUserId, true);
        if (!$existing) {
            throw new \RuntimeException('Credit introuvable.');
        }

        $deletedRows = $this->connection->delete('credit', ['idCredit' => $id]);
        if ($deletedRows <= 0) {
            throw new \RuntimeException('Suppression du credit impossible.');
        }

        $resolvedUserId = $forcedUserId ?? $this->nullableInt($existing['resolved_user_id'] ?? null);
        if ($resolvedUserId !== null) {
            $this->notificationService->createNotification(
                $resolvedUserId,
                null,
                $resolvedUserId,
                'CREDIT_DELETE',
                'Credit supprime',
                sprintf(
                    'Le credit #%d (%s) a ete supprime. Montant: %.2f DT.',
                    $id,
                    (string) ($existing['typeCredit'] ?? 'Credit'),
                    (float) ($existing['montantDemande'] ?? 0)
                )
            );
        }

        if ($forcedUserId !== null) {
            $this->activityService->log($forcedUserId, 'CREDIT_DELETE', 'Symfony portal', sprintf('Credit #%d deleted.', $id));
        }
    }

    public function listGaranties(?int $userId = null): array
    {
        $sql = 'SELECT g.*,
                       c.typeCredit,
                       c.montantDemande,
                       c.idCompte,
                       cp.numeroCompte AS compte_numero,
                       cp.idUser AS compte_user_id,
                       COALESCE(g.idUser, c.idUser, cp.idUser) AS resolved_user_id,
                       CONCAT(COALESCE(u.prenom, \'\'), \' \', COALESCE(u.nom, \'\')) AS user_name
                FROM garantiecredit g
                LEFT JOIN credit c ON c.idCredit = g.idCredit
                LEFT JOIN compte cp ON cp.idCompte = c.idCompte
                LEFT JOIN users u ON u.idUser = COALESCE(g.idUser, c.idUser, cp.idUser)';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE (g.idUser = ? OR c.idUser = ? OR cp.idUser = ?)';
            $params[] = $userId;
            $params[] = $userId;
            $params[] = $userId;
        }
        $sql .= ' ORDER BY g.idGarantie DESC';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function saveGarantie(array $data, ?int $id = null, ?int $forcedUserId = null): int
    {
        $existingGarantie = $this->resolveGarantieForWrite($id, $forcedUserId);
        $existingGarantieRaw = null;
        if ($existingGarantie !== null && $id !== null && $id > 0) {
            $existingGarantieRaw = $this->connection->fetchAssociative(
                'SELECT * FROM garantiecredit WHERE idGarantie = ? LIMIT 1',
                [$id]
            ) ?: null;
        }
        if ($existingGarantie === null) {
            $id = null;
        }

        $creditId = (int) ($data['idCredit'] ?? 0);
        $credit = null;
        if ($creditId > 0) {
            $credit = $this->connection->fetchAssociative(
                'SELECT idCredit, idUser, typeCredit FROM credit WHERE idCredit = ? LIMIT 1',
                [$creditId]
            );
            if (!$credit) {
                throw new \RuntimeException('Credit associe introuvable.');
            }
        }

        $resolvedUserId = $forcedUserId
            ?? $this->nullableInt($data['idUser'] ?? null)
            ?? $this->nullableInt($credit['idUser'] ?? null)
            ?? $this->nullableInt($existingGarantie['idUser'] ?? null);
        $typeGarantie = $this->normalizeGarantieTypeForStorage((string) ($data['typeGarantie'] ?? ''));
        if ($typeGarantie === '') {
            throw new \InvalidArgumentException('Type de garantie requis.');
        }

        $estimated = (float) ($data['valeurEstimee'] ?? 0);
        if ($estimated <= 0) {
            throw new \InvalidArgumentException('Valeur estimee invalide.');
        }

        $retained = ($data['valeurRetenue'] ?? '') !== '' ? (float) $data['valeurRetenue'] : round($estimated * 0.8, 2);
        if ($retained <= 0) {
            throw new \InvalidArgumentException('Valeur retenue invalide.');
        }
        if ($retained > $estimated) {
            throw new \InvalidArgumentException('La valeur retenue ne peut pas depasser la valeur estimee.');
        }

        $address = trim((string) ($data['adresseBien'] ?? ''));
        if ($resolvedUserId !== null && $address !== '' && $this->isGarantieAddressAlreadyUsed($resolvedUserId, $address, $id, $creditId)) {
            throw new \InvalidArgumentException('Cette adresse de garantie est deja utilisee sur un autre credit actif.');
        }

        $dateEvaluation = $this->normalizeIsoDateNotFuture((string) ($data['dateEvaluation'] ?? ''), 'Date d\'evaluation invalide.');
        $payload = [
            'idCredit' => $creditId > 0 ? $creditId : null,
            'typeGarantie' => $typeGarantie,
            'description' => trim((string) ($data['description'] ?? '')),
            'adresseBien' => $address,
            'adresseComplete' => trim((string) ($data['adresseComplete'] ?? $address)),
            'ville' => trim((string) ($data['ville'] ?? '')),
            'codePostal' => trim((string) ($data['codePostal'] ?? '')),
            'pays' => trim((string) ($data['pays'] ?? '')),
            'latitude' => trim((string) ($data['latitude'] ?? '')),
            'longitude' => trim((string) ($data['longitude'] ?? '')),
            'statutVerificationAdresse' => trim((string) ($data['statutVerificationAdresse'] ?? 'A verifier')),
            'valeurEstimee' => $estimated,
            'valeurRetenue' => $retained,
            'documentJustificatif' => trim((string) ($data['documentJustificatif'] ?? '')),
            'dateEvaluation' => $dateEvaluation,
            'nomGarant' => trim((string) ($data['nomGarant'] ?? '')),
            'statut' => $this->resolveStatusForSave('garantiecredit', 'idGarantie', $data, $id, $forcedUserId),
            'idUser' => $resolvedUserId ?? 0,
        ];

        $requestedVerificationStatus = trim((string) ($data['statutVerificationDocument'] ?? ''));
        $requestedDocumentStatus = trim((string) ($data['statutDocument'] ?? ''));
        $existingVerificationStatus = trim((string) ($existingGarantieRaw['statutVerificationDocument'] ?? 'en_attente'));
        $existingDocumentStatus = trim((string) ($existingGarantieRaw['statutDocument'] ?? ''));

        if ($requestedVerificationStatus !== '') {
            $payload['statutVerificationDocument'] = $requestedVerificationStatus;
        } elseif ($requestedDocumentStatus !== '') {
            $payload['statutVerificationDocument'] = $this->verificationStatusFromDocumentStatus($requestedDocumentStatus);
        } elseif ($existingVerificationStatus !== '') {
            $payload['statutVerificationDocument'] = $existingVerificationStatus;
        } elseif ($existingDocumentStatus !== '') {
            $payload['statutVerificationDocument'] = $this->verificationStatusFromDocumentStatus($existingDocumentStatus);
        } else {
            $payload['statutVerificationDocument'] = 'en_attente';
        }

        if ($requestedDocumentStatus !== '') {
            $payload['statutDocument'] = $this->normalizeDocumentStatus($requestedDocumentStatus);
        } else {
            $payload['statutDocument'] = $this->documentStatusFromVerificationStatus((string) ($payload['statutVerificationDocument'] ?? 'en_attente'));
        }
        $payload['remarqueAdmin'] = array_key_exists('remarqueAdmin', $data)
            ? trim((string) ($data['remarqueAdmin'] ?? ''))
            : trim((string) ($existingGarantieRaw['remarqueAdmin'] ?? ''));
        $payload['documentPublicId'] = trim((string) ($data['documentPublicId'] ?? '')) !== ''
            ? trim((string) $data['documentPublicId'])
            : trim((string) ($existingGarantieRaw['documentPublicId'] ?? ''));
        $payload['documentMimeType'] = trim((string) ($data['documentMimeType'] ?? '')) !== ''
            ? trim((string) $data['documentMimeType'])
            : trim((string) ($existingGarantieRaw['documentMimeType'] ?? ''));
        $payload['documentUploadedAt'] = trim((string) ($data['documentUploadedAt'] ?? '')) !== ''
            ? trim((string) $data['documentUploadedAt'])
            : trim((string) ($existingGarantieRaw['documentUploadedAt'] ?? ''));
        $payload['documentUrl'] = trim((string) ($data['documentUrl'] ?? '')) !== ''
            ? trim((string) $data['documentUrl'])
            : trim((string) ($existingGarantieRaw['documentUrl'] ?? ($data['documentJustificatif'] ?? '')));

        $payload = $this->filterPayloadForExistingColumns('garantiecredit', $payload);

        if ($id === null) {
            $this->connection->insert('garantiecredit', $payload);
            $this->activityService->log((int) $payload['idUser'], 'GARANTIE_CREATE', 'Symfony portal', 'Guarantee created.');
            $createdId = (int) $this->connection->lastInsertId();
            if ($resolvedUserId !== null) {
                $notificationMessage = $creditId > 0 && $credit
                    ? sprintf(
                        'Votre garantie #%d (%s) a ete enregistree pour le credit #%d (%s).',
                        $createdId,
                        $this->garantieTypeLabel($typeGarantie),
                        $creditId,
                        (string) ($credit['typeCredit'] ?? 'Credit')
                    )
                    : sprintf(
                        'Votre garantie #%d (%s) a ete enregistree sans credit associe.',
                        $createdId,
                        $this->garantieTypeLabel($typeGarantie)
                    );
                $this->notificationService->createNotification(
                    $resolvedUserId,
                    null,
                    $resolvedUserId,
                    'GARANTIE_CREATE',
                    'Garantie enregistree',
                    $notificationMessage
                );
            }

            return $createdId;
        }

        $this->connection->update('garantiecredit', $payload, ['idGarantie' => $id]);

        $this->activityService->log((int) $payload['idUser'], 'GARANTIE_UPDATE', 'Symfony portal', sprintf('Guarantee #%d updated.', $id));
        if ($resolvedUserId !== null) {
            $notificationMessage = $creditId > 0 && $credit
                ? sprintf(
                    'Votre garantie #%d (%s) a ete mise a jour pour le credit #%d (%s).',
                    (int) $id,
                    $this->garantieTypeLabel($typeGarantie),
                    $creditId,
                    (string) ($credit['typeCredit'] ?? 'Credit')
                )
                : sprintf(
                    'Votre garantie #%d (%s) a ete mise a jour sans credit associe.',
                    (int) $id,
                    $this->garantieTypeLabel($typeGarantie)
                );
            $this->notificationService->createNotification(
                $resolvedUserId,
                null,
                $resolvedUserId,
                'GARANTIE_UPDATE',
                'Garantie mise a jour',
                $notificationMessage
            );
        }

        return $id;
    }

    public function deleteGarantie(int $id, ?int $forcedUserId = null): void
    {
        $existing = $this->resolveGarantieForWrite($id, $forcedUserId, true);
        if (!$existing) {
            throw new \RuntimeException('Garantie introuvable.');
        }

        $deletedRows = $this->connection->delete('garantiecredit', ['idGarantie' => $id]);
        if ($deletedRows <= 0) {
            throw new \RuntimeException('Suppression de la garantie impossible.');
        }

        $resolvedUserId = $forcedUserId ?? $this->nullableInt($existing['resolved_user_id'] ?? null);
        if ($resolvedUserId !== null) {
            $creditId = (int) ($existing['idCredit'] ?? 0);
            $message = $creditId > 0
                ? sprintf(
                    'La garantie #%d (%s) liee au credit #%d (%s) a ete supprimee.',
                    $id,
                    (string) ($existing['typeGarantie'] ?? 'Garantie'),
                    $creditId,
                    (string) ($existing['typeCredit'] ?? 'Credit')
                )
                : sprintf(
                    'La garantie #%d (%s) sans credit associe a ete supprimee.',
                    $id,
                    (string) ($existing['typeGarantie'] ?? 'Garantie')
                );
            $this->notificationService->createNotification(
                $resolvedUserId,
                null,
                $resolvedUserId,
                'GARANTIE_DELETE',
                'Garantie supprimee',
                $message
            );
        }

        if ($forcedUserId !== null) {
            $this->activityService->log($forcedUserId, 'GARANTIE_DELETE', 'Symfony portal', sprintf('Guarantee #%d deleted.', $id));
        }
    }

    public function listPartenaires(): array
    {
        $this->ensurePartenaireVilleColumn();

        return $this->connection->fetchAllAssociative('SELECT * FROM partenaire ORDER BY nom ASC');
    }

    public function savePartenaire(array $data, ?int $id = null): void
    {
        $this->ensurePartenaireVilleColumn();

        $payload = [
            'nom' => trim((string) ($data['nom'] ?? '')),
            'categorie' => trim((string) ($data['categorie'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'ville' => trim((string) ($data['ville'] ?? '')),
            'tauxCashback' => (float) ($data['tauxCashback'] ?? 0),
            'tauxCashbackMax' => (float) ($data['tauxCashbackMax'] ?? 0),
            'plafondMensuel' => (float) ($data['plafondMensuel'] ?? 0),
            'conditions' => trim((string) ($data['conditions'] ?? '')),
            'status' => trim((string) ($data['status'] ?? 'Actif')),
            'rating' => max(0, min(5, (float) ($data['rating'] ?? 4))),
        ];

        if ($id === null) {
            $this->connection->insert('partenaire', $payload);

            return;
        }

        $this->connection->update('partenaire', $payload, ['idPartenaire' => $id]);
    }

    public function deletePartenaire(int $id): void
    {
        $this->connection->delete('partenaire', ['idPartenaire' => $id]);
    }

    public function listCashbacks(?int $userId = null): array
    {
        $sql = 'SELECT c.*, CONCAT(COALESCE(u.prenom, \'\'), \' \', COALESCE(u.nom, \'\')) AS user_name
                FROM cashback_entries c
                LEFT JOIN users u ON u.idUser = c.id_user';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE c.id_user = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY c.date_achat DESC, c.id_cashback DESC';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function saveCashback(array $data, ?int $id = null, ?int $forcedUserId = null): void
    {
        $userId = $forcedUserId ?? (int) ($data['id_user'] ?? 0);
        $amount = max(0, (float) ($data['montant_achat'] ?? 0));
        $partnerRating = $this->resolvePartnerRating(
            $this->nullableInt($data['id_partenaire'] ?? null),
            (string) ($data['partenaire_nom'] ?? '')
        );
        $rate = $this->resolveCashbackRate($amount, $partnerRating);
        $cashback = round($amount * ($rate / 100), 2);

        $payload = [
            'id_user' => $userId,
            'id_partenaire' => $this->nullableInt($data['id_partenaire'] ?? null),
            'partenaire_nom' => trim((string) ($data['partenaire_nom'] ?? '')),
            'montant_achat' => $amount,
            'taux_applique' => $rate,
            'montant_cashback' => $cashback,
            'date_achat' => trim((string) ($data['date_achat'] ?? date('Y-m-d'))),
            'date_credit' => trim((string) ($data['date_credit'] ?? '')) ?: null,
            'date_expiration' => trim((string) ($data['date_expiration'] ?? '')) ?: null,
            'statut' => trim((string) ($data['statut'] ?? 'En attente')),
            'transaction_ref' => trim((string) ($data['transaction_ref'] ?? '')),
        ];

        if ($id === null) {
            $this->connection->insert('cashback_entries', $payload);
            $this->notificationService->notifyCashbackSubmitted($payload);
            $this->activityService->log($userId, 'CASHBACK_CREATE', 'Symfony portal', 'Cashback request created.');

            return;
        }

        $criteria = ['id_cashback' => $id];
        if ($forcedUserId !== null) {
            $criteria['id_user'] = $forcedUserId;
        }

        $this->connection->update('cashback_entries', $payload, $criteria);
        $this->activityService->log($userId, 'CASHBACK_UPDATE', 'Symfony portal', sprintf('Cashback #%d updated.', $id));
    }

    public function grantCashbackReward(int $idCashback, float $bonusAmount, string $note = ''): void
    {
        $cashback = $this->connection->fetchAssociative('SELECT * FROM cashback_entries WHERE id_cashback = ?', [$idCashback]);
        if (!$cashback) {
            return;
        }

        $newAmount = round(((float) $cashback['montant_cashback']) + max(0, $bonusAmount), 2);
        $transactionRef = trim((string) ($cashback['transaction_ref'] ?? ''));
        $token = sprintf('ADMIN_REWARD +%.2f', max(0, $bonusAmount));
        if (trim($note) !== '') {
            $token .= ' ('.trim($note).')';
        }
        $transactionRef = $transactionRef !== '' ? $transactionRef.' | '.$token : $token;

        $this->connection->update('cashback_entries', [
            'montant_cashback' => $newAmount,
            'transaction_ref' => $transactionRef,
            'statut' => in_array($cashback['statut'], ['En attente', 'Pending'], true) ? 'Valide' : $cashback['statut'],
            'bonus_decision' => 'Approved',
            'bonus_note' => trim($note),
        ], [
            'id_cashback' => $idCashback,
        ]);

        $updated = $this->connection->fetchAssociative('SELECT * FROM cashback_entries WHERE id_cashback = ?', [$idCashback]);
        if ($updated) {
            $this->notificationService->notifyCashbackRewardGranted($updated, max(0, $bonusAmount), $note);
        }
    }

    public function setCashbackBonusDecision(int $idCashback, bool $approved, string $note = ''): void
    {
        $this->connection->update('cashback_entries', [
            'bonus_decision' => $approved ? 'Approved' : 'Rejected',
            'bonus_note' => trim($note),
        ], [
            'id_cashback' => $idCashback,
        ]);
    }

    public function submitCashbackRating(int $idCashback, int $userId, float $rating, string $comment = ''): void
    {
        $this->connection->update('cashback_entries', [
            'user_rating' => max(0, min(5, $rating)),
            'user_rating_comment' => trim($comment),
            'bonus_decision' => 'Pending',
        ], [
            'id_cashback' => $idCashback,
            'id_user' => $userId,
        ]);
    }

    public function deleteCashback(int $idCashback, ?int $forcedUserId = null): void
    {
        $criteria = ['id_cashback' => $idCashback];
        if ($forcedUserId !== null) {
            $criteria['id_user'] = $forcedUserId;
        }

        $this->connection->delete('cashback_entries', $criteria);
        if ($forcedUserId !== null) {
            $this->activityService->log($forcedUserId, 'CASHBACK_DELETE', 'Symfony portal', sprintf('Cashback #%d deleted.', $idCashback));
        }
    }

    public function listReclamations(?int $userId = null): array
    {
        $sql = 'SELECT r.*,
                       t.idUser AS transaction_user_id,
                       c.idUser AS compte_user_id,
                       COALESCE(r.idUser, t.idUser, c.idUser) AS resolved_user_id,
                       CONCAT(COALESCE(u.prenom, \'\'), \' \', COALESCE(u.nom, \'\')) AS user_name
                FROM reclamation r
                LEFT JOIN transactions t ON t.idTransaction = r.idTransaction
                LEFT JOIN compte c ON c.idCompte = t.idCompte
                LEFT JOIN users u ON u.idUser = COALESCE(r.idUser, t.idUser, c.idUser)';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE (r.idUser = ? OR t.idUser = ? OR c.idUser = ?)';
            $params[] = $userId;
            $params[] = $userId;
            $params[] = $userId;
        }
        $sql .= ' ORDER BY r.idReclamation DESC';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function saveReclamation(array $data, ?int $id = null, ?int $forcedUserId = null): void
    {
        $existing = null;
        if ($id !== null) {
            $existing = $this->connection->fetchAssociative(
                'SELECT * FROM reclamation WHERE idReclamation = ? LIMIT 1',
                [$id]
            );
            if (!$existing) {
                throw new \RuntimeException('Reclamation introuvable.');
            }
        }

        $transactionId = $this->nullableInt($data['idTransaction'] ?? null) ?? $this->nullableInt($existing['idTransaction'] ?? null);
        $resolvedUserId = $forcedUserId ?? $this->nullableInt($data['idUser'] ?? null) ?? $this->nullableInt($existing['idUser'] ?? null);

        if ($transactionId !== null && $resolvedUserId === null) {
            $transactionOwner = $this->connection->fetchAssociative(
                'SELECT COALESCE(t.idUser, c.idUser) AS resolved_user_id
                 FROM transactions t
                 LEFT JOIN compte c ON c.idCompte = t.idCompte
                 WHERE t.idTransaction = ? LIMIT 1',
                [$transactionId]
            );
            $resolvedUserId = $this->nullableInt($transactionOwner['resolved_user_id'] ?? null);
        }

        if ($resolvedUserId === null) {
            throw new \InvalidArgumentException('Utilisateur introuvable pour cette reclamation.');
        }

        $description = trim((string) ($data['description'] ?? ''));
        $inappropriate = $this->containsBadWord($description);
        
        $payload = [
            'idUser' => $resolvedUserId,
            'idTransaction' => $transactionId,
            'dateReclamation' => trim((string) ($data['dateReclamation'] ?? date('Y-m-d'))),
            'typeReclamation' => trim((string) ($data['typeReclamation'] ?? '')),
            'description' => $description,
            'status' => $inappropriate ? 'Signalée' : trim((string) ($data['status'] ?? 'En attente')),
            'is_inappropriate' => $inappropriate ? 1 : 0,
            'is_blurred' => (int) ((string) ($data['is_blurred'] ?? '0') === '1'),
        ];

        if ($id === null) {
            $this->connection->insert('reclamation', $payload);
            $this->activityService->log((int) $payload['idUser'], 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.');

            return;
        }

        $criteria = ['idReclamation' => $id];
        if ($forcedUserId !== null) {
            $criteria['idUser'] = $forcedUserId;
        }

        $this->connection->update('reclamation', $payload, $criteria);
        $this->activityService->log((int) $payload['idUser'], 'RECLAMATION_UPDATE', 'Symfony portal', sprintf('Complaint #%d updated.', $id));
    }

    public function toggleReclamationBlur(int $id, bool $blurred): void
    {
        $this->connection->update('reclamation', [
            'is_blurred' => $blurred ? 1 : 0,
        ], [
            'idReclamation' => $id,
        ]);
    }

    public function deleteReclamation(int $id, ?int $forcedUserId = null): void
    {
        $criteria = ['idReclamation' => $id];
        if ($forcedUserId !== null) {
            $criteria['idUser'] = $forcedUserId;
        }

        $this->connection->delete('reclamation', $criteria);
        if ($forcedUserId !== null) {
            $this->activityService->log($forcedUserId, 'RECLAMATION_DELETE', 'Symfony portal', sprintf('Complaint #%d deleted.', $id));
        }
    }

    public function listVaults(?int $userId = null): array
    {
        $sql = 'SELECT v.*,
                       c.numeroCompte,
                       c.idUser AS compte_user_id,
                       COALESCE(v.idUser, c.idUser) AS resolved_user_id
                FROM coffrevirtuel v
                LEFT JOIN compte c ON c.idCompte = v.idCompte';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE (v.idUser = ? OR c.idUser = ?)';
            $params[] = $userId;
            $params[] = $userId;
        }
        $sql .= ' ORDER BY v.idCoffre DESC';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function saveVault(array $data, ?int $id = null, ?int $forcedUserId = null): void
    {
        $payload = [
            'nom' => trim((string) ($data['nom'] ?? '')),
            'objectifMontant' => (float) ($data['objectifMontant'] ?? 0),
            'montantActuel' => (float) ($data['montantActuel'] ?? 0),
            'dateCreation' => trim((string) ($data['dateCreation'] ?? date('Y-m-d'))),
            'dateObjectifs' => trim((string) ($data['dateObjectifs'] ?? '')) ?: null,
            'status' => trim((string) ($data['status'] ?? 'Actif')),
            'estVerrouille' => (int) ((string) ($data['estVerrouille'] ?? '1') === '1'),
            'idCompte' => $this->nullableInt($data['idCompte'] ?? null),
            'idUser' => $forcedUserId ?? $this->nullableInt($data['idUser'] ?? null),
        ];

        if ($id === null) {
            $this->connection->insert('coffrevirtuel', $payload);
            if ($payload['idUser'] !== null) {
                $this->activityService->log((int) $payload['idUser'], 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.');
            }

            return;
        }

        $criteria = ['idCoffre' => $id];
        if ($forcedUserId !== null) {
            $criteria['idUser'] = $forcedUserId;
        }

        $this->connection->update('coffrevirtuel', $payload, $criteria);
        if ($payload['idUser'] !== null) {
            $this->activityService->log((int) $payload['idUser'], 'VAULT_UPDATE', 'Symfony portal', sprintf('Virtual vault #%d updated.', $id));
        }
    }

    public function deleteVault(int $id, ?int $forcedUserId = null): void
    {
        $criteria = ['idCoffre' => $id];
        if ($forcedUserId !== null) {
            $criteria['idUser'] = $forcedUserId;
        }

        $this->connection->delete('coffrevirtuel', $criteria);
        if ($forcedUserId !== null) {
            $this->activityService->log($forcedUserId, 'VAULT_DELETE', 'Symfony portal', sprintf('Virtual vault #%d deleted.', $id));
        }
    }

    public function transferVaultAmountToAccount(int $userId, int $vaultId, int $accountId): array
    {
        $vault = $this->resolveVaultForUser($vaultId, $userId);
        $account = $this->connection->fetchAssociative(
            'SELECT * FROM compte WHERE idCompte = ? AND idUser = ? LIMIT 1',
            [$accountId, $userId]
        );

        if (!$account) {
            throw new \RuntimeException('Le compte bancaire selectionne est introuvable.');
        }

        $amount = round((float) ($vault['montantActuel'] ?? 0.0), 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Ce coffre ne contient aucun montant a transferer.');
        }

        $newBalance = round(((float) ($account['solde'] ?? 0.0)) + $amount, 2);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $this->connection->transactional(function () use ($userId, $vaultId, $vault, $accountId, $amount, $newBalance, $today): void {
            $this->connection->update('compte', [
                'solde' => $newBalance,
            ], [
                'idCompte' => $accountId,
                'idUser' => $userId,
            ]);

            $vaultCriteria = ['idCoffre' => $vaultId];
            if ((int) ($vault['idUser'] ?? 0) > 0) {
                $vaultCriteria['idUser'] = $userId;
            }

            $this->connection->update('coffrevirtuel', [
                'montantActuel' => 0,
                'estVerrouille' => 0,
            ], $vaultCriteria);

            $this->connection->insert('transactions', [
                'idCompte' => $accountId,
                'idUser' => $userId,
                'categorie' => 'Epargne',
                'dateTransaction' => $today,
                'montant' => $this->security->encryptAmount($amount),
                'typeTransaction' => 'CREDIT',
                'soldeApres' => $newBalance,
                'description' => sprintf('Transfert depuis le coffre objectif #%d', $vaultId),
                'montantPaye' => $this->security->encryptAmount($amount),
            ]);
        });

        $vaultName = trim((string) ($vault['nom'] ?? '')) ?: '#'.$vaultId;
        $accountNumber = trim((string) ($account['numeroCompte'] ?? '')) ?: '#'.$accountId;

        $this->notificationService->createNotification(
            $userId,
            null,
            $userId,
            'VAULT_GOAL_TRANSFER',
            'Objectif transfere',
            sprintf(
                '%.2f DT du coffre %s ont ete transferes vers le compte %s.',
                $amount,
                $vaultName,
                $accountNumber
            )
        );
        $this->activityService->log(
            $userId,
            'VAULT_GOAL_TRANSFER',
            'Symfony portal',
            sprintf('Transferred %.2f DT from vault #%d to account #%d.', $amount, $vaultId, $accountId)
        );

        return [
            'amount' => $amount,
            'vault_id' => $vaultId,
            'vault_name' => $vaultName,
            'account_id' => $accountId,
            'account_number' => $accountNumber,
            'new_balance' => $newBalance,
        ];
    }

    public function extendVaultGoalDate(int $userId, int $vaultId, string $extensionCode): array
    {
        $allowedExtensions = [
            'P3M' => ['interval' => new \DateInterval('P3M'), 'label' => '+3 mois'],
            'P6M' => ['interval' => new \DateInterval('P6M'), 'label' => '+6 mois'],
            'P1Y' => ['interval' => new \DateInterval('P1Y'), 'label' => '+1 an'],
        ];

        if (!isset($allowedExtensions[$extensionCode])) {
            throw new \InvalidArgumentException('La duree de prolongation est invalide.');
        }

        $vault = $this->resolveVaultForUser($vaultId, $userId);
        $baseDateRaw = trim((string) ($vault['dateObjectifs'] ?? ''));
        $baseDate = $baseDateRaw !== '' ? new \DateTimeImmutable($baseDateRaw) : new \DateTimeImmutable('today');
        $newDate = $baseDate->add($allowedExtensions[$extensionCode]['interval']);

        $vaultCriteria = ['idCoffre' => $vaultId];
        if ((int) ($vault['idUser'] ?? 0) > 0) {
            $vaultCriteria['idUser'] = $userId;
        }

        $this->connection->update('coffrevirtuel', [
            'dateObjectifs' => $newDate->format('Y-m-d'),
        ], $vaultCriteria);

        $vaultName = trim((string) ($vault['nom'] ?? '')) ?: '#'.$vaultId;
        $label = $allowedExtensions[$extensionCode]['label'];

        $this->notificationService->createNotification(
            $userId,
            null,
            $userId,
            'VAULT_GOAL_EXTEND',
            'Objectif prolonge',
            sprintf(
                'La date objectif du coffre %s a ete prolongee de %s jusqu au %s.',
                $vaultName,
                $label,
                $newDate->format('Y-m-d')
            )
        );
        $this->activityService->log(
            $userId,
            'VAULT_GOAL_EXTEND',
            'Symfony portal',
            sprintf('Extended vault #%d by %s until %s.', $vaultId, $extensionCode, $newDate->format('Y-m-d'))
        );

        return [
            'vault_id' => $vaultId,
            'vault_name' => $vaultName,
            'extension_code' => $extensionCode,
            'extension_label' => $label,
            'previous_date' => $baseDate->format('Y-m-d'),
            'new_date' => $newDate->format('Y-m-d'),
        ];
    }

    private function resolvePartnerRating(?int $partnerId, string $partnerName): float
    {
        if ($partnerId !== null) {
            $rating = $this->connection->fetchOne('SELECT rating FROM partenaire WHERE idPartenaire = ?', [$partnerId]);
            if ($rating !== false) {
                return (float) $rating;
            }
        }

        if (trim($partnerName) === '') {
            return 0.0;
        }

        $rating = $this->connection->fetchOne(
            'SELECT rating FROM partenaire WHERE LOWER(TRIM(nom)) = LOWER(TRIM(?)) LIMIT 1',
            [$partnerName]
        );

        return $rating !== false ? (float) $rating : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveVaultForUser(int $vaultId, int $userId): array
    {
        $vault = $this->connection->fetchAssociative(
            'SELECT v.*, c.idUser AS compte_user_id, COALESCE(v.idUser, c.idUser) AS resolved_user_id
             FROM coffrevirtuel v
             LEFT JOIN compte c ON c.idCompte = v.idCompte
             WHERE v.idCoffre = ?
             LIMIT 1',
            [$vaultId]
        );

        if (!$vault || (int) ($vault['resolved_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('Le coffre selectionne est introuvable.');
        }

        return $vault;
    }

    private function resolveCashbackRate(float $amount, float $partnerRating): float
    {
        $baseRate = 1.0;
        if ($amount >= 50 && $amount <= 200) {
            $baseRate = 2.0;
        } elseif ($amount > 200) {
            $baseRate = 3.0;
        }

        if ($partnerRating > 4.0) {
            $baseRate += 1.0;
        }

        return $baseRate;
    }

    private function normalizeIsoDateNotFuture(string $rawDate, string $errorMessage): string
    {
        $normalized = trim($rawDate);
        if ($normalized === '') {
            return date('Y-m-d');
        }

        try {
            $date = new \DateTimeImmutable($normalized);
        } catch (\Throwable) {
            throw new \InvalidArgumentException($errorMessage);
        }

        $today = new \DateTimeImmutable('today');
        if ($date > $today) {
            throw new \InvalidArgumentException($errorMessage);
        }

        return $date->format('Y-m-d');
    }

    private function isGarantieAddressAlreadyUsed(int $userId, string $adresseBien, ?int $excludeGarantieId = null, ?int $currentCreditId = null): bool
    {
        $address = trim($adresseBien);
        if ($userId <= 0 || $address === '') {
            return false;
        }

        $sql = 'SELECT COUNT(*) AS address_count
                FROM garantiecredit g
                JOIN credit cr ON cr.idCredit = g.idCredit
                WHERE (g.idUser = ? OR cr.idUser = ?)
                  AND LOWER(TRIM(g.adresseBien)) = LOWER(TRIM(?))
                  AND LOWER(TRIM(g.statut)) NOT IN (\'rejete\', \'rejetee\', \'refuse\', \'annule\', \'rejected\')
                  AND LOWER(TRIM(cr.statut)) IN (\'en cours\', \'accepte\', \'en attente\', \'pending\', \'a traiter\')';
        $params = [$userId, $userId, $address];

        if ($excludeGarantieId !== null && $excludeGarantieId > 0) {
            $sql .= ' AND g.idGarantie <> ?';
            $params[] = $excludeGarantieId;
        }
        if ($currentCreditId !== null && $currentCreditId > 0) {
            $sql .= ' AND g.idCredit <> ?';
            $params[] = $currentCreditId;
        }

        $count = $this->connection->fetchOne($sql, $params);

        return ((int) $count) > 0;
    }

    private function buildTypeDistribution(array $rows): array
    {
        $total = 0;
        foreach ($rows as $row) {
            $total += max(0, (int) ($row['total'] ?? 0));
        }

        if ($total <= 0) {
            return [];
        }

        $colors = ['#0A2540', '#14B8A6', '#EAB308', '#F97316', '#8B5CF6', '#EF4444', '#2563EB', '#059669'];
        $distribution = [];
        $index = 0;

        foreach ($rows as $row) {
            $count = max(0, (int) ($row['total'] ?? 0));
            if ($count <= 0) {
                continue;
            }

            $distribution[] = [
                'label' => (string) ($row['label'] ?? 'Inconnu'),
                'count' => $count,
                'percent' => round(($count / $total) * 100, 1),
                'color' => $colors[$index % count($colors)],
            ];
            ++$index;
        }

        return $distribution;
    }

    private function calculateMonthlyPayment(float $amount, float $annualRate, int $months): float
    {
        if ($months <= 0) {
            return 0.0;
        }

        $monthlyRate = $annualRate / 100 / 12;
        if ($monthlyRate <= 0) {
            return round($amount / $months, 2);
        }

        $payment = ($amount * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$months));

        return round($payment, 2);
    }

    private function calculateCreditRiskScore(array $credit): float
    {
        $userId = (int) ($credit['idUser'] ?? 0);
        $salary = (float) ($credit['salaire'] ?? 0);
        $mensualite = (float) ($credit['mensualite'] ?? 0);
        if ($salary <= 0) {
            $salary = 2000.0;
        }

        $otherMonthly = (float) $this->connection->fetchOne(
            "SELECT COALESCE(SUM(mensualite), 0) FROM credit WHERE idUser = ? AND idCredit <> ? AND statut IN ('En cours', 'Accepte', 'En attente')",
            [$userId, (int) ($credit['idCredit'] ?? 0)]
        );
        $debtRatio = ($otherMonthly + $mensualite) / max($salary, 1);

        $debtScore = 0;
        if ($debtRatio <= 0.33) {
            $debtScore = 35;
        } elseif ($debtRatio <= 0.40) {
            $debtScore = 25;
        } elseif ($debtRatio <= 0.50) {
            $debtScore = 12;
        }

        $history = $this->connection->fetchAllAssociative(
            'SELECT statut FROM credit WHERE idUser = ? AND idCredit <> ?',
            [$userId, (int) ($credit['idCredit'] ?? 0)]
        );
        $historyScore = 15;
        if ($history !== []) {
            $approved = 0;
            $refused = 0;
            foreach ($history as $row) {
                $status = strtolower((string) ($row['statut'] ?? ''));
                if (in_array($status, ['accepte', 'rembourse', 'en cours'], true)) {
                    ++$approved;
                } elseif ($status === 'refuse') {
                    ++$refused;
                }
            }

            $historyScore = 10 + ($approved * 5) - ($refused * 5);
            $historyScore = max(0, min(25, $historyScore));
        }

        $guaranteeCoverage = (float) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(valeurRetenue), 0) FROM garantiecredit WHERE idCredit = ?',
            [(int) ($credit['idCredit'] ?? 0)]
        );
        $amount = max(1.0, (float) ($credit['montantDemande'] ?? 1));
        $coverageRatio = $guaranteeCoverage / $amount;
        $guaranteeScore = 0;
        if ($coverageRatio >= 1.0) {
            $guaranteeScore = 25;
        } elseif ($coverageRatio >= 0.7) {
            $guaranteeScore = 18;
        } elseif ($coverageRatio >= 0.4) {
            $guaranteeScore = 10;
        }

        $profileScore = 0;
        $contract = strtolower(trim((string) ($credit['typeContrat'] ?? '')));
        $seniority = (int) ($credit['ancienneteAnnees'] ?? 0);
        if (in_array($contract, ['cdi', 'fonctionnaire', 'permanent'], true)) {
            $profileScore += 8;
        } elseif ($contract !== '') {
            $profileScore += 4;
        }
        if ($seniority >= 5) {
            $profileScore += 7;
        } elseif ($seniority >= 2) {
            $profileScore += 4;
        } elseif ($seniority >= 1) {
            $profileScore += 2;
        }

        return round(max(0, min(100, $debtScore + $historyScore + $guaranteeScore + $profileScore)), 1);
    }

    private function isTransactionAnomaly(int $userId, float $amount): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $rows = $this->connection->fetchAllAssociative('SELECT montant FROM transactions WHERE idUser = ?', [$userId]);
        $sum = 0.0;
        $count = 0;
        foreach ($rows as $row) {
            $value = $this->security->decryptAmount($row['montant'] ?? null);
            if ($value !== null && $value > 0) {
                $sum += $value;
                ++$count;
            }
        }

        if ($count === 0) {
            return $amount > 5000;
        }

        return $amount > (($sum / $count) * 2.5);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function userExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE idUser = ?',
            [$userId]
        ) > 0;
    }

    private function assertValidUserName(string $value, string $label): void
    {
        $normalized = trim($value);
        if ($normalized === '' || mb_strlen($normalized) < 2 || mb_strlen($normalized) > 80) {
            throw new \InvalidArgumentException($label.' must contain between 2 and 80 characters.');
        }

        if (preg_match("/^[\\p{L}][\\p{L}'\\-\\s]*$/u", $normalized) !== 1) {
            throw new \InvalidArgumentException($label.' contains invalid characters.');
        }
    }

    private function assertValidUserEmail(string $email): void
    {
        if (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email format is invalid.');
        }
    }

    private function isValidPhone(string $phone): bool
    {
        $normalized = trim($phone);
        if ($normalized === '') {
            return false;
        }

        if (!preg_match('/^\+?[0-9][0-9\s\-]{6,19}$/', $normalized)) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $normalized) ?? '';

        return strlen($digits) >= 8 && strlen($digits) <= 15;
    }

    private function assertStrongPassword(string $password): void
    {
        if (
            strlen($password) < 8
            || preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/\d/', $password) !== 1
        ) {
            throw new \InvalidArgumentException('Password must contain at least 8 chars, upper, lower and number.');
        }
    }

    private function assertValidUserRole(string $role): void
    {
        if (!in_array($role, self::ALLOWED_USER_ROLES, true)) {
            throw new \InvalidArgumentException('Invalid role selected.');
        }
    }

    private function assertValidUserStatus(string $status): void
    {
        if (!in_array($status, self::ALLOWED_USER_STATUS, true)) {
            throw new \InvalidArgumentException('Invalid status selected.');
        }
    }

    private function emailExistsForAnotherUser(string $email, ?int $excludeUserId): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(?)';
        $params = [strtolower(trim($email))];

        if ($excludeUserId !== null) {
            $sql .= ' AND idUser <> ?';
            $params[] = $excludeUserId;
        }

        return (int) $this->connection->fetchOne($sql, $params) > 0;
    }

    private function resolveStatusForSave(
        string $table,
        string $idColumn,
        array $data,
        ?int $id = null,
        ?int $forcedUserId = null,
        string $defaultStatus = 'En attente',
    ): string {
        if (array_key_exists('statut', $data)) {
            $submittedStatus = trim((string) $data['statut']);
            if ($submittedStatus !== '') {
                return $submittedStatus;
            }
        }

        if ($id === null) {
            return $defaultStatus;
        }

        // Whitelist des tables autorisées — jamais d'entrée utilisateur ici
        $allowedTables = [
            'credit'        => 'idCredit',
            'garantiecredit' => 'idGarantie',
        ];

        if (!isset($allowedTables[$table]) || $allowedTables[$table] !== $idColumn) {
            return $defaultStatus;
        }

        $sql = sprintf('SELECT statut FROM %s WHERE %s = ?', $table, $idColumn);
        $params = [$id];
        if ($forcedUserId !== null) {
            $sql .= ' AND idUser = ?';
            $params[] = $forcedUserId;
        }
        $sql .= ' LIMIT 1';

        $existingStatus = trim((string) ($this->connection->fetchOne($sql, $params) ?? ''));

        return $existingStatus !== '' ? $existingStatus : $defaultStatus;
    }

    private function resolveCreditForWrite(?int $id, ?int $forcedUserId = null, bool $throwOnForbidden = false): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        $existing = $this->connection->fetchAssociative(
            'SELECT c.idCredit,
                    c.typeCredit,
                    c.montantDemande,
                    COALESCE(c.idUser, cp.idUser) AS resolved_user_id
             FROM credit c
             LEFT JOIN compte cp ON cp.idCompte = c.idCompte
             WHERE c.idCredit = ?
             LIMIT 1',
            [$id]
        );
        if (!$existing) {
            return null;
        }

        if ($forcedUserId !== null && $this->nullableInt($existing['resolved_user_id'] ?? null) !== $forcedUserId) {
            if ($throwOnForbidden) {
                throw new \RuntimeException('Credit introuvable.');
            }

            throw new \RuntimeException('Credit introuvable pour mise a jour.');
        }

        return $existing;
    }

    private function resolveGarantieForWrite(?int $id, ?int $forcedUserId = null, bool $throwOnForbidden = false): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        $existing = $this->connection->fetchAssociative(
            'SELECT g.idGarantie,
                    g.typeGarantie,
                    g.idCredit,
                    c.typeCredit,
                    COALESCE(g.idUser, c.idUser, cp.idUser) AS resolved_user_id
             FROM garantiecredit g
             LEFT JOIN credit c ON c.idCredit = g.idCredit
             LEFT JOIN compte cp ON cp.idCompte = c.idCompte
             WHERE g.idGarantie = ?
             LIMIT 1',
            [$id]
        );
        if (!$existing) {
            return null;
        }

        if ($forcedUserId !== null && $this->nullableInt($existing['resolved_user_id'] ?? null) !== $forcedUserId) {
            if ($throwOnForbidden) {
                throw new \RuntimeException('Garantie introuvable.');
            }

            throw new \RuntimeException('Garantie introuvable pour mise a jour.');
        }

        return $existing;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function filterPayloadForExistingColumns(string $table, array $payload): array
    {
        $columns = $this->getTableColumns($table);
        if ($columns === []) {
            return $payload;
        }

        $filtered = [];
        foreach ($payload as $column => $value) {
            if (in_array($column, $columns, true)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    /**
     * @return array<int, string>
     */
    private function getTableColumns(string $table): array
    {
        if (isset($this->tableColumnsCache[$table])) {
            return $this->tableColumnsCache[$table];
        }

        // Validation : nom de table alphanumérique uniquement (jamais d'entrée utilisateur)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            $this->tableColumnsCache[$table] = [];
            return [];
        }

        try {
            $rows = $this->connection->fetchAllAssociative('SHOW COLUMNS FROM `' . $table . '`');
        } catch (\Throwable) {
            $this->tableColumnsCache[$table] = [];

            return [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $field = (string) ($row['Field'] ?? '');
            if ($field !== '') {
                $columns[] = $field;
            }
        }
        $this->tableColumnsCache[$table] = $columns;

        return $columns;
    }

    private function ensurePartenaireVilleColumn(): void
    {
        $columns = $this->getTableColumns('partenaire');
        if (in_array('ville', $columns, true)) {
            return;
        }

        try {
            $this->connection->executeStatement(
                'ALTER TABLE partenaire ADD ville VARCHAR(120) DEFAULT NULL AFTER description'
            );
            unset($this->tableColumnsCache['partenaire']);
        } catch (\Throwable) {
            // Ignore schema update errors when the column already exists or cannot be added automatically.
        }
    }

    private function containsBadWord(string $description): bool
    {
        $text = strtolower($description);
        foreach (self::BAD_WORDS as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }

    private function buildMonthlyTransactionsSeries(array $transactions): array
    {
        $labels = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aout', 'Sep', 'Oct', 'Nov', 'Dec'];
        $entries = array_fill(0, 12, 0.0);
        $exits = array_fill(0, 12, 0.0);

        foreach ($transactions as $transaction) {
            $monthIndex = $this->resolveMonthIndex((string) ($transaction['dateTransaction'] ?? ''));
            if ($monthIndex === null) {
                continue;
            }

            $amount = abs((float) ($transaction['montant_value'] ?? 0.0));
            if ($amount <= 0) {
                continue;
            }

            // Utiliser resolved_type si disponible (transactions enrichies par listTransactions())
            // sinon recalculer selon les règles métier
            $resolvedType = strtoupper(trim((string) ($transaction['resolved_type'] ?? '')));
            if ($resolvedType !== 'CREDIT' && $resolvedType !== 'DEBIT') {
                $type = strtolower(trim((string) ($transaction['typeTransaction'] ?? '')));
                if (str_contains($type, 'depot') || str_contains($type, 'versement')) {
                    $resolvedType = 'CREDIT';
                } elseif (str_contains($type, 'paiement') || str_contains($type, 'retrait') || str_contains($type, 'paimenet')) {
                    $resolvedType = 'DEBIT';
                } elseif (str_contains($type, 'virement')) {
                    $dest = (int) ($transaction['idCompteDestinataire'] ?? 0);
                    $src  = (int) ($transaction['idCompte'] ?? 0);
                    // CREDIT si idCompteDestinataire == compte courant (réception)
                    // CREDIT si idCompteDestinataire NULL/0 (inconnu → CREDIT)
                    // DEBIT  si idCompteDestinataire est un compte différent (envoi)
                    $resolvedType = ($dest === 0 || $dest === $src) ? 'CREDIT' : 'DEBIT';
                }
            }

            if ($resolvedType === 'CREDIT') {
                $entries[$monthIndex] += $amount;
            } else {
                $exits[$monthIndex] += $amount;
            }
        }

        return [
            'labels' => $labels,
            'entries' => array_map(static fn (float $value): float => round($value, 2), $entries),
            'exits' => array_map(static fn (float $value): float => round($value, 2), $exits),
        ];
    }

    private function buildAccountTypeDistribution(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT COALESCE(NULLIF(TRIM(typeCompte), \'\'), \'Inconnu\') AS label, COUNT(*) AS total
             FROM compte
             GROUP BY COALESCE(NULLIF(TRIM(typeCompte), \'\'), \'Inconnu\')
             ORDER BY total DESC'
        );

        $grandTotal = 0;
        foreach ($rows as $row) {
            $grandTotal += max(0, (int) ($row['total'] ?? 0));
        }

        if ($grandTotal <= 0) {
            return [];
        }

        $colors = ['#11b7aa', '#0b2b4b', '#f1c232', '#7c8da1', '#8b5cf6', '#f97316'];
        $distribution = [];
        $index = 0;

        foreach ($rows as $row) {
            $count = max(0, (int) ($row['total'] ?? 0));
            if ($count <= 0) {
                continue;
            }

            $distribution[] = [
                'label' => (string) ($row['label'] ?? 'Inconnu'),
                'count' => $count,
                'percent' => round(($count / $grandTotal) * 100, 1),
                'color' => $colors[$index % count($colors)],
            ];
            ++$index;
        }

        return $distribution;
    }

    private function garantieTypeLabel(string $storedValue): string
    {
        return Garantiecredit::typeLabel($storedValue);
    }

    private function normalizeDocumentStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'valide' => 'valide',
            'refuse', 'rejete', 'rejetee', 'rejeté', 'rejetée' => 'refuse',
            default => 'en_attente',
        };
    }

    private function documentStatusFromVerificationStatus(string $verificationStatus): string
    {
        $normalized = strtolower(trim($verificationStatus));

        return match ($normalized) {
            'valide' => 'valide',
            'rejete', 'refuse' => 'refuse',
            default => 'en_attente',
        };
    }

    private function verificationStatusFromDocumentStatus(string $documentStatus): string
    {
        return $this->normalizeDocumentStatus($documentStatus) === 'refuse'
            ? 'rejete'
            : $this->normalizeDocumentStatus($documentStatus);
    }

    private function normalizeGarantieTypeForStorage(string $value): string
    {
        return Garantiecredit::normalizeTypeValue($value) ?? '';
    }

    private function resolveMonthIndex(string $dateRaw): ?int
    {
        $dateRaw = trim($dateRaw);
        if ($dateRaw === '') {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($dateRaw);
        } catch (\Throwable) {
            return null;
        }

        $month = (int) $date->format('n');
        if ($month < 1 || $month > 12) {
            return null;
        }

        return $month - 1;
    }
}
