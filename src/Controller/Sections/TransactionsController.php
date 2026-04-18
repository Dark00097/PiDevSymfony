<?php

namespace App\Controller\Sections;

use App\Service\BankingService;
use App\Service\ExportService;
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
        'Credit',
        'Debit',
    ];

    private const TYPE_COLORS = [
        'Credit' => '#22c55e',
        'Debit' => '#ef4444',
    ];

    private const CATEGORY_COLORS = [
        'Alimentation' => '#14b8a6',
        'Education' => '#2563eb',
        'Loyer' => '#7c3aed',
        'Restaurant' => '#ea580c',
        'Vetements' => '#db2777',
        'Assurance sante' => '#ca8a04',
    ];

    public function buildAdminData(BankingService $bankingService, array $queryParams = []): array
    {
        $dataset = $this->buildTransactionDataset($bankingService, null, $queryParams);

        return [
            'items' => $dataset['transactions'],
            'support' => [
                'users' => $bankingService->listUsers(),
                'accounts' => $dataset['accounts'],
                'transactions' => $dataset['transactions'],
                'all_transactions' => $dataset['all_transactions'],
                'transaction_query' => $dataset['query'],
                'transaction_categories' => self::TRANSACTION_CATEGORIES,
                'transaction_types' => self::TRANSACTION_TYPES,
                'transaction_sort_fields' => $this->transactionSortFields(),
                'transaction_stats' => $dataset['stats'],
                'edit_transaction' => $dataset['edit_transaction'],
                'view_transaction' => $dataset['view_transaction'],
            ],
        ];
    }

    public function buildPortalData(BankingService $bankingService, int $userId, array $queryParams = []): array
    {
        $dataset = $this->buildTransactionDataset($bankingService, $userId, $queryParams);

        return [
            'items' => $dataset['transactions'],
            'support' => [
                'accounts' => $dataset['accounts'],
                'transaction_query' => $dataset['query'],
                'transaction_categories' => self::TRANSACTION_CATEGORIES,
                'transaction_types' => self::TRANSACTION_TYPES,
                'transaction_sort_fields' => $this->transactionSortFields(),
                'transaction_stats' => $dataset['stats'],
                'edit_transaction' => $dataset['edit_transaction'],
                'view_transaction' => $dataset['view_transaction'],
                'reclamations' => $bankingService->listReclamations($userId),
                'all_transactions' => $dataset['transactions'],
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'transaction_save':
                $bankingService->saveTransaction($request->request->all(), $this->requestInt($request, 'idTransaction'));

                return ['type' => 'success', 'message' => 'Transaction saved.'];

            case 'transaction_delete':
                $bankingService->deleteTransaction($this->requestInt($request, 'idTransaction') ?? 0);

                return ['type' => 'success', 'message' => 'Transaction deleted.'];
        }

        return null;
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'transaction_save':
                $bankingService->saveTransaction($request->request->all(), $this->requestInt($request, 'idTransaction'), $userId);

                return ['type' => 'success', 'message' => 'Transaction saved.'];

            case 'transaction_delete':
                $bankingService->deleteTransaction($this->requestInt($request, 'idTransaction') ?? 0, $userId);

                return ['type' => 'success', 'message' => 'Transaction deleted.'];
        }

        return null;
    }

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
                ['ID transaction', '#'.$idTransaction],
                ['Numero compte', $this->displayValue($transaction['numeroCompte'] ?? null)],
                ['ID compte', $this->displayValue($transaction['idCompte'] ?? null)],
                ['Categorie', $this->displayValue($transaction['categorie'] ?? null)],
                ['Date transaction', $this->displayValue($transaction['dateTransaction'] ?? null)],
                ['Type transaction', $this->displayValue($transaction['typeTransaction'] ?? null)],
                ['Montant', $this->formatAmount($transaction['montant_value'] ?? 0)],
                ['Solde apres', $this->formatAmount($transaction['soldeApres'] ?? null)],
                ['Montant paye', $this->formatAmount($transaction['montantPaye_value'] ?? 0)],
                ['ID utilisateur', $this->displayValue($transaction['resolved_user_id'] ?? $transaction['idUser'] ?? null)],
                ['Utilisateur', $this->displayValue($transaction['user_name'] ?? null)],
                ['Type compte lie', $this->displayValue($transaction['linked_account']['typeCompte'] ?? null)],
                ['Statut compte lie', $this->displayValue($transaction['linked_account']['statutCompte'] ?? null)],
                ['Solde compte lie', $this->formatAmount($transaction['linked_account']['solde'] ?? null)],
                ['Description', $this->displayValue($transaction['description'] ?? null)],
            ];

            $typeLabel = $this->canonicalTransactionType((string) ($transaction['typeTransaction'] ?? ''));
            $stats = [
                ['label' => 'Type', 'value' => $typeLabel],
                ['label' => 'Montant', 'value' => $this->formatAmount($transaction['montant_value'] ?? 0)],
                ['label' => 'Categorie', 'value' => $this->displayValue($transaction['categorie'] ?? null)],
            ];

            $title = sprintf('Fiche Transaction %s', '#'.$idTransaction);
            $subtitle = sprintf(
                'Detail complet de la transaction %s du compte %s exporte depuis Nexora.',
                '#'.$idTransaction,
                $this->displayValue($transaction['numeroCompte'] ?? null)
            );
            $filename = sprintf('nexora-transaction-%d.pdf', $idTransaction);
            $accent = self::TYPE_COLORS[$typeLabel] ?? '#11b7aa';

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
            ['label' => 'Credits', 'value' => (string) ($dataset['stats']['counts']['Credit'] ?? 0)],
            ['label' => 'Debits', 'value' => (string) ($dataset['stats']['counts']['Debit'] ?? 0)],
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

    /**
     * @param array<string, mixed> $queryParams
     * @return array{
     *   accounts: array<int, array<string, mixed>>,
     *   all_transactions: array<int, array<string, mixed>>,
     *   transactions: array<int, array<string, mixed>>,
     *   query: array{q:string, filter:string, sort:string, dir:string, edit_id:?int, view_id:?int},
     *   edit_transaction:?array<string, mixed>,
     *   view_transaction:?array<string, mixed>,
     *   stats: array<string, mixed>
     * }
     */
    private function buildTransactionDataset(BankingService $bankingService, ?int $userId, array $queryParams): array
    {
        $accounts = $bankingService->listAccounts($userId);
        $accountMap = $this->indexAccountsById($accounts);
        $allTransactions = array_map(
            fn (array $transaction): array => $this->enrichTransaction($transaction, $accountMap),
            $bankingService->listTransactions($userId)
        );
        $query = $this->normalizePortalQuery($queryParams);

        $transactions = array_values(array_filter(
            $allTransactions,
            fn (array $transaction): bool => $this->matchesPortalFilter($transaction, $query['filter'])
                && $this->matchesPortalSearch($transaction, $query['q'])
        ));

        usort($transactions, fn (array $left, array $right): int => $this->compareTransactions($left, $right, $query['sort'], $query['dir']));

        return [
            'accounts' => $accounts,
            'all_transactions' => $allTransactions,
            'transactions' => $transactions,
            'query' => $query,
            'edit_transaction' => $this->findTransactionById($allTransactions, $query['edit_id']),
            'view_transaction' => $this->findTransactionById($allTransactions, $query['view_id']),
            'stats' => $this->buildTransactionStats($transactions),
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

    /**
     * @param array<int, array<string, mixed>> $accounts
     * @return array<int, array<string, mixed>>
     */
    private function indexAccountsById(array $accounts): array
    {
        $map = [];

        foreach ($accounts as $account) {
            $accountId = (int) ($account['idCompte'] ?? 0);
            if ($accountId > 0) {
                $map[$accountId] = $account;
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $transaction
     * @param array<int, array<string, mixed>> $accountMap
     * @return array<string, mixed>
     */
    private function enrichTransaction(array $transaction, array $accountMap): array
    {
        $accountId = (int) ($transaction['idCompte'] ?? 0);
        $account = $accountId > 0 ? ($accountMap[$accountId] ?? null) : null;

        $transaction['numeroCompte'] = (string) ($account['numeroCompte'] ?? '');
        $transaction['linked_account'] = $account;

        return $transaction;
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{q:string, filter:string, sort:string, dir:string, edit_id:?int, view_id:?int}
     */
    private function normalizePortalQuery(array $queryParams): array
    {
        $allowedFilters = ['all', 'credit', 'debit'];
        $allowedSorts = array_keys($this->transactionSortFields());

        $query = trim((string) ($queryParams['q'] ?? ''));
        $filter = strtolower(trim((string) ($queryParams['filter'] ?? 'all')));
        $sort = trim((string) ($queryParams['sort'] ?? 'dateTransaction'));
        $dir = strtolower(trim((string) ($queryParams['dir'] ?? 'desc')));

        if (!in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'dateTransaction';
        }

        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        return [
            'q' => $query,
            'filter' => $filter,
            'sort' => $sort,
            'dir' => $dir,
            'edit_id' => $this->queryInt($queryParams['edit_id'] ?? null),
            'view_id' => $this->queryInt($queryParams['view_id'] ?? null),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function transactionSortFields(): array
    {
        return [
            'numeroCompte' => 'Numero de compte',
            'categorie' => 'Categorie',
            'dateTransaction' => 'Date transaction',
            'montant' => 'Montant',
            'typeTransaction' => 'Type transaction',
            'soldeApres' => 'Solde apres',
            'description' => 'Description',
            'montantPaye' => 'Montant paye',
        ];
    }

    /**
     * @param array<string, mixed> $transaction
     */
    private function matchesPortalFilter(array $transaction, string $filter): bool
    {
        if ($filter === 'all') {
            return true;
        }

        $type = strtolower(trim((string) ($transaction['typeTransaction'] ?? '')));

        return $type === $filter;
    }

    /**
     * @param array<string, mixed> $transaction
     */
    private function matchesPortalSearch(array $transaction, string $query): bool
    {
        $needle = $this->normalizeSearchText($query);
        if ($needle === '') {
            return true;
        }

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

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareTransactions(array $left, array $right, string $sortField, string $direction): int
    {
        $comparison = match ($sortField) {
            'idTransaction' => ((int) ($left['idTransaction'] ?? 0)) <=> ((int) ($right['idTransaction'] ?? 0)),
            'resolved_user_id' => ((int) ($left['resolved_user_id'] ?? $left['idUser'] ?? 0)) <=> ((int) ($right['resolved_user_id'] ?? $right['idUser'] ?? 0)),
            'idCompte' => ((int) ($left['idCompte'] ?? 0)) <=> ((int) ($right['idCompte'] ?? 0)),
            'montant' => ((float) ($left['montant_value'] ?? 0)) <=> ((float) ($right['montant_value'] ?? 0)),
            'soldeApres' => ((float) ($left['soldeApres'] ?? 0)) <=> ((float) ($right['soldeApres'] ?? 0)),
            'montantPaye' => ((float) ($left['montantPaye_value'] ?? 0)) <=> ((float) ($right['montantPaye_value'] ?? 0)),
            default => strnatcasecmp(
                $this->transactionComparableValue($left, $sortField),
                $this->transactionComparableValue($right, $sortField)
            ),
        };

        if ($comparison === 0) {
            $comparison = ((int) ($left['idTransaction'] ?? 0)) <=> ((int) ($right['idTransaction'] ?? 0));
        }

        return $direction === 'desc' ? ($comparison * -1) : $comparison;
    }

    /**
     * @param array<string, mixed> $transaction
     */
    private function transactionComparableValue(array $transaction, string $field): string
    {
        return trim((string) match ($field) {
            'numeroCompte' => $transaction['numeroCompte'] ?? '',
            'description' => $transaction['description'] ?? '',
            default => $transaction[$field] ?? '',
        });
    }

    /**
     * @param array<int, array<string, mixed>> $transactions
     * @return array<string, mixed>|null
     */
    private function findTransactionById(array $transactions, ?int $id): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        foreach ($transactions as $transaction) {
            if ((int) ($transaction['idTransaction'] ?? 0) === $id) {
                return $transaction;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $transactions
     * @return array<string, mixed>
     */
    private function buildTransactionStats(array $transactions): array
    {
        $categories = [];
        $totalAmount = 0.0;

        foreach ($transactions as $transaction) {
            $category = $this->canonicalTransactionCategory((string) ($transaction['categorie'] ?? ''));
            $amount = (float) ($transaction['montant_value'] ?? 0);

            if (!array_key_exists($category, $categories)) {
                $categories[$category] = [
                    'label' => $category,
                    'count' => 0,
                    'amount' => 0.0,
                    'percentage' => 0.0,
                    'color' => self::CATEGORY_COLORS[$category] ?? '#64748b',
                ];
            }

            $categories[$category]['count']++;
            $categories[$category]['amount'] += $amount;
            $totalAmount += $amount;
        }

        $total = count($transactions);
        foreach ($categories as &$categoryStats) {
            $categoryStats['percentage'] = $total > 0 ? round(($categoryStats['count'] / $total) * 100, 1) : 0.0;
        }
        unset($categoryStats);

        uasort($categories, static function (array $left, array $right): int {
            $countComparison = ($right['count'] ?? 0) <=> ($left['count'] ?? 0);
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return [
            'total' => $total,
            'total_amount' => $totalAmount,
            'categories' => array_values($categories),
        ];
    }

    private function canonicalTransactionCategory(string $value): string
    {
        $normalized = $this->normalizeSearchText($value);

        foreach (self::TRANSACTION_CATEGORIES as $category) {
            if ($this->normalizeSearchText($category) === $normalized) {
                return $category;
            }
        }

        return trim($value) !== '' ? trim($value) : 'Autre';
    }

    private function canonicalTransactionType(string $value): string
    {
        return str_contains($this->normalizeSearchText($value), 'debit') ? 'Debit' : 'Credit';
    }

    private function normalizeSearchText(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return strtr($normalized, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ÿ' => 'y',
        ]);
    }

    private function formatAmount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, 2, '.', ' ').' DT';
    }

    private function displayValue(mixed $value): string
    {
        $string = trim((string) ($value ?? ''));

        return $string !== '' ? $string : '-';
    }

    /**
     * @param mixed $value
     */
    private function queryInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }
}
