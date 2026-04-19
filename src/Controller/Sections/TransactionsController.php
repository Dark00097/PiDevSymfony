<?php

namespace App\Controller\Sections;

use App\Service\BankingService;
use App\Service\ExportService;
use App\Service\PaymentEmailService;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

final class TransactionsController
{
    private const TRANSACTION_CATEGORIES = [
        'Alimentation',
        'Education',
        'Loyer',
        'Restaurant',
        'Vetements',
        'Assurance sante',
    ];

    private const TRANSACTION_TYPES = [
        'DEPOT',
        'RETRAIT',
        'VIREMENT',
        'PAIEMENT',
    ];

    private const TYPE_COLORS = [
        'DEPOT'    => '#22c55e',
        'RETRAIT'  => '#f59e0b',
        'VIREMENT' => '#3b82f6',
        'PAIEMENT' => '#ef4444',
    ];

    private const CATEGORY_COLORS = [
        'Alimentation'   => '#14b8a6',
        'Education'      => '#2563eb',
        'Loyer'          => '#7c3aed',
        'Restaurant'     => '#ea580c',
        'Vetements'      => '#db2777',
        'Assurance sante'=> '#ca8a04',
    ];

    public function buildAdminData(BankingService $bankingService, array $queryParams = []): array
    {
        $dataset = $this->buildTransactionDataset($bankingService, null, $queryParams);

        return [
            'items' => $dataset['transactions'],
            'support' => [
                'users'                   => $bankingService->listUsers(),
                'accounts'                => $dataset['accounts'],
                'transactions'            => $dataset['transactions'],
                'all_transactions'        => $dataset['all_transactions'],
                'transaction_query'       => $dataset['query'],
                'transaction_categories'  => self::TRANSACTION_CATEGORIES,
                'transaction_types'       => self::TRANSACTION_TYPES,
                'transaction_sort_fields' => $this->transactionSortFields(),
                'transaction_stats'       => $dataset['stats'],
                'edit_transaction'        => $dataset['edit_transaction'],
                'view_transaction'        => $dataset['view_transaction'],
                'pagination'              => $dataset['pagination'],
            ],
        ];
    }

    public function buildPortalData(BankingService $bankingService, int $userId, array $queryParams = []): array
    {
        $dataset = $this->buildTransactionDataset($bankingService, $userId, $queryParams);

        return [
            'items' => $dataset['transactions'],
            'support' => [
                'accounts'                => $dataset['accounts'],
                'transaction_query'       => $dataset['query'],
                'transaction_categories'  => self::TRANSACTION_CATEGORIES,
                'transaction_types'       => self::TRANSACTION_TYPES,
                'transaction_sort_fields' => $this->transactionSortFields(),
                'transaction_stats'       => $dataset['stats'],
                'edit_transaction'        => $dataset['edit_transaction'],
                'view_transaction'        => $dataset['view_transaction'],
                'reclamations'            => $bankingService->listReclamations($userId),
                'all_transactions'        => $dataset['transactions'],
                'pagination'              => $dataset['pagination'],
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'transaction_save':
                $bankingService->saveTransaction(
                    $request->request->all(),
                    $this->requestInt($request, 'idTransaction')
                );
                return ['type' => 'success', 'message' => 'Transaction saved.'];

            case 'transaction_delete':
                $bankingService->deleteTransaction(
                    $this->requestInt($request, 'idTransaction') ?? 0
                );
                return ['type' => 'success', 'message' => 'Transaction deleted.'];
        }

        return null;
    }

    public function handlePortalAction(
        string $action,
        Request $request,
        BankingService $bankingService,
        int $userId,
        ?PaymentEmailService $emailService = null,
        ?Connection $connection = null
    ): ?array {
        error_log("=== TRANSACTIONS CONTROLLER DEBUG ===");
        error_log("Action: " . $action);
        error_log("User ID: " . $userId);
        error_log("Request data raw: " . json_encode($request->request->all()));
error_log("### VERSION CORRIGEE CHARGEE ###");
        switch ($action) {
            case 'transaction_save':
                error_log("=== DÉBUT TRANSACTION_SAVE ===");

                // ── NORMALISATION DES DONNÉES POST ──────────────────────────
                // Le formulaire envoie le montant dans des champs spécifiques
                // selon le type (montant_depot, montant_retrait, montant_virement,
                // montantPaye) ainsi que dans le champ caché "montant" (rempli par JS).
                // On consolide ici côté serveur pour être robuste même si le JS
                // n'a pas pu copier la valeur dans le champ caché.
                $data = $this->normalizeTransactionPostData($request);

                error_log("TransactionsController: Données normalisées = " . json_encode($data));

                $typeTransaction = $data['typeTransaction'];

                try {
                    $transactionId = $bankingService->saveTransaction(
                        $data,
                        $this->requestInt($request, 'idTransaction'),
                        $userId
                    );
                    error_log("TransactionsController: Transaction ID = " . ($transactionId ?? 'null'));

                    // Envoi email au destinataire pour un VIREMENT
                    if ($typeTransaction === 'VIREMENT' && $transactionId && $emailService !== null && $connection !== null) {
                        $emailDest = trim((string) ($data['emailDestinataire'] ?? ''));
                        if ($emailDest !== '') {
                            $sender = $connection->fetchAssociative(
                                'SELECT nom, prenom, email FROM users WHERE idUser = ? LIMIT 1',
                                [$userId]
                            );
                            $transaction = $connection->fetchAssociative(
                                'SELECT * FROM transactions WHERE idTransaction = ? LIMIT 1',
                                [$transactionId]
                            );
                            if ($sender && $transaction) {
                                $emailService->sendVirementNotificationToRecipient($transaction, $sender);
                            }
                        }
                    }

                    // Si c'est un paiement, redirection vers Stripe
                    if ($typeTransaction === 'PAIEMENT' && $transactionId) {
                        error_log("TransactionsController: Redirection vers Stripe pour transaction #" . $transactionId);
                        return [
                            'type'           => 'redirect_stripe',
                            'transaction_id' => $transactionId,
                            'message'        => 'Redirection vers Stripe...',
                        ];
                    }

                    error_log("TransactionsController: Retour succès");
                    return ['type' => 'success', 'message' => 'Transaction enregistrée avec succès.'];

                } catch (\Throwable $e) {
                    error_log("TransactionsController: ERREUR - " . $e->getMessage());
                    error_log("TransactionsController: TRACE - " . $e->getTraceAsString());
                    throw $e;
                }

            case 'transaction_delete':
                $bankingService->deleteTransaction(
                    $this->requestInt($request, 'idTransaction') ?? 0,
                    $userId
                );
                return ['type' => 'success', 'message' => 'Transaction supprimée.'];
        }

        error_log("TransactionsController: Action non reconnue");
        return null;
    }

    // ════════════════════════════════════════════════════════════════════════════
    // NORMALISATION DES DONNÉES POST
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Consolide les données POST du formulaire de transaction.
     *
     * Le formulaire utilise des champs de montant séparés par type :
     *   - montant_depot      → pour DEPOT
     *   - montant_retrait    → pour RETRAIT
     *   - montant_virement   → pour VIREMENT
     *   - montantPaye        → pour PAIEMENT
     *
     * Et des champs de devise séparés :
     *   - currency_depot / currency_retrait / currency_virement / currency_paiement
     *
     * Cette méthode résout le bon montant et la bonne devise selon le type actif,
     * et s'assure que les champs comme nomDestinataire/emailDestinataire viennent
     * bien de la section active (et non d'une section cachée).
     *
     * @return array<string, mixed>
     */
    private function normalizeTransactionPostData(Request $request): array
    {
        $post = $request->request->all();

        $typeTransaction = strtoupper(trim((string) ($post['typeTransaction'] ?? 'DEPOT')));

        // ── Résolution du montant selon le type ─────────────────────────────
        // Priorité : champ spécifique du type > champ caché "montant" (rempli par JS)
        $montant  = null;
        $currency = 'TND';

        switch ($typeTransaction) {
            case 'DEPOT':
                $montant  = $this->resolveFloat($post, ['montant_depot', 'montant']);
                $currency = $this->resolveString($post, ['currency_depot', 'currency'], 'TND');
                break;

            case 'RETRAIT':
                $montant  = $this->resolveFloat($post, ['montant_retrait', 'montant']);
                $currency = $this->resolveString($post, ['currency_retrait', 'currency'], 'TND');
                break;

            case 'VIREMENT':
                $montant  = $this->resolveFloat($post, ['montant_virement', 'montant']);
                $currency = $this->resolveString($post, ['currency_virement', 'currency'], 'TND');
                break;

            case 'PAIEMENT':
                // Pour PAIEMENT le champ s'appelle montantPaye dans le form
                $montant  = $this->resolveFloat($post, ['montantPaye', 'montant_paye', 'montant']);
                $currency = $this->resolveString($post, ['currency_paiement', 'currency'], 'TND');
                break;
        }

        // ── Résolution des champs destinataire selon le type ────────────────
        // Évite les conflits quand les deux sections (VIREMENT + PAIEMENT)
        // ont des champs nomDestinataire / emailDestinataire dans le DOM.
        // Après le fix JS (disabled sur les sections cachées), ce n'est plus
        // un problème, mais on garde cette protection côté serveur.
        $nomDestinataire   = '';
        $emailDestinataire = '';
        $compteDestinataire = '';

        switch ($typeTransaction) {
            case 'VIREMENT':
                $nomDestinataire    = trim((string) ($post['nomDestinataire'] ?? ''));
                $emailDestinataire  = trim((string) ($post['emailDestinataire'] ?? ''));
                $compteDestinataire = trim((string) ($post['compteDestinataire'] ?? ''));
                break;

            case 'PAIEMENT':
                $nomDestinataire   = trim((string) ($post['nomDestinataire'] ?? ''));
                $emailDestinataire = trim((string) ($post['emailDestinataire'] ?? ''));
                break;
        }

        // ── Résolution de la catégorie ───────────────────────────────────────
        // La catégorie n'est obligatoire que pour PAIEMENT,
        // mais on la transmet toujours si présente.
        $categorie = trim((string) ($post['categorie'] ?? ''));

        // ── Construction du tableau normalisé ───────────────────────────────
        $normalized = [
            // Champs communs
            'typeTransaction'    => $typeTransaction,
            'compte'             => trim((string) ($post['compte'] ?? '')),
            'idCompte'           => trim((string) ($post['compte'] ?? '')),
            'dateTransaction'    => trim((string) ($post['dateTransaction'] ?? date('Y-m-d'))),
            'description'        => trim((string) ($post['description'] ?? '')),

            // Montant résolu
            'montant'            => $montant,
            'currency'           => strtoupper($currency),

            // Catégorie
            'categorie'          => $categorie,

            // Destinataire (virement / paiement)
            'nomDestinataire'    => $nomDestinataire,
            'emailDestinataire'  => $emailDestinataire,
            'compteDestinataire' => $compteDestinataire,
            'idCompteDestinataire' => $compteDestinataire,

            // Montant payé (paiement uniquement)
            'montantPaye'        => $typeTransaction === 'PAIEMENT' ? $montant : 0,
        ];

        error_log("normalizeTransactionPostData: type={$typeTransaction} montant={$montant} currency={$currency}");
        error_log("normalizeTransactionPostData: compte=" . $normalized['compte']);
        error_log("normalizeTransactionPostData: compteDestinataire=" . $normalized['compteDestinataire']);

        return $normalized;
    }

    /**
     * Résout la première valeur float non-nulle et > 0 parmi une liste de clés.
     *
     * @param array<string, mixed> $post
     * @param string[]             $keys
     */
    private function resolveFloat(array $post, array $keys): ?float
    {
        foreach ($keys as $key) {
            $raw = $post[$key] ?? null;
            if ($raw !== null && $raw !== '') {
                $value = (float) $raw;
                if ($value > 0) {
                    return $value;
                }
            }
        }
        return null;
    }

    /**
     * Résout la première valeur string non-vide parmi une liste de clés.
     *
     * @param array<string, mixed> $post
     * @param string[]             $keys
     */
    private function resolveString(array $post, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $raw = trim((string) ($post[$key] ?? ''));
            if ($raw !== '') {
                return $raw;
            }
        }
        return $default;
    }

    // ════════════════════════════════════════════════════════════════════════════
    // PDF EXPORT
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Builds the transaction PDF content.
     * Returns [string $pdfContent, string $filename].
     */
    public function buildTransactionPdf(
        BankingService $bankingService,
        ExportService $exportService,
        ?int $idTransaction,
        array $queryParams = []
    ): array {
        $dataset = $this->buildTransactionDataset($bankingService, null, $queryParams);

        if ($idTransaction !== null) {
            $transaction = $this->findTransactionById($dataset['all_transactions'], $idTransaction);
            if ($transaction === null) {
                return ['', ''];
            }

            $headers = ['Champ', 'Valeur'];
            $rows = [
                ['ID transaction',   '#'.$idTransaction],
                ['Numero compte',    $this->displayValue($transaction['numeroCompte'] ?? null)],
                ['ID compte',        $this->displayValue($transaction['idCompte'] ?? null)],
                ['Categorie',        $this->displayValue($transaction['categorie'] ?? null)],
                ['Date transaction', $this->displayValue($transaction['dateTransaction'] ?? null)],
                ['Type transaction', $this->displayValue($transaction['typeTransaction'] ?? null)],
                ['Montant',          $this->formatAmount($transaction['montant_value'] ?? 0)],
                ['Solde apres',      $this->formatAmount($transaction['soldeApres'] ?? null)],
                ['Montant paye',     $this->formatAmount($transaction['montantPaye_value'] ?? 0)],
                ['ID utilisateur',   $this->displayValue($transaction['resolved_user_id'] ?? $transaction['idUser'] ?? null)],
                ['Utilisateur',      $this->displayValue($transaction['user_name'] ?? null)],
                ['Type compte lie',  $this->displayValue($transaction['linked_account']['typeCompte'] ?? null)],
                ['Statut compte lie',$this->displayValue($transaction['linked_account']['statutCompte'] ?? null)],
                ['Solde compte lie', $this->formatAmount($transaction['linked_account']['solde'] ?? null)],
                ['Description',      $this->displayValue($transaction['description'] ?? null)],
            ];

            $typeLabel = $this->canonicalTransactionType((string) ($transaction['typeTransaction'] ?? ''));
            $stats = [
                ['label' => 'Type',      'value' => $typeLabel],
                ['label' => 'Montant',   'value' => $this->formatAmount($transaction['montant_value'] ?? 0)],
                ['label' => 'Categorie', 'value' => $this->displayValue($transaction['categorie'] ?? null)],
            ];

            $title    = sprintf('Fiche Transaction %s', '#'.$idTransaction);
            $subtitle = sprintf(
                'Detail complet de la transaction %s du compte %s exporte depuis Nexora.',
                '#'.$idTransaction,
                $this->displayValue($transaction['numeroCompte'] ?? null)
            );
            $filename = sprintf('nexora-transaction-%d.pdf', $idTransaction);
            $accent   = self::TYPE_COLORS[$typeLabel] ?? '#11b7aa';

            return [
                $exportService->buildPdf($title, $headers, $rows, $stats, $subtitle, $accent),
                $filename,
            ];
        }

        $headers = ['ID', 'N compte', 'Categorie', 'Date', 'Montant', 'Type', 'Solde apres', 'Montant paye', 'Description'];
        $rows = [];
        foreach ($dataset['transactions'] as $transaction) {
            $rows[] = [
                (string) ($transaction['idTransaction'] ?? ''),
                (string) ($transaction['numeroCompte'] ?? ''),
                (string) ($transaction['categorie'] ?? ''),
                (string) ($transaction['dateTransaction'] ?? ''),
                $this->formatAmount($transaction['montant_value'] ?? 0),
                (string) ($transaction['typeTransaction'] ?? ''),
                $this->formatAmount($transaction['soldeApres'] ?? null),
                $this->formatAmount($transaction['montantPaye_value'] ?? 0),
                (string) ($transaction['description'] ?? ''),
            ];
        }

        $stats = [
            ['label' => 'Transactions', 'value' => (string) count($dataset['transactions'])],
            ['label' => 'Depots',       'value' => (string) ($dataset['stats']['counts']['DEPOT']    ?? 0)],
            ['label' => 'Retraits',     'value' => (string) ($dataset['stats']['counts']['RETRAIT']  ?? 0)],
            ['label' => 'Virements',    'value' => (string) ($dataset['stats']['counts']['VIREMENT'] ?? 0)],
            ['label' => 'Paiements',    'value' => (string) ($dataset['stats']['counts']['PAIEMENT'] ?? 0)],
        ];

        return [
            $exportService->buildPdf(
                'Rapport Transactions Bancaires',
                $headers,
                $rows,
                $stats,
                'Export complet des transactions bancaires admin depuis Nexora.',
                '#11b7aa'
            ),
            'nexora-transactions-all.pdf',
        ];
    }

    // ════════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * @param array<string, mixed> $queryParams
     */
    private function buildTransactionDataset(BankingService $bankingService, ?int $userId, array $queryParams): array
    {
        $accounts       = $bankingService->listAccounts($userId);
        $accountMap     = $this->indexAccountsById($accounts);
        $allTransactions = array_map(
            fn (array $t): array => $this->enrichTransaction($t, $accountMap),
            $bankingService->listTransactions($userId)
        );
        $query = $this->normalizePortalQuery($queryParams);

        $filteredTransactions = array_values(array_filter(
            $allTransactions,
            fn (array $t): bool =>
                $this->matchesPortalFilter($t, $query['filter'])
                && $this->matchesPortalSearch($t, $query['q'])
                && $this->matchesAdvancedFilters($t, $query)
        ));

        usort($filteredTransactions, fn (array $l, array $r): int =>
            $this->compareTransactions($l, $r, $query['sort'], $query['dir'])
        );

        $totalItems  = count($filteredTransactions);
        $totalPages  = max(1, (int) ceil($totalItems / $query['per_page']));
        $currentPage = min($query['page'], $totalPages);
        $offset      = ($currentPage - 1) * $query['per_page'];

        $paginatedTransactions = array_slice($filteredTransactions, $offset, $query['per_page']);

        return [
            'accounts'         => $accounts,
            'all_transactions' => $allTransactions,
            'transactions'     => $paginatedTransactions,
            'query'            => $query,
            'edit_transaction' => $this->findTransactionById($allTransactions, $query['edit_id']),
            'view_transaction' => $this->findTransactionById($allTransactions, $query['view_id']),
            'stats'            => $this->buildTransactionStats($filteredTransactions),
            'pagination'       => [
                'current_page' => $currentPage,
                'total_pages'  => $totalPages,
                'total_items'  => $totalItems,
                'per_page'     => $query['per_page'],
                'has_prev'     => $currentPage > 1,
                'has_next'     => $currentPage < $totalPages,
            ],
        ];
    }

    private function requestInt(Request $request, string $key): ?int
    {
        $value = $request->request->get($key);
        if ($value === null || $value === '') {
            return null;
        }
        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function indexAccountsById(array $accounts): array
    {
        $map = [];
        foreach ($accounts as $account) {
            $id = (int) ($account['idCompte'] ?? 0);
            if ($id > 0) {
                $map[$id] = $account;
            }
        }
        return $map;
    }

    private function enrichTransaction(array $transaction, array $accountMap): array
    {
        $id = (int) ($transaction['idCompte'] ?? 0);
        $account = $id > 0 ? ($accountMap[$id] ?? null) : null;
        $transaction['numeroCompte']  = (string) ($account['numeroCompte'] ?? '');
        $transaction['linked_account'] = $account;
        return $transaction;
    }

    private function normalizePortalQuery(array $queryParams): array
    {
        $allowedFilters = ['all', 'depot', 'retrait', 'virement', 'paiement'];
        $allowedSorts   = array_keys($this->transactionSortFields());

        $query    = trim((string) ($queryParams['q'] ?? ''));
        $filter   = strtolower(trim((string) ($queryParams['filter'] ?? 'all')));
        $sort     = trim((string) ($queryParams['sort'] ?? 'dateTransaction'));
        $dir      = strtolower(trim((string) ($queryParams['dir'] ?? 'desc')));
        $page     = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage  = 3;

        $dateFrom  = trim((string) ($queryParams['date_from'] ?? ''));
        $dateTo    = trim((string) ($queryParams['date_to'] ?? ''));
        $amountMin = $queryParams['amount_min'] ?? null;
        $amountMax = $queryParams['amount_max'] ?? null;
        $category  = trim((string) ($queryParams['category'] ?? ''));
        $accountId = $this->queryInt($queryParams['account_id'] ?? null);

        if (!in_array($filter, $allowedFilters, true)) { $filter = 'all'; }
        if (!in_array($sort, $allowedSorts, true))     { $sort = 'dateTransaction'; }
        if (!in_array($dir, ['asc', 'desc'], true))    { $dir = 'desc'; }

        return [
            'q'          => $query,
            'filter'     => $filter,
            'sort'       => $sort,
            'dir'        => $dir,
            'edit_id'    => $this->queryInt($queryParams['edit_id'] ?? null),
            'view_id'    => $this->queryInt($queryParams['view_id'] ?? null),
            'page'       => $page,
            'per_page'   => $perPage,
            'date_from'  => $dateFrom !== '' ? $dateFrom : null,
            'date_to'    => $dateTo !== '' ? $dateTo : null,
            'amount_min' => $amountMin !== null && $amountMin !== '' ? (float) $amountMin : null,
            'amount_max' => $amountMax !== null && $amountMax !== '' ? (float) $amountMax : null,
            'category'   => $category !== '' ? $category : null,
            'account_id' => $accountId,
        ];
    }

    /** @return array<string, string> */
    private function transactionSortFields(): array
    {
        return [
            'numeroCompte'    => 'Numero de compte',
            'categorie'       => 'Categorie',
            'dateTransaction' => 'Date transaction',
            'montant'         => 'Montant',
            'typeTransaction' => 'Type transaction',
            'soldeApres'      => 'Solde apres',
            'description'     => 'Description',
            'montantPaye'     => 'Montant paye',
        ];
    }

    private function matchesPortalFilter(array $transaction, string $filter): bool
    {
        if ($filter === 'all') { return true; }
        return strtolower(trim((string) ($transaction['typeTransaction'] ?? ''))) === $filter;
    }

    private function matchesPortalSearch(array $transaction, string $query): bool
    {
        $needle = $this->normalizeSearchText($query);
        if ($needle === '') { return true; }

        $haystack = $this->normalizeSearchText(implode(' ', [
            (string) ($transaction['idTransaction'] ?? ''),
            (string) ($transaction['resolved_user_id'] ?? $transaction['idUser'] ?? ''),
            (string) ($transaction['idCompte'] ?? ''),
            (string) ($transaction['numeroCompte'] ?? ''),
            (string) ($transaction['categorie'] ?? ''),
            (string) ($transaction['dateTransaction'] ?? ''),
            (string) ($transaction['montant_value'] ?? ''),
            $this->formatAmount($transaction['montant_value'] ?? 0),
            (string) ($transaction['typeTransaction'] ?? ''),
            (string) ($transaction['soldeApres'] ?? ''),
            $this->formatAmount($transaction['soldeApres'] ?? null),
            (string) ($transaction['description'] ?? ''),
            (string) ($transaction['montantPaye_value'] ?? ''),
            $this->formatAmount($transaction['montantPaye_value'] ?? 0),
            (string) ($transaction['user_name'] ?? ''),
            (string) ($transaction['linked_account']['typeCompte'] ?? ''),
            (string) ($transaction['linked_account']['statutCompte'] ?? ''),
        ]));

        return str_contains($haystack, $needle);
    }

    private function matchesAdvancedFilters(array $transaction, array $query): bool
    {
        $date   = (string) ($transaction['dateTransaction'] ?? '');
        $amount = (float) ($transaction['montant_value'] ?? 0);

        if ($query['date_from'] !== null && $date < $query['date_from']) { return false; }
        if ($query['date_to']   !== null && $date > $query['date_to'])   { return false; }
        if ($query['amount_min'] !== null && $amount < $query['amount_min']) { return false; }
        if ($query['amount_max'] !== null && $amount > $query['amount_max']) { return false; }

        if ($query['category'] !== null) {
            $tc = $this->normalizeSearchText((string) ($transaction['categorie'] ?? ''));
            $fc = $this->normalizeSearchText($query['category']);
            if ($tc !== $fc) { return false; }
        }

        if ($query['account_id'] !== null) {
            if ((int) ($transaction['idCompte'] ?? 0) !== $query['account_id']) { return false; }
        }

        return true;
    }

    private function compareTransactions(array $left, array $right, string $sortField, string $direction): int
    {
        $comparison = match ($sortField) {
            'idTransaction'   => ((int) ($left['idTransaction'] ?? 0)) <=> ((int) ($right['idTransaction'] ?? 0)),
            'resolved_user_id'=> ((int) ($left['resolved_user_id'] ?? $left['idUser'] ?? 0)) <=> ((int) ($right['resolved_user_id'] ?? $right['idUser'] ?? 0)),
            'idCompte'        => ((int) ($left['idCompte'] ?? 0)) <=> ((int) ($right['idCompte'] ?? 0)),
            'montant'         => ((float) ($left['montant_value'] ?? 0)) <=> ((float) ($right['montant_value'] ?? 0)),
            'soldeApres'      => ((float) ($left['soldeApres'] ?? 0)) <=> ((float) ($right['soldeApres'] ?? 0)),
            'montantPaye'     => ((float) ($left['montantPaye_value'] ?? 0)) <=> ((float) ($right['montantPaye_value'] ?? 0)),
            default           => strnatcasecmp(
                $this->transactionComparableValue($left, $sortField),
                $this->transactionComparableValue($right, $sortField)
            ),
        };

        if ($comparison === 0) {
            $comparison = ((int) ($left['idTransaction'] ?? 0)) <=> ((int) ($right['idTransaction'] ?? 0));
        }

        return $direction === 'desc' ? ($comparison * -1) : $comparison;
    }

    private function transactionComparableValue(array $transaction, string $field): string
    {
        return trim((string) match ($field) {
            'numeroCompte' => $transaction['numeroCompte'] ?? '',
            'description'  => $transaction['description'] ?? '',
            default        => $transaction[$field] ?? '',
        });
    }

    private function findTransactionById(array $transactions, ?int $id): ?array
    {
        if ($id === null || $id <= 0) { return null; }
        foreach ($transactions as $t) {
            if ((int) ($t['idTransaction'] ?? 0) === $id) { return $t; }
        }
        return null;
    }

    private function buildTransactionStats(array $transactions): array
    {
        $categories  = [];
        $counts      = [];
        $totalAmount = 0.0;

        foreach ($transactions as $t) {
            $category = $this->canonicalTransactionCategory((string) ($t['categorie'] ?? ''));
            $type     = $this->canonicalTransactionType((string) ($t['typeTransaction'] ?? ''));
            $amount   = (float) ($t['montant_value'] ?? 0);

            $counts[$type] = ($counts[$type] ?? 0) + 1;

            if (!array_key_exists($category, $categories)) {
                $categories[$category] = [
                    'label'      => $category,
                    'count'      => 0,
                    'amount'     => 0.0,
                    'percentage' => 0.0,
                    'color'      => self::CATEGORY_COLORS[$category] ?? '#64748b',
                ];
            }

            $categories[$category]['count']++;
            $categories[$category]['amount'] += $amount;
            $totalAmount += $amount;
        }

        $total = count($transactions);
        foreach ($categories as &$cat) {
            $cat['percentage'] = $total > 0 ? round(($cat['count'] / $total) * 100, 1) : 0.0;
        }
        unset($cat);

        uasort($categories, static fn (array $l, array $r): int =>
            ($r['count'] ?? 0) <=> ($l['count'] ?? 0)
            ?: strnatcasecmp((string) ($l['label'] ?? ''), (string) ($r['label'] ?? ''))
        );

        return [
            'total'        => $total,
            'total_amount' => $totalAmount,
            'categories'   => array_values($categories),
            'counts'       => $counts,
        ];
    }

    private function canonicalTransactionCategory(string $value): string
    {
        $normalized = $this->normalizeSearchText($value);
        foreach (self::TRANSACTION_CATEGORIES as $category) {
            if ($this->normalizeSearchText($category) === $normalized) { return $category; }
        }
        return trim($value) !== '' ? trim($value) : 'Autre';
    }

    private function canonicalTransactionType(string $value): string
    {
        $normalized = strtoupper(trim($value));
        $mapping = ['VERSEMENT' => 'DEPOT', 'CREDIT' => 'DEPOT', 'DEBIT' => 'RETRAIT'];
        if (isset($mapping[$normalized])) { return $mapping[$normalized]; }
        return in_array($normalized, self::TRANSACTION_TYPES, true) ? $normalized : 'DEPOT';
    }

    private function normalizeSearchText(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        return strtr($normalized, [
            'à'=>'a','á'=>'a','â'=>'a','ä'=>'a',
            'ç'=>'c',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ñ'=>'n',
            'ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
            'ỳ'=>'y','ý'=>'y','ÿ'=>'y',
        ]);
    }

    private function formatAmount(mixed $value): string
    {
        if ($value === null || $value === '') { return '-'; }
        return number_format((float) $value, 2, '.', ' ').' DT';
    }

    private function displayValue(mixed $value): string
    {
        $string = trim((string) ($value ?? ''));
        return $string !== '' ? $string : '-';
    }

    private function queryInt(mixed $value): ?int
    {
        if ($value === null || $value === '') { return null; }
        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }
}