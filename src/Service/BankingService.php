<?php

namespace App\Service;

use App\Form\CreditType;
use App\Form\GarantiecreditType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class BankingService
{
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

    public function __construct(
        private readonly Connection $connection,
        private readonly LegacyBankingSecurity $security,
        private readonly NotificationService $notificationService,
        private readonly ActivityService $activityService,
        private readonly FormFactoryInterface $formFactory,
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

    public function saveAccount(array $data, ?int $id = null, ?int $forcedUserId = null): void
    {
        $userId = $forcedUserId ?? $this->nullableInt($data['idUser'] ?? null);
        $payload = [
            'numeroCompte' => trim((string) ($data['numeroCompte'] ?? '')),
            'solde' => (float) ($data['solde'] ?? 0),
            'dateOuverture' => trim((string) ($data['dateOuverture'] ?? date('Y-m-d'))),
            'statutCompte' => trim((string) ($data['statutCompte'] ?? 'Actif')),
            'plafondRetrait' => (float) ($data['plafondRetrait'] ?? 0),
            'plafondVirement' => (float) ($data['plafondVirement'] ?? 0),
            'typeCompte' => trim((string) ($data['typeCompte'] ?? 'Courant')),
            'idUser' => $userId,
        ];

        if ($id === null) {
            $this->connection->insert('compte', $payload);
            if ($userId !== null) {
                $this->activityService->log($userId, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.');
            }

            return;
        }

        $criteria = ['idCompte' => $id];
        if ($forcedUserId !== null) {
            $criteria['idUser'] = $forcedUserId;
        }

        $this->connection->update('compte', $payload, $criteria);
        if ($userId !== null) {
            $this->activityService->log($userId, 'ACCOUNT_UPDATE', 'Symfony portal', sprintf('Bank account #%d updated.', $id));
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
                       COALESCE(t.idUser, c.idUser) AS resolved_user_id,
                       CONCAT(COALESCE(u.prenom, \'\'), \' \', COALESCE(u.nom, \'\')) AS user_name
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
        }

        return $rows;
    }

    public function saveTransaction(array $data, ?int $id = null, ?int $forcedUserId = null): void
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
        $paidAmount = (float) ($data['montantPaye'] ?? 0);
        $payload = [
            'idCompte' => $accountId,
            'idUser' => $userId,
            'categorie' => trim((string) ($data['categorie'] ?? '')),
            'dateTransaction' => trim((string) ($data['dateTransaction'] ?? date('Y-m-d'))),
            'montant' => $this->security->encryptAmount($amount),
            'typeTransaction' => trim((string) ($data['typeTransaction'] ?? 'Credit')),
            'soldeApres' => ($data['soldeApres'] ?? '') !== '' ? (float) $data['soldeApres'] : null,
            'description' => trim((string) ($data['description'] ?? '')),
            'montantPaye' => $this->security->encryptAmount($paidAmount),
        ];

        if ($id === null) {
            $this->connection->insert('transactions', $payload);
            $this->activityService->log($userId, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.');

            return;
        }

        $criteria = ['idTransaction' => $id];
        if ($forcedUserId !== null) {
            $criteria['idUser'] = $forcedUserId;
        }

        $this->connection->update('transactions', $payload, $criteria);
        $this->activityService->log($userId, 'TRANSACTION_UPDATE', 'Symfony portal', sprintf('Transaction #%d updated.', $id));
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

        $validatedData = $this->validateCrudInput(
            CreditType::class,
            array_replace($data, ['idUser' => $forcedUserId ?? ($data['idUser'] ?? null)]),
            'Donnees du credit invalides.'
        );

        $accountId = (int) ($validatedData['idCompte'] ?? 0);

        $account = $this->connection->fetchAssociative(
            'SELECT idCompte, idUser FROM compte WHERE idCompte = ? LIMIT 1',
            [$accountId]
        );
        if (!$account) {
            throw new \RuntimeException('Compte introuvable.');
        }

        $accountOwnerId = $this->nullableInt($account['idUser'] ?? null);
        $requestedUserId = $forcedUserId ?? $this->nullableInt($validatedData['idUser'] ?? null);
        if ($accountOwnerId !== null && $requestedUserId !== null && $requestedUserId !== $accountOwnerId) {
            throw new \InvalidArgumentException('Le compte selectionne n\'appartient pas a l\'utilisateur choisi.');
        }

        $effectiveUserId = $requestedUserId ?? $accountOwnerId;
        if ($effectiveUserId === null) {
            throw new \InvalidArgumentException('Utilisateur requis pour enregistrer un credit.');
        }

        $typeCredit = trim((string) ($validatedData['typeCredit'] ?? ''));
        $amount = (float) ($validatedData['montantDemande'] ?? 0);
        $rate = max(0, (float) ($validatedData['tauxInteret'] ?? 0));
        $duration = (int) ($validatedData['duree'] ?? 0);
        $autoFunding = $validatedData['autofinancement'] !== null ? (float) $validatedData['autofinancement'] : null;
        if ($autoFunding !== null && $autoFunding < 0) {
            throw new \InvalidArgumentException('Autofinancement invalide.');
        }

        $monthlyPayment = $validatedData['mensualite'] !== null
            ? (float) $validatedData['mensualite']
            : $this->calculateMonthlyPayment($amount, $rate, $duration);
        if ($monthlyPayment <= 0) {
            throw new \InvalidArgumentException('Mensualite invalide.');
        }

        $approvedAmount = $validatedData['montantAccorde'] !== null
            ? (float) $validatedData['montantAccorde']
            : $amount;
        if ($approvedAmount < 0) {
            throw new \InvalidArgumentException('Montant accorde invalide.');
        }

        $dateDemande = $this->normalizeIsoDateNotFuture((string) ($validatedData['dateDemande'] ?? ''), 'Date de demande invalide.');

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
            'salaire' => (float) ($validatedData['salaire'] ?? 0),
            'typeContrat' => trim((string) ($validatedData['typeContrat'] ?? '')),
            'ancienneteAnnees' => max(0, (int) ($validatedData['ancienneteAnnees'] ?? 0)),
        ];

        if ($id === null) {
            $this->connection->insert('credit', $payload);
            $createdId = (int) $this->connection->lastInsertId();
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

    public function saveGarantie(array $data, ?int $id = null, ?int $forcedUserId = null): void
    {
        $existingGarantie = $this->resolveGarantieForWrite($id, $forcedUserId);
        if ($existingGarantie === null) {
            $id = null;
        }

        $validatedData = $this->validateCrudInput(
            GarantiecreditType::class,
            array_replace($data, ['idUser' => $forcedUserId ?? ($data['idUser'] ?? null)]),
            'Donnees de la garantie invalides.'
        );

        $creditId = (int) ($validatedData['idCredit'] ?? 0);

        $credit = $this->connection->fetchAssociative(
            'SELECT idCredit, idUser, typeCredit FROM credit WHERE idCredit = ? LIMIT 1',
            [$creditId]
        );
        if (!$credit) {
            throw new \RuntimeException('Credit associe introuvable.');
        }

        $resolvedUserId = $forcedUserId ?? $this->nullableInt($validatedData['idUser'] ?? null) ?? $this->nullableInt($credit['idUser'] ?? null);
        $typeGarantie = trim((string) ($validatedData['typeGarantie'] ?? ''));
        $estimated = (float) ($validatedData['valeurEstimee'] ?? 0);
        $retained = $validatedData['valeurRetenue'] !== null ? (float) $validatedData['valeurRetenue'] : round($estimated * 0.8, 2);
        if ($retained <= 0) {
            throw new \InvalidArgumentException('Valeur retenue invalide.');
        }
        if ($retained > $estimated) {
            throw new \InvalidArgumentException('La valeur retenue ne peut pas depasser la valeur estimee.');
        }

        $address = trim((string) ($validatedData['adresseBien'] ?? ''));
        if ($resolvedUserId !== null && $address !== '' && $this->isGarantieAddressAlreadyUsed($resolvedUserId, $address, $id, $creditId)) {
            throw new \InvalidArgumentException('Cette adresse de garantie est deja utilisee sur un autre credit actif.');
        }

        $dateEvaluation = $this->normalizeIsoDateNotFuture((string) ($validatedData['dateEvaluation'] ?? ''), 'Date d\'evaluation invalide.');
        $payload = [
            'idCredit' => $creditId,
            'typeGarantie' => $typeGarantie,
            'description' => trim((string) ($validatedData['description'] ?? '')),
            'adresseBien' => $address,
            'valeurEstimee' => $estimated,
            'valeurRetenue' => $retained,
            'documentJustificatif' => trim((string) ($validatedData['documentJustificatif'] ?? '')),
            'dateEvaluation' => $dateEvaluation,
            'nomGarant' => trim((string) ($validatedData['nomGarant'] ?? '')),
            'statut' => $this->resolveStatusForSave('garantiecredit', 'idGarantie', $data, $id, $forcedUserId),
            'idUser' => $resolvedUserId ?? 0,
        ];

        if ($id === null) {
            $this->connection->insert('garantiecredit', $payload);
            $this->activityService->log((int) $payload['idUser'], 'GARANTIE_CREATE', 'Symfony portal', 'Guarantee created.');
            $createdId = (int) $this->connection->lastInsertId();
            if ($resolvedUserId !== null) {
                $this->notificationService->createNotification(
                    $resolvedUserId,
                    null,
                    $resolvedUserId,
                    'GARANTIE_CREATE',
                    'Garantie enregistree',
                    sprintf(
                        'Votre garantie #%d (%s) a ete enregistree avec le credit associe #%d (%s).',
                        $createdId,
                        $typeGarantie,
                        $creditId,
                        (string) ($credit['typeCredit'] ?? 'Credit')
                    )
                );
            }

            return;
        }

        $this->connection->update('garantiecredit', $payload, ['idGarantie' => $id]);

        $this->activityService->log((int) $payload['idUser'], 'GARANTIE_UPDATE', 'Symfony portal', sprintf('Guarantee #%d updated.', $id));
        if ($resolvedUserId !== null) {
            $this->notificationService->createNotification(
                $resolvedUserId,
                null,
                $resolvedUserId,
                'GARANTIE_UPDATE',
                'Garantie mise a jour',
                sprintf(
                    'Votre garantie #%d (%s) a ete mise a jour avec le credit associe #%d (%s).',
                    (int) $id,
                    $typeGarantie,
                    $creditId,
                    (string) ($credit['typeCredit'] ?? 'Credit')
                )
            );
        }
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
            $this->notificationService->createNotification(
                $resolvedUserId,
                null,
                $resolvedUserId,
                'GARANTIE_DELETE',
                'Garantie supprimee',
                sprintf(
                    'La garantie #%d (%s) et son credit associe #%d (%s) ont ete dissocies puis supprimes.',
                    $id,
                    (string) ($existing['typeGarantie'] ?? 'Garantie'),
                    (int) ($existing['idCredit'] ?? 0),
                    (string) ($existing['typeCredit'] ?? 'Credit')
                )
            );
        }

        if ($forcedUserId !== null) {
            $this->activityService->log($forcedUserId, 'GARANTIE_DELETE', 'Symfony portal', sprintf('Guarantee #%d deleted.', $id));
        }
    }

    public function listPartenaires(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM partenaire ORDER BY nom ASC');
    }

    public function savePartenaire(array $data, ?int $id = null): void
    {
        $payload = [
            'nom' => trim((string) ($data['nom'] ?? '')),
            'categorie' => trim((string) ($data['categorie'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
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
        if ($date < $today) {
            throw new \InvalidArgumentException($errorMessage);
        }

        return $date->format('Y-m-d');
    }

    private function validateCrudInput(string $formType, array $data, string $fallbackMessage): array
    {
        $form = $this->formFactory->create($formType);
        $form->submit($data);

        if (!$form->isValid()) {
            throw new \InvalidArgumentException($this->buildFormErrorMessage($form, $fallbackMessage));
        }

        return $form->getData();
    }

    private function buildFormErrorMessage(FormInterface $form, string $fallbackMessage): string
    {
        $messages = [];

        foreach ($form->getErrors(true) as $error) {
            $message = trim((string) $error->getMessage());
            if ($message !== '' && !in_array($message, $messages, true)) {
                $messages[] = $message;
            }
        }

        return $messages !== [] ? implode(' ', $messages) : $fallbackMessage;
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

            $type = strtolower(trim((string) ($transaction['typeTransaction'] ?? '')));
            $isEntry = str_contains($type, 'credit')
                || str_contains($type, 'depot')
                || str_contains($type, 'entree')
                || str_contains($type, 'entrée')
                || str_contains($type, 'versement');

            if ($isEntry) {
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
