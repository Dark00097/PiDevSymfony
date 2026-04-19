<?php

namespace App\Controller\Sections;

use App\Service\ActivityService;
use App\Service\BankingService;
use App\Service\BankingMlAssistantService;
use App\Service\GamificationService;
use App\Service\NotificationService;
use Symfony\Component\HttpFoundation\Request;

final class AccountsController
{
    private const ACCOUNT_LIMIT_MIN = 10.0;
    private const ACCOUNT_LIMIT_MAX = 1000.0;

    public function __construct(
        private readonly BankingMlAssistantService $bankingMlAssistantService,
        private readonly NotificationService $notificationService
    )
    {
    }

    public function buildAdminData(BankingService $bankingService, ?Request $request = null, ?int $filterUserId = null): array
    {
        $accounts  = $bankingService->listAccounts($filterUserId);
        $vaults    = $bankingService->listVaults($filterUserId);
        $vaultData = $this->buildVaultData($vaults);

        // FIX PRINCIPAL : lire le panel depuis query string (GET ?panel=ia)
        // Le lien Twig génère : href="...?tab=accounts&panel=ia"
        // On lit UNIQUEMENT $request->query->get('panel') pour être sûr.
        $activePanel = 'compte';
        if ($request !== null) {
            $fromQuery   = trim((string) ($request->query->get('panel', '') ?? ''));
            $fromPost    = trim((string) ($request->request->get('panel', '') ?? ''));
            $activePanel = $fromQuery !== '' ? $fromQuery : ($fromPost !== '' ? $fromPost : 'compte');
        }

        // FIX : normaliser — seulement les valeurs attendues sont acceptées
        if (!in_array($activePanel, ['compte', 'coffre', 'ia'], true)) {
            $activePanel = 'compte';
        }

        $isIaPanel = ($activePanel === 'ia');

        $formErrorsCompte = $request !== null ? $this->getFormErrors($request, 'compte') : [];
        $formDataCompte   = $request !== null ? $this->getFormData($request, 'compte') : [];

        if ($request !== null) {
            $request->getSession()->remove('nexora.form_errors.compte');
            $request->getSession()->remove('nexora.form_data.compte');
        }

        // FIX : construire les données ML SEULEMENT quand le panel IA est actif.
        // Ainsi : cliquer sur "Assistante IA" → déclenche Python → log dans dev.log.
        // Les autres panels (compte, coffre) ne lancent PAS Python.
        $aiAssistant = [];
        if ($isIaPanel) {
            $aiAssistant = $this->bankingMlAssistantService->buildAdminAssistantData($filterUserId);
        }

        return [
            'items'   => $accounts,
            'support' => [
                'users'              => $bankingService->listUsers(),
                'accounts'           => $accounts,
                'all_accounts'       => $accounts,
                'account_stats'      => $this->buildAccountStats($accounts),
                'account_history'    => $request !== null ? $this->getHistory($request) : [],
                'form_errors_compte' => $formErrorsCompte,
                'form_data_compte'   => $formDataCompte,
                'vault_data'         => $vaultData,
                'vault_stats'        => $this->buildVaultStats($vaultData),
                'ai_assistant'       => $aiAssistant,
                'pending_accounts'   => $bankingService->listPendingAccounts(),
                // FIX : exposer active_panel pour que le twig puisse le lire
                // depuis support.active_panel (utilisé ligne 1 du twig)
                'active_panel'       => $activePanel,
            ],
        ];
    }

    /**
     * Enriches vault items with computed progression, bar color and lock state.
     *
     * @param array<int, array<string, mixed>> $vaults
     * @return array<int, array<string, mixed>>
     */
    public function buildVaultData(array $vaults): array
    {
        $result = [];
        foreach ($vaults as $v) {
            $objectif = (float) ($v['objectifMontant'] ?? 0);
            $actuel   = (float) ($v['montantActuel'] ?? 0);

            $pct = $objectif > 0 ? min(round(($actuel / $objectif) * 100, 1), 100) : 0;

            $barColor = match (true) {
                $pct >= 100 => '#22c55e',
                $pct >= 50  => '#f97316',
                default     => '#ef4444',
            };

            $locked    = $pct >= 100;
            $lockIcon  = $locked ? 'fa-lock' : 'fa-lock-open';
            $lockColor = $locked ? '#ef4444' : '#22c55e';

            $result[] = array_merge($v, [
                'progression' => $pct,
                'bar_color'   => $barColor,
                'is_locked'   => $locked,
                'lock_icon'   => $lockIcon,
                'lock_color'  => $lockColor,
            ]);
        }

        return $result;
    }

    /**
     * Computes vault statistics for KPI cards and charts.
     *
     * @param array<int, array<string, mixed>> $vaultData enriched vault data from buildVaultData()
     */
    public function buildVaultStats(array $vaultData): array
    {
        $total       = count($vaultData);
        $totalStored = 0.0;
        $actifs      = 0;
        $rouge       = 0;
        $orange      = 0;
        $vert        = 0;

        foreach ($vaultData as $v) {
            $totalStored += (float) ($v['montantActuel'] ?? 0);
            $pct = (float) ($v['progression'] ?? 0);

            if (strtolower((string) ($v['status'] ?? '')) === 'actif') {
                $actifs++;
            }

            if ($pct >= 100) {
                $vert++;
            } elseif ($pct >= 50) {
                $orange++;
            } else {
                $rouge++;
            }
        }

        return [
            'total'        => $total,
            'total_stored' => $totalStored,
            'actifs'       => $actifs,
            'rouge'        => $rouge,
            'orange'       => $orange,
            'vert'         => $vert,
        ];
    }

    /**
     * Computes per-status statistics for the pie chart.
     */
    public function buildAccountStats(array $accounts): array
    {
        $total  = count($accounts);
        $counts = ['Actif' => 0, 'Fermé' => 0, 'Bloqué' => 0];
        $soldes = ['Actif' => 0.0, 'Fermé' => 0.0, 'Bloqué' => 0.0];

        foreach ($accounts as $a) {
            $raw    = (string) ($a['statutCompte'] ?? '');
            $statut = match (strtolower(trim($raw))) {
                'actif'            => 'Actif',
                'fermé', 'ferme'   => 'Fermé',
                'bloqué', 'bloque' => 'Bloqué',
                default            => null,
            };
            if ($statut !== null) {
                $counts[$statut]++;
                $soldes[$statut] += (float) ($a['solde'] ?? 0);
            }
        }

        return [
            'total'  => $total,
            'counts' => $counts,
            'soldes' => $soldes,
            'colors' => [
                'Actif'  => '#00B4A0',
                'Fermé'  => '#0A2540',
                'Bloqué' => '#B8860B',
            ],
        ];
    }

    public function buildPortalData(
        BankingService $bankingService,
        ActivityService $activityService,
        GamificationService $gamificationService,
        int $userId,
        ?Request $request = null
    ): array {
        $allAccounts  = $bankingService->listAccounts($userId);
        $accountsQuery = $this->resolvePortalAccountQuery($request);
        $accounts     = $this->filterAndSortPortalAccounts($allAccounts, $accountsQuery);
        $vaults       = $bankingService->listVaults($userId);
        $vaultData    = $this->buildVaultData($vaults);

        $formErrorsCompte = $request !== null ? $this->getFormErrors($request, 'compte') : [];
        $formDataCompte   = $request !== null ? $this->getFormData($request, 'compte') : [];
        $formErrorsVault  = $request !== null ? $this->getFormErrors($request, 'vault') : [];
        $formDataVault    = $request !== null ? $this->getFormData($request, 'vault') : [];
        $wheelFeedback    = $request !== null
            ? (array) $request->getSession()->get('nexora.wheel_feedback', ['type' => '', 'message' => ''])
            : ['type' => '', 'message' => ''];

        if ($request !== null) {
            $request->getSession()->remove('nexora.form_errors.compte');
            $request->getSession()->remove('nexora.form_data.compte');
            $request->getSession()->remove('nexora.form_errors.vault');
            $request->getSession()->remove('nexora.form_data.vault');
            $request->getSession()->remove('nexora.wheel_feedback');
        }

        return [
            'items'   => $accounts,
            'support' => [
                'wheel'                  => $gamificationService->getWheelStatus($userId),
                'wheel_security'         => $request !== null
                    ? (array) $request->getSession()->get('nexora.wheel_security', ['locked' => false, 'message' => ''])
                    : ['locked' => false, 'message' => ''],
                'wheel_feedback'         => $wheelFeedback,
                'activity'               => $activityService->listRecent($userId, 40),
                'account_history'        => $request !== null ? $this->getHistory($request) : [],
                'all_accounts'           => $allAccounts,
                'account_query'          => $accountsQuery,
                'account_filter_counts'  => $this->buildPortalAccountTypeCounts($allAccounts),
                'filtered_account_count' => count($accounts),
                'vaults'                 => $vaultData,
                'vault_data'             => $vaultData,
                'form_errors_compte'     => $formErrorsCompte,
                'form_data_compte'       => $formDataCompte,
                'form_errors_vault'      => $formErrorsVault,
                'form_data_vault'        => $formDataVault,
            ],
        ];
    }

    /**
     * @return array{q:string, search_in:string, filter:string, sort:string, dir:string}
     */
    private function resolvePortalAccountQuery(?Request $request): array
    {
        $allowedSearchFields = ['all', 'id', 'numero', 'solde', 'date', 'statut', 'retrait', 'virement', 'type'];
        $allowedFilters      = ['all', 'courant', 'professionnel', 'epargne'];
        $allowedSorts        = ['id', 'numero', 'solde', 'date', 'statut', 'retrait', 'virement', 'type'];

        $query    = trim((string) ($request?->query->get('q') ?? ''));
        $searchIn = strtolower(trim((string) ($request?->query->get('search_in') ?? 'all')));
        $filter   = strtolower(trim((string) ($request?->query->get('filter') ?? 'all')));
        $sort     = strtolower(trim((string) ($request?->query->get('sort') ?? '')));
        $dir      = strtolower(trim((string) ($request?->query->get('dir') ?? 'asc')));

        if (!in_array($searchIn, $allowedSearchFields, true)) {
            $searchIn = 'all';
        }
        if (!in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = '';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        return [
            'q'         => $query,
            'search_in' => $searchIn,
            'filter'    => $filter,
            'sort'      => $sort,
            'dir'       => $dir,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $accounts
     * @param array{q:string, search_in:string, filter:string, sort:string, dir:string} $query
     * @return array<int, array<string, mixed>>
     */
    private function filterAndSortPortalAccounts(array $accounts, array $query): array
    {
        $filtered = array_values(array_filter(
            $accounts,
            fn (array $account): bool => $this->matchesPortalAccountFilter($account, $query['filter'])
                && $this->matchesPortalAccountSearch($account, $query['q'], $query['search_in'])
        ));

        if ($query['sort'] === '') {
            return $filtered;
        }

        $direction = $query['dir'] === 'desc' ? -1 : 1;

        usort($filtered, function (array $left, array $right) use ($query, $direction): int {
            $comparison = match ($query['sort']) {
                'id'       => ((int) ($left['idCompte'] ?? 0)) <=> ((int) ($right['idCompte'] ?? 0)),
                'solde'    => ((float) ($left['solde'] ?? 0)) <=> ((float) ($right['solde'] ?? 0)),
                'date'     => $this->portalAccountDateValue($left) <=> $this->portalAccountDateValue($right),
                'statut'   => strnatcmp($this->portalAccountSearchValue($left, 'statut'), $this->portalAccountSearchValue($right, 'statut')),
                'retrait'  => ((float) ($left['plafondRetrait'] ?? 0)) <=> ((float) ($right['plafondRetrait'] ?? 0)),
                'virement' => ((float) ($left['plafondVirement'] ?? 0)) <=> ((float) ($right['plafondVirement'] ?? 0)),
                'type'     => strnatcmp($this->portalAccountSearchValue($left, 'type'), $this->portalAccountSearchValue($right, 'type')),
                default    => strnatcmp($this->portalAccountSearchValue($left, 'numero'), $this->portalAccountSearchValue($right, 'numero')),
            };

            return $comparison * $direction;
        });

        return $filtered;
    }

    /**
     * @param array<string, mixed> $account
     */
    private function matchesPortalAccountFilter(array $account, string $filter): bool
    {
        if ($filter === 'all') {
            return true;
        }

        $type = $this->normalizePortalAccountText((string) ($account['typeCompte'] ?? ''));

        return match ($filter) {
            'courant'       => str_contains($type, 'cour'),
            'professionnel' => str_contains($type, 'prof'),
            'epargne'       => str_contains($type, 'epa'),
            default         => true,
        };
    }

    /**
     * @param array<string, mixed> $account
     */
    private function matchesPortalAccountSearch(array $account, string $query, string $searchIn): bool
    {
        $needle = $this->normalizePortalAccountText($query);
        if ($needle == '') {
            return true;
        }

        if ($searchIn === 'all') {
            foreach (['id', 'numero', 'solde', 'date', 'statut', 'retrait', 'virement', 'type'] as $field) {
                if (str_contains($this->portalAccountSearchValue($account, $field), $needle)) {
                    return true;
                }
            }

            return false;
        }

        return str_contains($this->portalAccountSearchValue($account, $searchIn), $needle);
    }

    /**
     * @param array<string, mixed> $account
     */
    private function portalAccountSearchValue(array $account, string $field): string
    {
        $value = match ($field) {
            'id'       => (string) ($account['idCompte'] ?? ''),
            'numero'   => (string) ($account['numeroCompte'] ?? ''),
            'solde'    => number_format((float) ($account['solde'] ?? 0), 2, '.', ' '),
            'date'     => (string) ($account['dateOuverture'] ?? ''),
            'statut'   => (string) ($account['statutCompte'] ?? ''),
            'retrait'  => number_format((float) ($account['plafondRetrait'] ?? 0), 2, '.', ' '),
            'virement' => number_format((float) ($account['plafondVirement'] ?? 0), 2, '.', ' '),
            'type'     => (string) ($account['typeCompte'] ?? ''),
            default    => '',
        };

        return $this->normalizePortalAccountText($value);
    }

    /**
     * @param array<string, mixed> $account
     */
    private function portalAccountDateValue(array $account): int
    {
        $rawDate   = (string) ($account['dateOuverture'] ?? '');
        $timestamp = strtotime($rawDate);

        return $timestamp !== false ? $timestamp : 0;
    }

    private function normalizePortalAccountText(string $value): string
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

    /**
     * @param array<int, array<string, mixed>> $accounts
     * @return array{all:int,courant:int,professionnel:int,epargne:int}
     */
    private function buildPortalAccountTypeCounts(array $accounts): array
    {
        $counts = [
            'all'           => count($accounts),
            'courant'       => 0,
            'professionnel' => 0,
            'epargne'       => 0,
        ];

        foreach ($accounts as $account) {
            $type = $this->normalizePortalAccountText((string) ($account['typeCompte'] ?? ''));
            if (str_contains($type, 'cour')) {
                $counts['courant']++;
            } elseif (str_contains($type, 'prof')) {
                $counts['professionnel']++;
            } elseif (str_contains($type, 'epa')) {
                $counts['epargne']++;
            }
        }

        return $counts;
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService, ?array $currentUser = null): ?array
    {
        switch ($action) {
            case 'account_save':
                $data   = $request->request->all();
                $errors = $this->validateCompte($data, $bankingService);
                if ($errors !== []) {
                    $request->getSession()->set('nexora.form_errors.compte', $errors);
                    $request->getSession()->set('nexora.form_data.compte', $data);

                    return ['type' => 'error', 'message' => 'Veuillez corriger les erreurs du formulaire de compte.'];
                }

                $request->getSession()->remove('nexora.form_errors.compte');
                $request->getSession()->remove('nexora.form_data.compte');

                try {
                    $bankingService->saveAccount($data, $this->requestInt($request, 'idCompte'));
                } catch (\Throwable $exception) {
                    $request->getSession()->set('nexora.form_data.compte', $data);

                    return ['type' => 'error', 'message' => $exception->getMessage()];
                }

                $this->logHistory($request, 'account_save', $data);

                return ['type' => 'success', 'message' => 'Account saved.'];

            case 'account_delete':
                $id = $this->requestInt($request, 'idCompte') ?? 0;
                $bankingService->deleteAccount($id);
                $this->logHistory($request, 'account_delete', ['idCompte' => $id]);

                return ['type' => 'success', 'message' => 'Account deleted.'];

            case 'account_accept':
                $id = $this->requestInt($request, 'idCompte') ?? 0;
                $bankingService->validateAccount($id, true);
                $this->logHistory($request, 'account_accept', ['idCompte' => $id]);

                return ['type' => 'success', 'message' => 'Compte accepté et client notifié.'];

            case 'account_refuse':
                $id = $this->requestInt($request, 'idCompte') ?? 0;
                $bankingService->validateAccount($id, false);
                $this->logHistory($request, 'account_refuse', ['idCompte' => $id]);

                return ['type' => 'success', 'message' => 'Compte refusé et client notifié.'];

            case 'send_ai_analysis':
                $clientId = $this->requestInt($request, 'userId');
                if ($clientId === null || $clientId <= 0) {
                    return ['type' => 'error', 'message' => 'Utilisateur invalide.'];
                }

                $aiData = $this->bankingMlAssistantService->buildAdminAssistantData($clientId);
                $profile = null;
                foreach ($aiData['profiles'] ?? [] as $p) {
                    if ((int)$p['client_id'] === $clientId) {
                        $profile = $p;
                        break;
                    }
                }

                if (!$profile) {
                    return ['type' => 'error', 'message' => 'Aucune donnée IA disponible pour ce client.'];
                }

                $name = $profile['client_name'] ?? 'Client';
                $score = $profile['risk_score'] ?? 0;
                $label = $profile['profile_label'] ?? 'Profil';
                $analysis = $profile['analysis'] ?? 'Analyse indisponible.';
                $recommendations = $profile['recommendations'] ?? [];
                $alerts = $profile['alerts'] ?? [];

                $message = "### Analyse de votre profil financier par NEXORA AI\n\n";
                $message .= "**Segment :** $label\n";
                $message .= "**Score de risque :** $score/100\n\n";
                $message .= "#### Analyse détaillée\n$analysis\n\n";

                if (!empty($alerts)) {
                    $message .= "#### Alertes de sécurité & fraude\n";
                    foreach ($alerts as $a) {
                        $message .= "- **" . ($a['title'] ?? 'Alerte') . " :** " . ($a['text'] ?? '') . "\n";
                    }
                    $message .= "\n";
                }

                if (!empty($recommendations)) {
                    $message .= "#### Recommandations personnalisées\n";
                    foreach ($recommendations as $r) {
                        $message .= "- $r\n";
                    }
                    $message .= "\n";
                }

                $message .= "Ces recommandations sont générées automatiquement pour vous aider à mieux gérer votre situation financière.";

                $this->notificationService->createNotification(
                    $clientId,
                    null,
                    $clientId,
                    'AI_ANALYSIS',
                    'Analyse IA de votre compte',
                    $message,
                    true
                );

                return ['type' => 'success', 'message' => "L'analyse IA a été envoyée avec succès à $name."];
        }

        return null;
    }

    public function buildAccountDetail(BankingService $bankingService, int $idCompte): ?array
    {
        $accounts = $bankingService->listAccounts();
        $account  = null;
        foreach ($accounts as $a) {
            if ((int) ($a['idCompte'] ?? 0) === $idCompte) {
                $account = $a;
                break;
            }
        }

        if ($account === null) {
            return null;
        }

        $allVaults    = $bankingService->listVaults();
        $linkedVaults = array_values(array_filter(
            $allVaults,
            fn ($v) => (int) ($v['idCompte'] ?? 0) === $idCompte
        ));

        $enrichedVaults = $this->buildVaultData($linkedVaults);
        $vaultStats     = $this->buildVaultStats($enrichedVaults);

        return [
            'account'     => $account,
            'vaults'      => $enrichedVaults,
            'vault_stats' => $vaultStats,
        ];
    }

    public function buildAccountPdf(BankingService $bankingService, \App\Service\ExportService $exportService, ?int $idCompte): array
    {
        if ($idCompte !== null) {
            $accounts = $bankingService->listAccounts();
            $account  = null;
            foreach ($accounts as $a) {
                if ((int) ($a['idCompte'] ?? 0) === $idCompte) {
                    $account = $a;
                    break;
                }
            }

            if ($account === null) {
                return ['', ''];
            }

            $headers  = ['Champ', 'Valeur'];
            $rows     = [
                ['ID Compte',         (string) ($account['idCompte'] ?? '—')],
                ['Numéro de Compte',  (string) ($account['numeroCompte'] ?? '—')],
                ['Solde',             number_format((float) ($account['solde'] ?? 0), 2, '.', ' ') . ' DT'],
                ['Date d\'Ouverture', (string) ($account['dateOuverture'] ?? '—')],
                ['Statut',            (string) ($account['statutCompte'] ?? '—')],
                ['Plafond Retrait',   number_format((float) ($account['plafondRetrait'] ?? 0), 2, '.', ' ') . ' DT'],
                ['Plafond Virement',  number_format((float) ($account['plafondVirement'] ?? 0), 2, '.', ' ') . ' DT'],
                ['Type de Compte',    (string) ($account['typeCompte'] ?? '—')],
            ];
            $stats    = [
                ['label' => 'N° Compte', 'value' => (string) ($account['numeroCompte'] ?? '—')],
                ['label' => 'Solde',     'value' => number_format((float) ($account['solde'] ?? 0), 2, '.', ' ') . ' DT'],
                ['label' => 'Statut',    'value' => (string) ($account['statutCompte'] ?? '—')],
            ];
            $title    = sprintf('Fiche Compte — %s', (string) ($account['numeroCompte'] ?? '#' . $idCompte));
            $subtitle = sprintf('Détail complet du compte bancaire #%d exporté depuis Nexora.', $idCompte);
            $filename = sprintf('nexora-compte-%d.pdf', $idCompte);
        } else {
            $accounts   = $bankingService->listAccounts();
            $headers    = ['ID', 'N° Compte', 'Solde (DT)', 'Date Ouv.', 'Statut', 'Plaf. Retrait', 'Plaf. Virement', 'Type'];
            $rows       = [];
            $totalSolde = 0.0;
            foreach ($accounts as $a) {
                $totalSolde += (float) ($a['solde'] ?? 0);
                $rows[] = [
                    (string) ($a['idCompte'] ?? ''),
                    (string) ($a['numeroCompte'] ?? ''),
                    number_format((float) ($a['solde'] ?? 0), 2, '.', ' '),
                    (string) ($a['dateOuverture'] ?? ''),
                    (string) ($a['statutCompte'] ?? ''),
                    number_format((float) ($a['plafondRetrait'] ?? 0), 2, '.', ' '),
                    number_format((float) ($a['plafondVirement'] ?? 0), 2, '.', ' '),
                    (string) ($a['typeCompte'] ?? ''),
                ];
            }
            $actifs   = count(array_filter($accounts, fn ($a) => strtolower((string) ($a['statutCompte'] ?? '')) === 'actif'));
            $stats    = [
                ['label' => 'Total comptes',  'value' => (string) count($accounts)],
                ['label' => 'Comptes actifs', 'value' => (string) $actifs],
                ['label' => 'Solde total',    'value' => number_format($totalSolde, 2, '.', ' ') . ' DT'],
            ];
            $title    = 'Rapport Comptes Bancaires';
            $subtitle = 'Export complet de tous les comptes bancaires depuis Nexora.';
            $filename = 'nexora-comptes-all.pdf';
        }

        return [
            $exportService->buildPdf($title, $headers, $rows, $stats, $subtitle, '#11b7aa'),
            $filename,
        ];
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'account_save':
                $data = $request->request->all();
                if (($data['idUser'] ?? '') === '') {
                    $data['idUser'] = (string) $userId;
                }
                if (($data['idCompte'] ?? '') === '') {
                    $data['statutCompte'] = 'En attente';
                }
                $errors = $this->validateCompte($data, $bankingService);
                if ($errors !== []) {
                    $request->getSession()->set('nexora.form_errors.compte', $errors);
                    $request->getSession()->set('nexora.form_data.compte', $data);

                    return ['type' => 'validation_error', 'message' => 'Veuillez corriger les erreurs du formulaire.'];
                }
                $request->getSession()->remove('nexora.form_errors.compte');
                $request->getSession()->remove('nexora.form_data.compte');
                $accountId = $this->requestInt($request, 'idCompte');
                if ($accountId !== null) {
                    $existingAccount = null;
                    foreach ($bankingService->listAccounts($userId) as $account) {
                        if ((int) ($account['idCompte'] ?? 0) === $accountId) {
                            $existingAccount = $account;
                            break;
                        }
                    }

                    if ($existingAccount === null) {
                        return ['type' => 'error', 'message' => 'Compte introuvable ou inaccessible.'];
                    }

                    $data['idUser'] = (string) $userId;
                    $savedAccountId = $bankingService->saveAccount($data, $accountId, null);
                } else {
                    $savedAccountId = $bankingService->saveAccount($data, null, $userId);
                }
                $request->getSession()->set('nexora.last_saved_account_id', $savedAccountId);
                $this->logHistory($request, 'account_save', $data);

                return $accountId !== null
                    ? ['type' => 'success', 'message' => 'Compte mis à jour avec succès.']
                    : ['type' => 'success', 'message' => 'Demande de création envoyée à l\'administration avec succès.'];

            case 'account_delete':
                $id = $this->requestInt($request, 'idCompte') ?? 0;
                $bankingService->deleteAccount($id, $userId);
                $this->logHistory($request, 'account_delete', ['idCompte' => $id]);

                return ['type' => 'success', 'message' => 'Compte supprimé.'];

            case 'vault_save':
                $data = $request->request->all();
                if (($data['idUser'] ?? '') === '') {
                    $data['idUser'] = (string) $userId;
                }
                $errors = $this->validateVault($data);
                if ($errors !== []) {
                    $request->getSession()->set('nexora.form_errors.vault', $errors);
                    $request->getSession()->set('nexora.form_data.vault', $data);

                    return ['type' => 'validation_error', 'message' => 'Veuillez corriger les erreurs du formulaire coffre.'];
                }
                $request->getSession()->remove('nexora.form_errors.vault');
                $request->getSession()->remove('nexora.form_data.vault');
                $vaultId = $this->requestInt($request, 'idCoffre');
                if ($vaultId !== null) {
                    $existingVault = null;
                    foreach ($bankingService->listVaults($userId) as $vault) {
                        if ((int) ($vault['idCoffre'] ?? 0) === $vaultId) {
                            $existingVault = $vault;
                            break;
                        }
                    }

                    if ($existingVault === null) {
                        return ['type' => 'error', 'message' => 'Coffre introuvable ou inaccessible.'];
                    }

                    $data['idUser'] = (string) $userId;
                    if (($data['idCompte'] ?? '') === '') {
                        $data['idCompte'] = (string) ($existingVault['idCompte'] ?? '');
                    }

                    $bankingService->saveVault($data, $vaultId, null);
                } else {
                    $bankingService->saveVault($data, null, $userId);
                }
                $this->logHistory($request, 'vault_save', $data);

                return ['type' => 'success', 'message' => 'Coffre enregistré avec succès.'];

            case 'vault_delete':
                $id = $this->requestInt($request, 'idCoffre') ?? 0;
                if ($id > 0) {
                    $existingVault = null;
                    foreach ($bankingService->listVaults($userId) as $vault) {
                        if ((int) ($vault['idCoffre'] ?? 0) === $id) {
                            $existingVault = $vault;
                            break;
                        }
                    }

                    if ($existingVault === null) {
                        return ['type' => 'error', 'message' => 'Coffre introuvable ou inaccessible.'];
                    }

                    $bankingService->deleteVault($id, null);
                }
                $this->logHistory($request, 'vault_delete', ['idCoffre' => $id]);

                return ['type' => 'success', 'message' => 'Coffre supprimé.'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validateCompte(array $data, BankingService $bankingService): array
    {
        $errors          = [];
        $today           = new \DateTimeImmutable('today');
        $userId          = (int) ($data['idUser'] ?? 0);
        $editId          = (int) ($data['idCompte'] ?? 0);
        $existingAccount = null;

        if ($editId > 0) {
            foreach ($bankingService->listAccounts() as $account) {
                if ((int) ($account['idCompte'] ?? 0) === $editId) {
                    $existingAccount = $account;
                    break;
                }
            }
        }

        if ($userId <= 0) {
            $errors['idUser'] = 'Veuillez selectionner un utilisateur.';
        } else {
            $userExists = false;
            foreach ($bankingService->listUsers() as $user) {
                if ((int) ($user['idUser'] ?? 0) === $userId) {
                    $userExists = true;
                    break;
                }
            }

            if (!$userExists) {
                $errors['idUser'] = 'Utilisateur selectionne introuvable.';
            }
        }

        $num = trim((string) ($data['numeroCompte'] ?? ''));
        if ($num === '') {
            $errors['numeroCompte'] = 'Le numéro de compte est obligatoire.';
        } elseif (!preg_match('/^CB-\d{3,}$/', $num)) {
            $errors['numeroCompte'] = 'Le numéro de compte doit être au format CB-XXX (ex : CB-123).';
        } else {
            foreach ($bankingService->listAccounts() as $a) {
                if ((string) ($a['numeroCompte'] ?? '') === $num && (int) ($a['idCompte'] ?? 0) !== $editId) {
                    $errors['numeroCompte'] = 'Ce numéro de compte existe déjà.';
                    break;
                }
            }
        }

        $solde = $data['solde'] ?? '';
        if ($solde === '' || $solde === null) {
            $errors['solde'] = 'Le solde est obligatoire.';
        } elseif ((float) $solde < 0) {
            $errors['solde'] = 'Le solde doit être un nombre positif ou égal à 0.';
        }

        $dateVal = trim((string) ($data['dateOuverture'] ?? ''));
        if ($dateVal !== '' && !$this->isValidDateValue($dateVal)) {
            $errors['dateOuverture'] = "La date d'ouverture est invalide.";
        } elseif ($dateVal !== '') {
            if ($existingAccount !== null) {
                $expectedDate = trim((string) ($existingAccount['dateOuverture'] ?? ''));
                if ($expectedDate === '') {
                    $expectedDate = $today->format('Y-m-d');
                }

                if ($dateVal !== $expectedDate) {
                    $errors['dateOuverture'] = sprintf(
                        "La date d'ouverture est fixee automatiquement au %s et ne peut pas etre modifiee.",
                        $expectedDate
                    );
                }
            } else {
                $submittedDate = new \DateTimeImmutable($dateVal);
                if ($submittedDate > $today) {
                    $errors['dateOuverture'] = "La date d'ouverture ne peut pas etre dans le futur.";
                }
            }
        }

        $statut = trim((string) ($data['statutCompte'] ?? ''));
        if ($statut === '' && $editId > 0) {
            $errors['statutCompte'] = 'Veuillez sélectionner un statut.';
        }

        $retrait = $data['plafondRetrait'] ?? '';
        if ($retrait !== '' && $retrait !== null && !$this->isWithinAccountLimitRange((float) $retrait)) {
            $errors['plafondRetrait'] = sprintf(
                'Le plafond de retrait doit etre compris entre %.0f DT et %.0f DT.',
                self::ACCOUNT_LIMIT_MIN,
                self::ACCOUNT_LIMIT_MAX
            );
        }

        $virement = $data['plafondVirement'] ?? '';
        if ($virement !== '' && $virement !== null && !$this->isWithinAccountLimitRange((float) $virement)) {
            $errors['plafondVirement'] = sprintf(
                'Le plafond de virement doit etre compris entre %.0f DT et %.0f DT.',
                self::ACCOUNT_LIMIT_MIN,
                self::ACCOUNT_LIMIT_MAX
            );
        }

        $type = trim((string) ($data['typeCompte'] ?? ''));
        if ($type === '') {
            $errors['typeCompte'] = 'Veuillez sélectionner un type de compte.';
        }

        return $errors;
    }

    private function isValidDateValue(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function isWithinAccountLimitRange(float $value): bool
    {
        return $value >= self::ACCOUNT_LIMIT_MIN && $value <= self::ACCOUNT_LIMIT_MAX;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validateVault(array $data): array
    {
        $errors       = [];
        $today        = new \DateTimeImmutable('today');
        $dateCreation = null;

        $nom = trim((string) ($data['nom'] ?? ''));
        if (strlen($nom) < 3) {
            $errors['nom'] = 'Le nom du coffre est obligatoire et doit contenir au moins 3 caractères.';
        }

        $objectif = $data['objectifMontant'] ?? '';
        if ($objectif === '' || $objectif === null) {
            $errors['objectifMontant'] = "L'objectif de montant est obligatoire.";
        } elseif ((float) $objectif <= 0) {
            $errors['objectifMontant'] = "L'objectif de montant doit être supérieur à 0.";
        }

        $actuel = $data['montantActuel'] ?? '';
        if ($actuel === '' || $actuel === null) {
            $errors['montantActuel'] = 'Le montant actuel est obligatoire.';
        } elseif ((float) $actuel < 0) {
            $errors['montantActuel'] = 'Le montant actuel doit être positif ou égal à 0.';
        } elseif ($objectif !== '' && $objectif !== null && (float) $actuel > (float) $objectif) {
            $errors['montantActuel'] = "Le montant actuel ne doit pas dépasser l'objectif de montant.";
        }

        $dc = trim((string) ($data['dateCreation'] ?? ''));
        if ($dc === '') {
            $errors['dateCreation'] = 'La date de création est obligatoire.';
        } else {
            try {
                $dateCreation = new \DateTimeImmutable($dc);
                if ($dateCreation > $today) {
                    $errors['dateCreation'] = 'La date de création ne doit pas être supérieure à la date actuelle.';
                }
            } catch (\Exception) {
                $errors['dateCreation'] = 'La date de création est invalide.';
            }
        }

        $dobj = trim((string) ($data['dateObjectifs'] ?? ''));
        if ($dobj !== '') {
            try {
                $dateObjectif = new \DateTimeImmutable($dobj);
                if ($dateCreation !== null && $dateObjectif < $dateCreation) {
                    $errors['dateObjectifs'] = "La date d'objectif doit être supérieure ou égale à la date de création.";
                }
            } catch (\Exception) {
                $errors['dateObjectifs'] = "La date d'objectif est invalide.";
            }
        }

        $status = trim((string) ($data['status'] ?? ''));
        if ($status === '') {
            $errors['status'] = 'Veuillez sélectionner un statut.';
        }

        if ($objectif !== '' && $objectif !== null && (float) $objectif > 0 && $actuel !== '' && $actuel !== null) {
            $pct = ((float) $actuel / (float) $objectif) * 100;
            if ($pct >= 100 && ($data['estVerrouille'] ?? '0') === '0') {
                $errors['estVerrouille'] = "Le coffre doit être verrouillé lorsque l'objectif est atteint.";
            }
        }

        if ((int) ($data['idCompte'] ?? 0) <= 0) {
            $errors['idCompte'] = 'Veuillez sélectionner un compte associé.';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public function getFormErrors(Request $request, string $formKey): array
    {
        return (array) $request->getSession()->get('nexora.form_errors.' . $formKey, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormData(Request $request, string $formKey): array
    {
        return (array) $request->getSession()->get('nexora.form_data.' . $formKey, []);
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

    private function logHistory(Request $request, string $action, array $data): void
    {
        $session = $request->getSession();
        /** @var array<int, array<string, mixed>> $history */
        $history = $session->get('nexora.accounts_history', []);

        $labels = [
            'account_add'    => 'Ajouter compte',
            'account_edit'   => 'Modifier compte',
            'account_delete' => 'Supprimer compte',
            'vault_add'      => 'Ajouter coffre',
            'vault_edit'     => 'Modifier coffre',
            'vault_delete'   => 'Supprimer coffre',
        ];

        $resolvedAction = $action;
        if ($action === 'account_save') {
            $resolvedAction = ($data['idCompte'] ?? '') !== '' ? 'account_edit' : 'account_add';
        } elseif ($action === 'vault_save') {
            $resolvedAction = ($data['idCoffre'] ?? '') !== '' ? 'vault_edit' : 'vault_add';
        }

        $idCompte     = (string) ($data['idCompte'] ?? '—');
        $idCoffre     = (string) ($data['idCoffre'] ?? '—');
        $numero       = (string) ($data['numeroCompte'] ?? '');
        $statut       = (string) ($data['statutCompte'] ?? '');
        $type         = (string) ($data['typeCompte'] ?? '');
        $solde        = isset($data['solde']) ? number_format((float) $data['solde'], 2, '.', ' ') . ' DT' : '—';
        $vaultNom     = (string) ($data['nom'] ?? '');
        $vaultStatus  = (string) ($data['status'] ?? '');
        $vaultCurrent = isset($data['montantActuel']) ? number_format((float) $data['montantActuel'], 2, '.', ' ') . ' DT' : '—';
        $vaultTarget  = isset($data['objectifMontant']) ? number_format((float) $data['objectifMontant'], 2, '.', ' ') . ' DT' : '—';

        if (str_contains($resolvedAction, 'vault')) {
            $detail = $vaultNom !== '' ? $vaultNom : ($idCoffre !== '—' ? 'Coffre #' . $idCoffre : 'Coffre');
            if ($vaultStatus !== '') {
                $detail .= ' · ' . $vaultStatus;
            }
            if ($idCompte !== '—') {
                $detail .= ' · Compte #' . $idCompte;
            }
            if ($vaultCurrent !== '—') {
                $detail .= ' · Actuel ' . $vaultCurrent;
            }
            if ($vaultTarget !== '—') {
                $detail .= ' / Obj. ' . $vaultTarget;
            }
        } else {
            $detail = $numero !== '' ? $numero : ($idCompte !== '—' ? '#' . $idCompte : '—');
            if ($statut !== '') {
                $detail .= ' · ' . $statut;
            }
            if ($type !== '') {
                $detail .= ' · ' . $type;
            }
            if ($solde !== '—') {
                $detail .= ' · ' . $solde;
            }
        }

        array_unshift($history, [
            'action'    => $labels[$resolvedAction] ?? $resolvedAction,
            'detail'    => $detail,
            'idCompte'  => $idCompte,
            'idCoffre'  => $idCoffre,
            'timestamp' => date('d/m/Y H:i:s'),
            'type'      => str_contains($resolvedAction, 'delete') ? 'delete' : (str_contains($resolvedAction, 'vault') ? 'vault' : 'save'),
        ]);

        $session->set('nexora.accounts_history', array_slice($history, 0, 50));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(Request $request): array
    {
        return (array) $request->getSession()->get('nexora.accounts_history', []);
    }
}