<?php

namespace App\Controller\Sections;

use App\Service\ActivityService;
use App\Service\BankingService;
use App\Service\ExportService;
use Symfony\Component\HttpFoundation\Request;

final class GarantiesController
{
    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    public function buildAdminData(BankingService $bankingService): array
    {
        $garanties = $bankingService->listGaranties();

        return [
            'items' => $garanties,
            'support' => [
                'garanties'           => $garanties,
                'credits'             => $bankingService->listCredits(),
                'garantie_type_stats' => $bankingService->getGarantieTypeDistribution(),
                'garantie_stats'      => $this->buildGarantieStats($garanties),
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'garantie_save':
                $data = $request->request->all();
                $bankingService->saveGarantie($data, $this->requestInt($request, 'idGarantie'));
                $this->logHistory($request, 'garantie_save', $data);

                return ['type' => 'success', 'message' => 'Garantie saved.'];

            case 'garantie_delete':
                $id = $this->requestInt($request, 'idGarantie') ?? 0;
                $bankingService->deleteGarantie($id);
                $this->logHistory($request, 'garantie_delete', ['idGarantie' => $id]);

                return ['type' => 'success', 'message' => 'Garantie deleted.'];
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Portal
    // -------------------------------------------------------------------------

    public function buildPortalData(BankingService $bankingService, int $userId, ?Request $request = null): array
    {
        $allGaranties    = $bankingService->listGaranties($userId);
        $garantieQuery   = $this->resolvePortalGarantieQuery($request);
        $garanties       = $this->filterAndSortPortalGaranties($allGaranties, $garantieQuery);

        $formErrorsGarantie = $request !== null ? $this->getFormErrors($request, 'garantie') : [];
        $formDataGarantie   = $request !== null ? $this->getFormData($request, 'garantie') : [];

        if ($request !== null) {
            $request->getSession()->remove('nexora.form_errors.garantie');
            $request->getSession()->remove('nexora.form_data.garantie');
        }

        return [
            'items' => $garanties,
            'support' => [
                'garanties'                => $garanties,
                'all_garanties'            => $allGaranties,
                'credits'                  => $bankingService->listCredits($userId),
                'garantie_query'           => $garantieQuery,
                'garantie_filter_counts'   => $this->buildPortalGarantieStatusCounts($allGaranties),
                'filtered_garantie_count'  => count($garanties),
                'garantie_history'         => $request !== null ? $this->getHistory($request) : [],
                'garantie_stats'           => $this->buildGarantieStats($allGaranties),
                'form_errors_garantie'     => $formErrorsGarantie,
                'form_data_garantie'       => $formDataGarantie,
                'garantie_form_feedback'   => [
                    'errors' => $formErrorsGarantie,
                    'input' => $formDataGarantie,
                ],
            ],
        ];
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'garantie_save':
                $data = $request->request->all();
                if (($data['idUser'] ?? '') === '') {
                    $data['idUser'] = (string) $userId;
                }

                $errors = $this->validateGarantie($data, $bankingService, $userId);
                if ($errors !== []) {
                    $request->getSession()->set('nexora.form_errors.garantie', $errors);
                    $request->getSession()->set('nexora.form_data.garantie', $data);

                    return ['type' => 'validation_error', 'message' => 'Veuillez corriger les erreurs du formulaire garantie.'];
                }

                $request->getSession()->remove('nexora.form_errors.garantie');
                $request->getSession()->remove('nexora.form_data.garantie');

                $garantieId = $this->requestInt($request, 'idGarantie');
                if ($garantieId !== null) {
                    $existingGarantie = null;
                    foreach ($bankingService->listGaranties($userId) as $garantie) {
                        if ((int) ($garantie['idGarantie'] ?? 0) === $garantieId) {
                            $existingGarantie = $garantie;
                            break;
                        }
                    }
                    if ($existingGarantie === null) {
                        return ['type' => 'error', 'message' => 'Garantie introuvable ou inaccessible.'];
                    }
                    $data['idUser'] = (string) $userId;
                    $bankingService->saveGarantie($data, $garantieId, null);
                } else {
                    $bankingService->saveGarantie($data, null, $userId);
                }

                $this->logHistory($request, 'garantie_save', $data);

                return ['type' => 'success', 'message' => 'Garantie enregistrée avec succès.'];

            case 'garantie_delete':
                $id = $this->requestInt($request, 'idGarantie') ?? 0;
                if ($id > 0) {
                    $existingGarantie = null;
                    foreach ($bankingService->listGaranties($userId) as $garantie) {
                        if ((int) ($garantie['idGarantie'] ?? 0) === $id) {
                            $existingGarantie = $garantie;
                            break;
                        }
                    }
                    if ($existingGarantie === null) {
                        return ['type' => 'error', 'message' => 'Garantie introuvable ou inaccessible.'];
                    }
                    $bankingService->deleteGarantie($id, null);
                }
                $this->logHistory($request, 'garantie_delete', ['idGarantie' => $id]);

                return ['type' => 'success', 'message' => 'Garantie supprimée.'];
        }

        return null;
    }

    /**
     * Validates garantie data. Returns field-keyed error messages.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validateGarantie(array $data, BankingService $bankingService, int $userId): array
    {
        $errors = [];
        $today = new \DateTimeImmutable('today');
        $allowedTypes = [
            'Hypotheque immobiliere',
            'Hypothèque immobilière',
            'Titre vehicule',
            'Titre véhicule',
            'Caution personnelle',
            'Garantie bancaire',
            'Police assurance',
            'Nantissement',
            'Autre garantie',
        ];

        $typeGarantie = trim((string) ($data['typeGarantie'] ?? ''));
        if ($typeGarantie === '') {
            $errors['typeGarantie'] = 'Le type de garantie est obligatoire.';
        } elseif (!in_array($typeGarantie, $allowedTypes, true)) {
            $errors['typeGarantie'] = 'Veuillez selectionner un type de garantie valide.';
        }

        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            $errors['description'] = 'La description est obligatoire.';
        } elseif (mb_strlen($description, 'UTF-8') < 10) {
            $errors['description'] = 'La description doit contenir au moins 10 caracteres.';
        } elseif (mb_strlen($description, 'UTF-8') > 1000) {
            $errors['description'] = 'La description ne peut pas depasser 1000 caracteres.';
        }

        $adresseBien = trim((string) ($data['adresseBien'] ?? ''));
        if ($adresseBien === '') {
            $errors['adresseBien'] = "L'adresse du bien est obligatoire.";
        } elseif (mb_strlen($adresseBien, 'UTF-8') < 5) {
            $errors['adresseBien'] = "L'adresse doit contenir au moins 5 caracteres.";
        } elseif (mb_strlen($adresseBien, 'UTF-8') > 255) {
            $errors['adresseBien'] = "L'adresse du bien ne peut pas depasser 255 caracteres.";
        }

        $estimatedRaw = $data['valeurEstimee'] ?? '';
        $estimated = (float) $estimatedRaw;
        if ($estimatedRaw === '' || $estimatedRaw === null) {
            $errors['valeurEstimee'] = 'La valeur estimee est obligatoire.';
        } elseif ($estimated <= 0) {
            $errors['valeurEstimee'] = 'La valeur estimee doit etre un nombre positif.';
        } elseif ($estimated < 1000 || $estimated > 100000000) {
            $errors['valeurEstimee'] = 'La valeur estimee doit etre comprise entre 1000 et 100000000 DT.';
        }

        $retainedRaw = $data['valeurRetenue'] ?? '';
        if ($retainedRaw !== '' && $retainedRaw !== null) {
            $retained = (float) $retainedRaw;
            if ($retained <= 0) {
                $errors['valeurRetenue'] = 'La valeur retenue doit etre positive.';
            } elseif ($estimated > 0 && $retained > $estimated) {
                $errors['valeurRetenue'] = 'La valeur retenue ne peut pas depasser la valeur estimee.';
            }
        }

        $dateEvaluation = trim((string) ($data['dateEvaluation'] ?? ''));
        if ($dateEvaluation === '') {
            $errors['dateEvaluation'] = "La date d'evaluation est obligatoire.";
        } else {
            try {
                $parsedDate = new \DateTimeImmutable($dateEvaluation);
                if ($parsedDate > $today) {
                    $errors['dateEvaluation'] = "La date d'evaluation ne doit pas etre superieure a la date actuelle.";
                }
            } catch (\Throwable) {
                $errors['dateEvaluation'] = "Date d'evaluation invalide.";
            }
        }

        $document = trim((string) ($data['documentJustificatif'] ?? ''));
        if ($document === '') {
            $errors['documentJustificatif'] = 'Le document justificatif est obligatoire.';
        } elseif (mb_strlen($document, 'UTF-8') > 255) {
            $errors['documentJustificatif'] = 'Le nom du document ne peut pas depasser 255 caracteres.';
        }

        $nomGarant = trim((string) ($data['nomGarant'] ?? ''));
        if ($nomGarant === '') {
            $errors['nomGarant'] = 'Le nom du garant est obligatoire.';
        } elseif (mb_strlen($nomGarant, 'UTF-8') < 2) {
            $errors['nomGarant'] = 'Le nom doit contenir au moins 2 caracteres.';
        } elseif (mb_strlen($nomGarant, 'UTF-8') > 150) {
            $errors['nomGarant'] = 'Le nom du garant ne peut pas depasser 150 caracteres.';
        } elseif (!preg_match('/^[\p{L}\s\-\']+$/u', $nomGarant)) {
            $errors['nomGarant'] = 'Le nom du garant ne doit contenir que des lettres.';
        }

        return $errors;
    }

    // -------------------------------------------------------------------------
    // Detail
    // -------------------------------------------------------------------------

    /**
     * Builds the detailed view for a single garantie.
     */
    public function buildGarantieDetail(BankingService $bankingService, int $idGarantie): ?array
    {
        $garanties = $bankingService->listGaranties();
        $garantie  = null;
        foreach ($garanties as $g) {
            if ((int) ($g['idGarantie'] ?? 0) === $idGarantie) {
                $garantie = $g;
                break;
            }
        }

        if ($garantie === null) {
            return null;
        }

        // Fetch the associated credit
        $creditId = (int) ($garantie['idCredit'] ?? 0);
        $credit   = null;
        if ($creditId > 0) {
            foreach ($bankingService->listCredits() as $c) {
                if ((int) ($c['idCredit'] ?? 0) === $creditId) {
                    $credit = $c;
                    break;
                }
            }
        }

        return [
            'garantie' => $garantie,
            'credit'   => $credit,
        ];
    }

    // -------------------------------------------------------------------------
    // PDF export
    // -------------------------------------------------------------------------

    /**
     * @return array{string, string} [$pdfContent, $filename]
     */
    public function buildGarantiePdf(BankingService $bankingService, ExportService $exportService, ?int $idGarantie): array
    {
        if ($idGarantie !== null) {
            $garantie = null;
            foreach ($bankingService->listGaranties() as $g) {
                if ((int) ($g['idGarantie'] ?? 0) === $idGarantie) {
                    $garantie = $g;
                    break;
                }
            }

            if ($garantie === null) {
                return ['', ''];
            }

            $headers = ['Champ', 'Valeur'];
            $rows    = [
                ['ID Garantie',      (string) ($garantie['idGarantie'] ?? '—')],
                ['ID Crédit',        (string) ($garantie['idCredit'] ?? '—')],
                ['Type',             (string) ($garantie['typeGarantie'] ?? '—')],
                ['Valeur estimée',   number_format((float) ($garantie['valeurEstimee'] ?? 0), 2, '.', ' ').' DT'],
                ['Valeur retenue',   number_format((float) ($garantie['valeurRetenue'] ?? 0), 2, '.', ' ').' DT'],
                ['Date évaluation',  (string) ($garantie['dateEvaluation'] ?? '—')],
                ['Nom garant',       (string) ($garantie['nomGarant'] ?? '—')],
                ['Adresse bien',     (string) ($garantie['adresseBien'] ?? '—')],
                ['Statut',           (string) ($garantie['statut'] ?? '—')],
            ];
            $stats = [
                ['label' => 'Type',          'value' => (string) ($garantie['typeGarantie'] ?? '—')],
                ['label' => 'Val. estimée',  'value' => number_format((float) ($garantie['valeurEstimee'] ?? 0), 2, '.', ' ').' DT'],
                ['label' => 'Statut',        'value' => (string) ($garantie['statut'] ?? '—')],
            ];
            $title    = sprintf('Fiche Garantie — #%d', $idGarantie);
            $subtitle = sprintf('Détail complet de la garantie #%d exportée depuis Nexora.', $idGarantie);
            $filename = sprintf('nexora-garantie-%d.pdf', $idGarantie);
        } else {
            $garanties     = $bankingService->listGaranties();
            $headers       = ['ID', 'ID Crédit', 'Type', 'Val. estimée', 'Val. retenue', 'Date éval.', 'Garant', 'Statut'];
            $rows          = [];
            $totalEstimee  = 0.0;
            $totalRetenue  = 0.0;
            foreach ($garanties as $g) {
                $totalEstimee += (float) ($g['valeurEstimee'] ?? 0);
                $totalRetenue += (float) ($g['valeurRetenue'] ?? 0);
                $rows[] = [
                    (string) ($g['idGarantie'] ?? ''),
                    (string) ($g['idCredit'] ?? ''),
                    (string) ($g['typeGarantie'] ?? ''),
                    number_format((float) ($g['valeurEstimee'] ?? 0), 2, '.', ' '),
                    number_format((float) ($g['valeurRetenue'] ?? 0), 2, '.', ' '),
                    (string) ($g['dateEvaluation'] ?? ''),
                    (string) ($g['nomGarant'] ?? ''),
                    (string) ($g['statut'] ?? ''),
                ];
            }
            $stats = [
                ['label' => 'Total garanties', 'value' => (string) count($garanties)],
                ['label' => 'Val. estimée',    'value' => number_format($totalEstimee, 2, '.', ' ').' DT'],
                ['label' => 'Val. retenue',    'value' => number_format($totalRetenue, 2, '.', ' ').' DT'],
            ];
            $title    = 'Rapport Garanties';
            $subtitle = 'Export complet de toutes les garanties depuis Nexora.';
            $filename = 'nexora-garanties-all.pdf';
        }

        return [
            $exportService->buildPdf($title, $headers, $rows, $stats, $subtitle, '#00bcd4'),
            $filename,
        ];
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    /**
     * Computes per-status statistics for KPI cards.
     *
     * @param array<int, array<string, mixed>> $garanties
     */
    public function buildGarantieStats(array $garanties): array
    {
        $total         = count($garanties);
        $totalEstimee  = 0.0;
        $totalRetenue  = 0.0;
        $amounts       = ['Actif' => 0.0, 'RejetÃ©' => 0.0, 'En attente' => 0.0, 'Autre' => 0.0];
        $counts        = ['Actif' => 0, 'Rejeté' => 0, 'En attente' => 0, 'Autre' => 0];

        foreach ($garanties as $g) {
            $estimated = (float) ($g['valeurEstimee'] ?? 0);

            $totalEstimee += $estimated;
            $totalRetenue += (float) ($g['valeurRetenue'] ?? 0);

            $statut = strtolower(trim((string) ($g['statut'] ?? '')));
            if (str_contains($statut, 'actif') || str_contains($statut, 'valid')) {
                $counts['Actif']++;
                $amounts['Actif'] += $estimated;
            } elseif (str_contains($statut, 'rejet') || str_contains($statut, 'refus')) {
                $counts['Rejeté']++;
                $amounts['RejetÃ©'] += $estimated;
            } elseif (str_contains($statut, 'attente') || str_contains($statut, 'pending')) {
                $counts['En attente']++;
                $amounts['En attente'] += $estimated;
            } else {
                $counts['Autre']++;
                $amounts['Autre'] += $estimated;
            }
        }

        return [
            'total'         => $total,
            'total_estimee' => $totalEstimee,
            'total_retenue' => $totalRetenue,
            'counts'        => $counts,
            'amounts'       => $amounts,
            'colors'        => [
                'Actif'      => '#22c55e',
                'Rejeté'     => '#ef4444',
                'En attente' => '#f97316',
                'Autre'      => '#6b7280',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Portal filtering / sorting
    // -------------------------------------------------------------------------

    /**
     * @return array{q:string, search_in:string, filter:string, sort:string, dir:string}
     */
    private function resolvePortalGarantieQuery(?Request $request): array
    {
        $allowedSearchFields = ['all', 'id', 'credit', 'type', 'estimee', 'retenue', 'date', 'garant', 'statut'];
        $allowedFilters      = ['all', 'actif', 'en_attente', 'rejete', 'autre'];
        $allowedSorts        = ['id', 'credit', 'type', 'estimee', 'retenue', 'date', 'garant', 'statut'];

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

        return ['q' => $query, 'search_in' => $searchIn, 'filter' => $filter, 'sort' => $sort, 'dir' => $dir];
    }

    /**
     * @param array<int, array<string, mixed>> $garanties
     * @param array{q:string, search_in:string, filter:string, sort:string, dir:string} $query
     * @return array<int, array<string, mixed>>
     */
    private function filterAndSortPortalGaranties(array $garanties, array $query): array
    {
        $filtered = array_values(array_filter(
            $garanties,
            fn (array $g): bool => $this->matchesPortalGarantieFilter($g, $query['filter'])
                && $this->matchesPortalGarantieSearch($g, $query['q'], $query['search_in'])
        ));

        if ($query['sort'] === '') {
            return $filtered;
        }

        $direction = $query['dir'] === 'desc' ? -1 : 1;

        usort($filtered, function (array $left, array $right) use ($query, $direction): int {
            $cmp = match ($query['sort']) {
                'id'      => ((int) ($left['idGarantie'] ?? 0)) <=> ((int) ($right['idGarantie'] ?? 0)),
                'credit'  => ((int) ($left['idCredit'] ?? 0)) <=> ((int) ($right['idCredit'] ?? 0)),
                'estimee' => ((float) ($left['valeurEstimee'] ?? 0)) <=> ((float) ($right['valeurEstimee'] ?? 0)),
                'retenue' => ((float) ($left['valeurRetenue'] ?? 0)) <=> ((float) ($right['valeurRetenue'] ?? 0)),
                'date'    => strtotime((string) ($left['dateEvaluation'] ?? '')) <=> strtotime((string) ($right['dateEvaluation'] ?? '')),
                default   => strnatcmp(
                    $this->normalizeGarantieText((string) ($left[$this->garantieSortField($query['sort'])] ?? '')),
                    $this->normalizeGarantieText((string) ($right[$this->garantieSortField($query['sort'])] ?? ''))
                ),
            };

            return $cmp * $direction;
        });

        return $filtered;
    }

    private function garantieSortField(string $sort): string
    {
        return match ($sort) {
            'type'   => 'typeGarantie',
            'garant' => 'nomGarant',
            'statut' => 'statut',
            default  => 'idGarantie',
        };
    }

    private function matchesPortalGarantieFilter(array $garantie, string $filter): bool
    {
        if ($filter === 'all') {
            return true;
        }

        $statut = $this->normalizeGarantieText((string) ($garantie['statut'] ?? ''));

        return match ($filter) {
            'actif'      => str_contains($statut, 'actif') || str_contains($statut, 'valid'),
            'en_attente' => str_contains($statut, 'attente') || str_contains($statut, 'pending'),
            'rejete'     => str_contains($statut, 'rejet') || str_contains($statut, 'refus'),
            'autre'      => !str_contains($statut, 'actif') && !str_contains($statut, 'valid')
                         && !str_contains($statut, 'attente') && !str_contains($statut, 'pending')
                         && !str_contains($statut, 'rejet') && !str_contains($statut, 'refus'),
            default => true,
        };
    }

    private function matchesPortalGarantieSearch(array $garantie, string $query, string $searchIn): bool
    {
        $needle = $this->normalizeGarantieText($query);
        if ($needle === '') {
            return true;
        }

        $fields = ['id', 'credit', 'type', 'estimee', 'retenue', 'date', 'garant', 'statut'];

        if ($searchIn === 'all') {
            foreach ($fields as $field) {
                if (str_contains($this->portalGarantieSearchValue($garantie, $field), $needle)) {
                    return true;
                }
            }

            return false;
        }

        return str_contains($this->portalGarantieSearchValue($garantie, $searchIn), $needle);
    }

    private function portalGarantieSearchValue(array $garantie, string $field): string
    {
        $value = match ($field) {
            'id'      => (string) ($garantie['idGarantie'] ?? ''),
            'credit'  => (string) ($garantie['idCredit'] ?? ''),
            'type'    => (string) ($garantie['typeGarantie'] ?? ''),
            'estimee' => number_format((float) ($garantie['valeurEstimee'] ?? 0), 2, '.', ' '),
            'retenue' => number_format((float) ($garantie['valeurRetenue'] ?? 0), 2, '.', ' '),
            'date'    => (string) ($garantie['dateEvaluation'] ?? ''),
            'garant'  => (string) ($garantie['nomGarant'] ?? ''),
            'statut'  => (string) ($garantie['statut'] ?? ''),
            default   => '',
        };

        return $this->normalizeGarantieText($value);
    }

    /**
     * @param array<int, array<string, mixed>> $garanties
     * @return array{all:int, actif:int, en_attente:int, rejete:int, autre:int}
     */
    private function buildPortalGarantieStatusCounts(array $garanties): array
    {
        $counts = ['all' => count($garanties), 'actif' => 0, 'en_attente' => 0, 'rejete' => 0, 'autre' => 0];

        foreach ($garanties as $g) {
            $statut = $this->normalizeGarantieText((string) ($g['statut'] ?? ''));
            if (str_contains($statut, 'actif') || str_contains($statut, 'valid')) {
                $counts['actif']++;
            } elseif (str_contains($statut, 'attente') || str_contains($statut, 'pending')) {
                $counts['en_attente']++;
            } elseif (str_contains($statut, 'rejet') || str_contains($statut, 'refus')) {
                $counts['rejete']++;
            } else {
                $counts['autre']++;
            }
        }

        return $counts;
    }

    private function normalizeGarantieText(string $value): string
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

    // -------------------------------------------------------------------------
    // Form helpers (session persistence)
    // -------------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    public function getFormErrors(Request $request, string $formKey): array
    {
        return (array) $request->getSession()->get('nexora.form_errors.'.$formKey, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormData(Request $request, string $formKey): array
    {
        return (array) $request->getSession()->get('nexora.form_data.'.$formKey, []);
    }

    // -------------------------------------------------------------------------
    // History
    // -------------------------------------------------------------------------

    private function logHistory(Request $request, string $action, array $data): void
    {
        $session = $request->getSession();
        /** @var array<int, array<string, mixed>> $history */
        $history = $session->get('nexora.garanties_history', []);

        $labels = [
            'garantie_add'    => 'Ajouter garantie',
            'garantie_edit'   => 'Modifier garantie',
            'garantie_delete' => 'Supprimer garantie',
        ];

        $resolvedAction = $action;
        if ($action === 'garantie_save') {
            $resolvedAction = ($data['idGarantie'] ?? '') !== '' ? 'garantie_edit' : 'garantie_add';
        }

        $idGarantie = (string) ($data['idGarantie'] ?? '—');
        $idCredit   = (string) ($data['idCredit'] ?? '—');
        $type       = (string) ($data['typeGarantie'] ?? '');
        $estimee    = isset($data['valeurEstimee']) ? number_format((float) $data['valeurEstimee'], 2, '.', ' ').' DT' : '—';
        $statut     = (string) ($data['statut'] ?? '');

        $detail = $type !== '' ? $type : ($idGarantie !== '—' ? '#'.$idGarantie : '—');
        if ($idCredit !== '—') {
            $detail .= ' · Crédit #'.$idCredit;
        }
        if ($statut !== '') {
            $detail .= ' · '.$statut;
        }
        if ($estimee !== '—') {
            $detail .= ' · Estimée '.$estimee;
        }

        array_unshift($history, [
            'action'     => $labels[$resolvedAction] ?? $resolvedAction,
            'detail'     => $detail,
            'idGarantie' => $idGarantie,
            'idCredit'   => $idCredit,
            'timestamp'  => date('d/m/Y H:i:s'),
            'type'       => str_contains($resolvedAction, 'delete') ? 'delete' : 'save',
        ]);

        $session->set('nexora.garanties_history', array_slice($history, 0, 50));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(Request $request): array
    {
        return (array) $request->getSession()->get('nexora.garanties_history', []);
    }

    // -------------------------------------------------------------------------
    // Shared helper
    // -------------------------------------------------------------------------

    private function requestInt(Request $request, string $key): ?int
    {
        $value = $request->request->get($key);
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }
}
