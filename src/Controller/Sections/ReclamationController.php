<?php

namespace App\Controller\Sections;

use App\Form\ReclamationType;
use App\Service\BankingService;
use Symfony\Component\HttpFoundation\Request;

final class ReclamationController
{
    private const RECLAMATION_TYPES = [
        'Echec de montant',
        'Virement non recu',
        'Erreur de transaction',
        'Probleme de connexion au compte',
    ];

    private const RECLAMATION_STATUSES = [
        'Valide',
        'Ferme',
        'Attende',
    ];

    public function buildAdminData(BankingService $bankingService, array $queryParams = []): array
    {
        $dataset = $this->buildReclamationDataset($bankingService, null, $queryParams);

        return [
            'items' => $dataset['reclamations'],
            'support' => [
                'reclamations' => $dataset['reclamations'],
                'reclamation_query' => $dataset['query'],
                'reclamation_types' => self::RECLAMATION_TYPES,
                'reclamation_statuses' => self::RECLAMATION_STATUSES,
                'reclamation_sort_fields' => $this->reclamationSortFields(),
                'reclamation_transactions' => $dataset['transactions'],
                'edit_reclamation' => $dataset['edit_reclamation'],
                'view_reclamation' => $dataset['view_reclamation'],
                'forms' => [
                    'reclamation' => ReclamationType::class,
                ],
            ],
        ];
    }

    public function buildPortalData(BankingService $bankingService, int $userId, array $queryParams = []): array
    {
        $dataset = $this->buildReclamationDataset($bankingService, $userId, $queryParams);

        return [
            'items' => $dataset['reclamations'],
            'support' => [
                'transactions' => $dataset['transactions'],
                'reclamation_query' => $dataset['query'],
                'reclamation_types' => self::RECLAMATION_TYPES,
                'reclamation_statuses' => self::RECLAMATION_STATUSES,
                'reclamation_sort_fields' => $this->reclamationSortFields(),
                'edit_reclamation' => $dataset['edit_reclamation'],
                'view_reclamation' => $dataset['view_reclamation'],
                'forms' => [
                    'reclamation' => ReclamationType::class,
                ],
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'reclamation_save':
                $bankingService->saveReclamation($request->request->all(), $this->requestInt($request, 'idReclamation'));

                return ['type' => 'success', 'message' => 'Reclamation saved.'];

            case 'reclamation_delete':
                $bankingService->deleteReclamation($this->requestInt($request, 'idReclamation') ?? 0);

                return ['type' => 'success', 'message' => 'Reclamation deleted.'];

            case 'reclamation_blur':
                $bankingService->toggleReclamationBlur(
                    $this->requestInt($request, 'idReclamation') ?? 0,
                    (string) $request->request->get('is_blurred', '0') === '1'
                );

                return ['type' => 'success', 'message' => 'Reclamation blur status updated.'];
        }

        return null;
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'reclamation_save':
                $bankingService->saveReclamation($request->request->all(), $this->requestInt($request, 'idReclamation'), $userId);

                return ['type' => 'success', 'message' => 'Reclamation saved.'];

            case 'reclamation_delete':
                $bankingService->deleteReclamation($this->requestInt($request, 'idReclamation') ?? 0, $userId);

                return ['type' => 'success', 'message' => 'Reclamation deleted.'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{
     *   reclamations: array<int, array<string, mixed>>,
     *   transactions: array<int, array<string, mixed>>,
     *   query: array{q:string, sort:string, dir:string, edit_id:?int, view_id:?int},
     *   edit_reclamation:?array<string, mixed>,
     *   view_reclamation:?array<string, mixed>
     * }
     */
    private function buildReclamationDataset(BankingService $bankingService, ?int $userId, array $queryParams): array
    {
        $transactions = $bankingService->listTransactions($userId);
        $transactionMap = [];
        foreach ($transactions as $transaction) {
            $transactionId = (int) ($transaction['idTransaction'] ?? 0);
            if ($transactionId > 0) {
                $transactionMap[$transactionId] = $transaction;
            }
        }

        $allReclamations = array_map(
            fn (array $reclamation): array => $this->enrichReclamation($reclamation, $transactionMap),
            $bankingService->listReclamations($userId)
        );
        $query = $this->normalizeQuery($queryParams);

        $reclamations = array_values(array_filter(
            $allReclamations,
            fn (array $reclamation): bool => $this->matchesSearch($reclamation, $query['q'])
        ));

        usort($reclamations, fn (array $left, array $right): int => $this->compareReclamations($left, $right, $query['sort'], $query['dir']));

        return [
            'reclamations' => $reclamations,
            'transactions' => $transactions,
            'query' => $query,
            'edit_reclamation' => $this->findReclamationById($allReclamations, $query['edit_id']),
            'view_reclamation' => $this->findReclamationById($allReclamations, $query['view_id']),
        ];
    }

    /**
     * @param array<string, mixed> $reclamation
     * @param array<int, array<string, mixed>> $transactionMap
     * @return array<string, mixed>
     */
    private function enrichReclamation(array $reclamation, array $transactionMap): array
    {
        $transactionId = (int) ($reclamation['idTransaction'] ?? 0);
        $transaction = $transactionId > 0 ? ($transactionMap[$transactionId] ?? null) : null;
        $reclamation['linked_transaction'] = $transaction;
        $reclamation['numeroCompte'] = (string) ($transaction['numeroCompte'] ?? '');
        $reclamation['transaction_type'] = (string) ($transaction['typeTransaction'] ?? '');
        $reclamation['transaction_category'] = (string) ($transaction['categorie'] ?? '');

        return $reclamation;
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{q:string, sort:string, dir:string, edit_id:?int, view_id:?int}
     */
    private function normalizeQuery(array $queryParams): array
    {
        $allowedSorts = array_keys($this->reclamationSortFields());
        $query = trim((string) ($queryParams['q'] ?? ''));
        $sort = trim((string) ($queryParams['sort'] ?? 'dateReclamation'));
        $dir = strtolower(trim((string) ($queryParams['dir'] ?? 'desc')));

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'dateReclamation';
        }

        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        return [
            'q' => $query,
            'sort' => $sort,
            'dir' => $dir,
            'edit_id' => $this->queryInt($queryParams['edit_id'] ?? null),
            'view_id' => $this->queryInt($queryParams['view_id'] ?? null),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function reclamationSortFields(): array
    {
        return [
            'idReclamation' => 'ID reclamation',
            'dateReclamation' => 'Date reclamation',
            'typeReclamation' => 'Type reclamation',
            'status' => 'Status',
            'idTransaction' => 'ID transaction',
            'numeroCompte' => 'Numero compte',
            'resolved_user_id' => 'ID utilisateur',
            'user_name' => 'Utilisateur',
            'description' => 'Description',
            'is_inappropriate' => 'Inappropriee',
            'is_blurred' => 'Flou',
        ];
    }

    /**
     * @param array<string, mixed> $reclamation
     */
    private function matchesSearch(array $reclamation, string $query): bool
    {
        $needle = $this->normalizeText($query);
        if ($needle === '') {
            return true;
        }

        $haystack = $this->normalizeText(implode(' ', [
            (string) ($reclamation['idReclamation'] ?? ''),
            (string) ($reclamation['dateReclamation'] ?? ''),
            (string) ($reclamation['typeReclamation'] ?? ''),
            (string) ($reclamation['description'] ?? ''),
            (string) ($reclamation['status'] ?? ''),
            (string) ($reclamation['idTransaction'] ?? ''),
            (string) ($reclamation['numeroCompte'] ?? ''),
            (string) ($reclamation['resolved_user_id'] ?? $reclamation['idUser'] ?? ''),
            (string) ($reclamation['user_name'] ?? ''),
            (string) ($reclamation['transaction_type'] ?? ''),
            (string) ($reclamation['transaction_category'] ?? ''),
            ((int) ($reclamation['is_inappropriate'] ?? 0)) === 1 ? 'oui inappropriate' : 'non',
            ((int) ($reclamation['is_blurred'] ?? 0)) === 1 ? 'oui blurred flou' : 'non',
        ]));

        return str_contains($haystack, $needle);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareReclamations(array $left, array $right, string $sortField, string $direction): int
    {
        $comparison = match ($sortField) {
            'idReclamation' => ((int) ($left['idReclamation'] ?? 0)) <=> ((int) ($right['idReclamation'] ?? 0)),
            'idTransaction' => ((int) ($left['idTransaction'] ?? 0)) <=> ((int) ($right['idTransaction'] ?? 0)),
            'resolved_user_id' => ((int) ($left['resolved_user_id'] ?? $left['idUser'] ?? 0)) <=> ((int) ($right['resolved_user_id'] ?? $right['idUser'] ?? 0)),
            'is_inappropriate' => ((int) ($left['is_inappropriate'] ?? 0)) <=> ((int) ($right['is_inappropriate'] ?? 0)),
            'is_blurred' => ((int) ($left['is_blurred'] ?? 0)) <=> ((int) ($right['is_blurred'] ?? 0)),
            default => strnatcasecmp($this->comparableValue($left, $sortField), $this->comparableValue($right, $sortField)),
        };

        if ($comparison === 0) {
            $comparison = ((int) ($left['idReclamation'] ?? 0)) <=> ((int) ($right['idReclamation'] ?? 0));
        }

        return $direction === 'desc' ? ($comparison * -1) : $comparison;
    }

    /**
     * @param array<string, mixed> $reclamation
     */
    private function comparableValue(array $reclamation, string $field): string
    {
        return trim((string) match ($field) {
            'numeroCompte' => $reclamation['numeroCompte'] ?? '',
            default => $reclamation[$field] ?? '',
        });
    }

    /**
     * @param array<int, array<string, mixed>> $reclamations
     * @return array<string, mixed>|null
     */
    private function findReclamationById(array $reclamations, ?int $id): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        foreach ($reclamations as $reclamation) {
            if ((int) ($reclamation['idReclamation'] ?? 0) === $id) {
                return $reclamation;
            }
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return strtr($normalized, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
        ]);
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
