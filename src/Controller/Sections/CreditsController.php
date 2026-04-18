<?php

namespace App\Controller\Sections;
use App\Service\ActivityService;
use App\Service\BankingService;
use App\Service\ExportService;
use Symfony\Component\HttpFoundation\Request;

final class CreditsController
{
    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    public function buildAdminData(BankingService $bankingService): array
    {
        $credits = $bankingService->listCredits();

        return [
            'items' => $credits,
            'support' => [
                'users'             => $bankingService->listUsers(),
                'accounts'          => $bankingService->listAccounts(),
                'credits'           => $credits,
                'credit_type_stats' => $bankingService->getCreditTypeDistribution(),
                'credit_stats'      => $this->buildCreditStats($credits),
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'credit_save':
                $data = $request->request->all();
                $bankingService->saveCredit($data, $this->requestInt($request, 'idCredit'));
                $this->logHistory($request, 'credit_save', $data);

                return ['type' => 'success', 'message' => 'Credit saved.'];

            case 'credit_delete':
                $id = $this->requestInt($request, 'idCredit') ?? 0;
                $bankingService->deleteCredit($id);
                $this->logHistory($request, 'credit_delete', ['idCredit' => $id]);

                return ['type' => 'success', 'message' => 'Credit deleted.'];
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Portal
    // -------------------------------------------------------------------------

    public function buildPortalData(BankingService $bankingService, int $userId, ?Request $request = null): array
    {
        $allCredits    = $bankingService->listCredits($userId);
        $creditQuery   = $this->resolvePortalCreditQuery($request);
        $credits       = $this->filterAndSortPortalCredits($allCredits, $creditQuery);

        $formErrorsCredit = $request !== null ? $this->getFormErrors($request, 'credit') : [];
        $formDataCredit   = $request !== null ? $this->getFormData($request, 'credit') : [];

        if ($request !== null) {
            $request->getSession()->remove('nexora.form_errors.credit');
            $request->getSession()->remove('nexora.form_data.credit');
        }

        return [
            'items' => $credits,
            'support' => [
                'accounts'               => $bankingService->listAccounts($userId),
                'credits'                => $credits,
                'all_credits'            => $allCredits,
                'credit_query'           => $creditQuery,
                'credit_filter_counts'   => $this->buildPortalCreditStatusCounts($allCredits),
                'filtered_credit_count'  => count($credits),
                'credit_history'         => $request !== null ? $this->getHistory($request) : [],
                'credit_stats'           => $this->buildCreditStats($allCredits),
                'form_errors_credit'     => $formErrorsCredit,
                'form_data_credit'       => $formDataCredit,
                'credit_form_feedback'   => [
                    'errors' => $formErrorsCredit,
                    'input' => $formDataCredit,
                ],
            ],
        ];
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'credit_save':
                $data = $request->request->all();
                if (($data['idUser'] ?? '') === '') {
                    $data['idUser'] = (string) $userId;
                }

                $errors = $this->validateCredit($data, $bankingService, $userId);
                if ($errors !== []) {
                    $request->getSession()->set('nexora.form_errors.credit', $errors);
                    $request->getSession()->set('nexora.form_data.credit', $data);

                    return ['type' => 'validation_error', 'message' => 'Veuillez corriger les erreurs du formulaire credit.'];
                }

                $request->getSession()->remove('nexora.form_errors.credit');
                $request->getSession()->remove('nexora.form_data.credit');

                // Ownership check on edit
                $creditId = $this->requestInt($request, 'idCredit');
                if ($creditId !== null) {
                    $existingCredit = null;
                    foreach ($bankingService->listCredits($userId) as $credit) {
                        if ((int) ($credit['idCredit'] ?? 0) === $creditId) {
                            $existingCredit = $credit;
                            break;
                        }
                    }
                    if ($existingCredit === null) {
                        return ['type' => 'error', 'message' => 'Crédit introuvable ou inaccessible.'];
                    }
                    $data['idUser'] = (string) $userId;
                    $bankingService->saveCredit($data, $creditId, $userId);
                } else {
                    $bankingService->saveCredit($data, null, $userId);
                }

                $this->logHistory($request, 'credit_save', $data);

                return ['type' => 'success', 'message' => 'Crédit enregistré avec succès.'];

            case 'credit_delete':
                $id = $this->requestInt($request, 'idCredit') ?? 0;
                if ($id > 0) {
                    $existingCredit = null;
                    foreach ($bankingService->listCredits($userId) as $credit) {
                        if ((int) ($credit['idCredit'] ?? 0) === $id) {
                            $existingCredit = $credit;
                            break;
                        }
                    }
                    if ($existingCredit === null) {
                        return ['type' => 'error', 'message' => 'Crédit introuvable ou inaccessible.'];
                    }
                    $bankingService->deleteCredit($id, null);
                }
                $this->logHistory($request, 'credit_delete', ['idCredit' => $id]);

                return ['type' => 'success', 'message' => 'Crédit supprimé.'];
        }

        return null;
    }

    /**
     * Validates credit data. Returns field-keyed error messages.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validateCredit(array $data, BankingService $bankingService, int $userId): array
    {
        $errors = [];
        $today = new \DateTimeImmutable('today');
        $allowedDurations = [6, 12, 18, 24, 36, 48, 60, 72, 84, 120];
        $allowedTypes = [
            'Professionnel',
            'Immobilier',
            'Auto',
            'Consommation',
            'Etudes',
            'Travaux',
            'Personnel',
            'Autre',
            'Hypotheque',
            'Pret auto',
            'Education',
            'Sante',
        ];
        $allowedContracts = ['CDI', 'CDD', 'Fonctionnaire', 'Profession liberale', 'Profession libérale', 'Autre'];

        $accountId = (int) ($data['idCompte'] ?? 0);
        if ($accountId <= 0) {
            $errors['idCompte'] = 'Le compte associe est obligatoire.';
        } else {
            $accountFound = false;
            foreach ($bankingService->listAccounts($userId) as $account) {
                if ((int) ($account['idCompte'] ?? 0) === $accountId) {
                    $accountFound = true;
                    break;
                }
            }

            if (!$accountFound) {
                $errors['idCompte'] = 'Compte requis pour le credit.';
            }
        }

        $typeCredit = trim((string) ($data['typeCredit'] ?? ''));
        if ($typeCredit === '') {
            $errors['typeCredit'] = 'Le type de credit est obligatoire.';
        } elseif (!in_array($typeCredit, $allowedTypes, true)) {
            $errors['typeCredit'] = 'Veuillez selectionner un type de credit valide.';
        }

        $dateDemande = trim((string) ($data['dateDemande'] ?? ''));
        $isEdit = (int) ($data['idCredit'] ?? 0) > 0;
        if ($dateDemande === '') {
            $errors['dateDemande'] = 'La date de demande est obligatoire.';
        } else {
            try {
                $parsedDate = new \DateTimeImmutable($dateDemande);
                if (!$isEdit && $parsedDate < $today) {
                    $errors['dateDemande'] = 'La date de demande ne peut pas être dans le passé.';
                }
            } catch (\Throwable) {
                $errors['dateDemande'] = 'Date de demande invalide.';
            }
        }

        $amountRaw = $data['montantDemande'] ?? '';
        $amount = (float) $amountRaw;
        if ($amountRaw === '' || $amountRaw === null) {
            $errors['montantDemande'] = 'Le montant demande est obligatoire.';
        } elseif ($amount <= 0) {
            $errors['montantDemande'] = 'Le montant demande doit etre un nombre positif.';
        } elseif ($amount < 500 || $amount > 10000000) {
            $errors['montantDemande'] = 'Le montant doit etre compris entre 500 et 10000000 DT.';
        }

        $autoFundingRaw = $data['autofinancement'] ?? '';
        $autoFunding = (float) $autoFundingRaw;
        if ($autoFundingRaw === '' || $autoFundingRaw === null) {
            $errors['autofinancement'] = "L'autofinancement est obligatoire.";
        } elseif ($autoFunding < 0) {
            $errors['autofinancement'] = "L'autofinancement doit etre positif ou nul.";
        } elseif ($amount > 0 && $autoFunding > $amount) {
            $errors['autofinancement'] = "L'autofinancement ne doit pas depasser le montant demande.";
        }

        $duration = (int) ($data['duree'] ?? 0);
        if ($duration <= 0) {
            $errors['duree'] = 'La duree est obligatoire.';
        } elseif (!in_array($duration, $allowedDurations, true)) {
            $errors['duree'] = 'La duree doit etre comprise dans la liste autorisee.';
        }

        $rateRaw = $data['tauxInteret'] ?? '';
        $rate = (float) $rateRaw;
        if ($rateRaw === '' || $rateRaw === null) {
            $errors['tauxInteret'] = "Le taux d'interet est obligatoire.";
        } elseif ($rate < 0 || $rate > 100) {
            $errors['tauxInteret'] = "Le taux d'interet doit etre compris entre 0% et 100%.";
        }

        $monthlyRaw = $data['mensualite'] ?? '';
        if ($monthlyRaw !== '' && $monthlyRaw !== null && (float) $monthlyRaw <= 0) {
            $errors['mensualite'] = 'La mensualite doit etre un nombre positif.';
        }

        $approvedRaw = $data['montantAccorde'] ?? '';
        if ($approvedRaw !== '' && $approvedRaw !== null && (float) $approvedRaw < 0) {
            $errors['montantAccorde'] = 'Le montant accorde doit etre positif ou nul.';
        }

        $salaryRaw = $data['salaire'] ?? '';
        $salary = (float) $salaryRaw;
        if ($salaryRaw === '' || $salaryRaw === null) {
            $errors['salaire'] = 'Le salaire est obligatoire.';
        } elseif ($salary <= 0) {
            $errors['salaire'] = 'Le salaire doit etre un nombre positif.';
        } elseif ($salary > 1000000) {
            $errors['salaire'] = 'Le salaire doit etre compris entre 0 et 1000000 DT.';
        }

        $contract = trim((string) ($data['typeContrat'] ?? ''));
        if ($contract === '') {
            $errors['typeContrat'] = 'Le type de contrat est obligatoire.';
        } elseif (!in_array($contract, $allowedContracts, true)) {
            $errors['typeContrat'] = 'Veuillez selectionner un type de contrat valide.';
        }

        $seniorityRaw = trim((string) ($data['ancienneteAnnees'] ?? ''));
        if ($seniorityRaw === '') {
            $errors['ancienneteAnnees'] = "L'anciennete est obligatoire.";
        } elseif (filter_var($seniorityRaw, FILTER_VALIDATE_INT) === false) {
            $errors['ancienneteAnnees'] = "L'anciennete doit etre un nombre entier.";
        } else {
            $seniority = (int) $seniorityRaw;
            if ($seniority < 0 || $seniority > 60) {
                $errors['ancienneteAnnees'] = "L'anciennete doit etre comprise entre 0 et 60 ans.";
            }
        }

        $selectedGarantieId = (int) ($data['idGarantie'] ?? 0);
        if ($selectedGarantieId <= 0) {
            $errors['idGarantie'] = 'Veuillez selectionner une garantie enregistree.';
        } else {
            $matchingGarantie = null;
            foreach ($bankingService->listGaranties($userId) as $garantie) {
                if ((int) ($garantie['idGarantie'] ?? 0) === $selectedGarantieId) {
                    $matchingGarantie = $garantie;
                    break;
                }
            }

            if ($matchingGarantie === null) {
                $errors['idGarantie'] = 'Garantie selectionnee introuvable.';
            } else {
                $currentCreditId = (int) ($data['idCredit'] ?? 0);
                $linkedCreditId = (int) ($matchingGarantie['idCredit'] ?? 0);
                if ($linkedCreditId > 0 && $linkedCreditId !== $currentCreditId) {
                    $errors['idGarantie'] = 'Cette garantie est deja associee a un autre credit.';
                }
            }
        }

        return $errors;
    }

    // -------------------------------------------------------------------------
    // Detail
    // -------------------------------------------------------------------------

    /**
     * Builds the detailed view for a single credit (with its garanties).
     */
    public function buildCreditDetail(BankingService $bankingService, int $idCredit): ?array
    {
        $credits = $bankingService->listCredits();
        $credit  = null;
        foreach ($credits as $c) {
            if ((int) ($c['idCredit'] ?? 0) === $idCredit) {
                $credit = $c;
                break;
            }
        }

        if ($credit === null) {
            return null;
        }

        $allGaranties    = $bankingService->listGaranties();
        $linkedGaranties = array_values(array_filter(
            $allGaranties,
            fn ($g) => (int) ($g['idCredit'] ?? 0) === $idCredit
        ));

        return [
            'credit'    => $credit,
            'garanties' => $linkedGaranties,
        ];
    }

    // -------------------------------------------------------------------------
    // PDF export
    // -------------------------------------------------------------------------

    /**
     * @return array{string, string} [$pdfContent, $filename]
     */
    public function buildCreditPdf(BankingService $bankingService, ExportService $exportService, ?int $idCredit): array
    {
        if ($idCredit !== null) {
            $credit = null;
            foreach ($bankingService->listCredits() as $c) {
                if ((int) ($c['idCredit'] ?? 0) === $idCredit) {
                    $credit = $c;
                    break;
                }
            }

            if ($credit === null) {
                return ['', ''];
            }

            $headers = ['Champ', 'Valeur'];
            $rows    = [
                ['ID Crédit',       (string) ($credit['idCredit'] ?? '—')],
                ['Type',            (string) ($credit['typeCredit'] ?? '—')],
                ['Montant demandé', number_format((float) ($credit['montantDemande'] ?? 0), 2, '.', ' ').' DT'],
                ['Mensualité',      number_format((float) ($credit['mensualite'] ?? 0), 2, '.', ' ').' DT'],
                ['Durée (mois)',    (string) ($credit['duree'] ?? '—')],
                ['Taux d\'intérêt', (string) ($credit['tauxInteret'] ?? '—').' %'],
                ['Date demande',    (string) ($credit['dateDemande'] ?? '—')],
                ['Statut',          (string) ($credit['statut'] ?? '—')],
            ];
            $stats = [
                ['label' => 'Type',    'value' => (string) ($credit['typeCredit'] ?? '—')],
                ['label' => 'Montant', 'value' => number_format((float) ($credit['montantDemande'] ?? 0), 2, '.', ' ').' DT'],
                ['label' => 'Statut',  'value' => (string) ($credit['statut'] ?? '—')],
            ];
            $title    = sprintf('Fiche Crédit — #%d', $idCredit);
            $subtitle = sprintf('Détail complet du dossier crédit #%d exporté depuis Nexora.', $idCredit);
            $filename = sprintf('nexora-credit-%d.pdf', $idCredit);
        } else {
            $credits     = $bankingService->listCredits();
            $headers     = ['ID', 'Type', 'Montant (DT)', 'Mensualité', 'Durée', 'Taux', 'Date', 'Statut'];
            $rows        = [];
            $totalAmount = 0.0;
            foreach ($credits as $c) {
                $totalAmount += (float) ($c['montantDemande'] ?? 0);
                $rows[] = [
                    (string) ($c['idCredit'] ?? ''),
                    (string) ($c['typeCredit'] ?? ''),
                    number_format((float) ($c['montantDemande'] ?? 0), 2, '.', ' '),
                    number_format((float) ($c['mensualite'] ?? 0), 2, '.', ' '),
                    (string) ($c['duree'] ?? ''),
                    (string) ($c['tauxInteret'] ?? '').' %',
                    (string) ($c['dateDemande'] ?? ''),
                    (string) ($c['statut'] ?? ''),
                ];
            }
            $pending = count(array_filter($credits, fn ($c) => strtolower((string) ($c['statut'] ?? '')) === 'en attente'));
            $stats   = [
                ['label' => 'Total crédits',    'value' => (string) count($credits)],
                ['label' => 'En attente',        'value' => (string) $pending],
                ['label' => 'Montant total',     'value' => number_format($totalAmount, 2, '.', ' ').' DT'],
            ];
            $title    = 'Rapport Crédits';
            $subtitle = 'Export complet de tous les dossiers crédit depuis Nexora.';
            $filename = 'nexora-credits-all.pdf';
        }

        return [
            $exportService->buildPdf($title, $headers, $rows, $stats, $subtitle, '#0db98f'),
            $filename,
        ];
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    /**
     * Computes per-status statistics.
     *
     * @param array<int, array<string, mixed>> $credits
     */
    public function buildCreditStats(array $credits): array
    {
        $total   = count($credits);
        $counts  = ['En attente' => 0, 'Accepté' => 0, 'En cours' => 0, 'Rejeté' => 0, 'Clôturé' => 0];
        $amounts = ['En attente' => 0.0, 'Accepté' => 0.0, 'En cours' => 0.0, 'Rejeté' => 0.0, 'Clôturé' => 0.0];

        foreach ($credits as $c) {
            $raw    = (string) ($c['statut'] ?? '');
            $statut = match (strtolower(trim($raw))) {
                'en attente', 'pending', 'a traiter' => 'En attente',
                'accepte', 'accepté'                 => 'Accepté',
                'en cours'                           => 'En cours',
                'rejete', 'rejeté', 'refuse'         => 'Rejeté',
                'cloture', 'clôturé'                 => 'Clôturé',
                default                              => null,
            };
            if ($statut !== null) {
                $counts[$statut]++;
                $amounts[$statut] += (float) ($c['montantDemande'] ?? 0);
            }
        }

        return [
            'total'   => $total,
            'counts'  => $counts,
            'amounts' => $amounts,
            'colors'  => [
                'En attente' => '#f97316',
                'Accepté'    => '#22c55e',
                'En cours'   => '#3b82f6',
                'Rejeté'     => '#ef4444',
                'Clôturé'    => '#6b7280',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Portal filtering / sorting
    // -------------------------------------------------------------------------

    /**
     * @return array{q:string, search_in:string, filter:string, sort:string, dir:string}
     */
    private function resolvePortalCreditQuery(?Request $request): array
    {
        $allowedSearchFields = ['all', 'id', 'type', 'montant', 'mensualite', 'duree', 'taux', 'date', 'statut'];
        $allowedFilters      = ['all', 'en_attente', 'accepte', 'en_cours', 'rejete', 'cloture'];
        $allowedSorts        = ['id', 'type', 'montant', 'mensualite', 'duree', 'taux', 'date', 'statut'];

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

        return compact('query', 'searchIn', 'filter', 'sort', 'dir') + ['q' => $query, 'search_in' => $searchIn];
    }

    /**
     * @param array<int, array<string, mixed>> $credits
     * @param array{q:string, search_in:string, filter:string, sort:string, dir:string} $query
     * @return array<int, array<string, mixed>>
     */
    private function filterAndSortPortalCredits(array $credits, array $query): array
    {
        $filtered = array_values(array_filter(
            $credits,
            fn (array $credit): bool => $this->matchesPortalCreditFilter($credit, $query['filter'])
                && $this->matchesPortalCreditSearch($credit, $query['q'], $query['search_in'])
        ));

        if ($query['sort'] === '') {
            return $filtered;
        }

        $direction = $query['dir'] === 'desc' ? -1 : 1;

        usort($filtered, function (array $left, array $right) use ($query, $direction): int {
            $cmp = match ($query['sort']) {
                'id'        => ((int) ($left['idCredit'] ?? 0)) <=> ((int) ($right['idCredit'] ?? 0)),
                'montant'   => ((float) ($left['montantDemande'] ?? 0)) <=> ((float) ($right['montantDemande'] ?? 0)),
                'mensualite'=> ((float) ($left['mensualite'] ?? 0)) <=> ((float) ($right['mensualite'] ?? 0)),
                'duree'     => ((int) ($left['duree'] ?? 0)) <=> ((int) ($right['duree'] ?? 0)),
                'taux'      => ((float) ($left['tauxInteret'] ?? 0)) <=> ((float) ($right['tauxInteret'] ?? 0)),
                'date'      => strtotime((string) ($left['dateDemande'] ?? '')) <=> strtotime((string) ($right['dateDemande'] ?? '')),
                default     => strnatcmp(
                    $this->normalizeCreditText((string) ($left[$this->creditSortField($query['sort'])] ?? '')),
                    $this->normalizeCreditText((string) ($right[$this->creditSortField($query['sort'])] ?? ''))
                ),
            };

            return $cmp * $direction;
        });

        return $filtered;
    }

    private function creditSortField(string $sort): string
    {
        return match ($sort) {
            'type'   => 'typeCredit',
            'statut' => 'statut',
            default  => 'idCredit',
        };
    }

    private function matchesPortalCreditFilter(array $credit, string $filter): bool
    {
        if ($filter === 'all') {
            return true;
        }

        $statut = $this->normalizeCreditText((string) ($credit['statut'] ?? ''));

        return match ($filter) {
            'en_attente' => str_contains($statut, 'attente') || str_contains($statut, 'pending'),
            'accepte'    => str_contains($statut, 'accept'),
            'en_cours'   => str_contains($statut, 'cours'),
            'rejete'     => str_contains($statut, 'rejet') || str_contains($statut, 'refus'),
            'cloture'    => str_contains($statut, 'clot'),
            default      => true,
        };
    }

    private function matchesPortalCreditSearch(array $credit, string $query, string $searchIn): bool
    {
        $needle = $this->normalizeCreditText($query);
        if ($needle === '') {
            return true;
        }

        $fields = ['id', 'type', 'montant', 'mensualite', 'duree', 'taux', 'date', 'statut'];

        if ($searchIn === 'all') {
            foreach ($fields as $field) {
                if (str_contains($this->portalCreditSearchValue($credit, $field), $needle)) {
                    return true;
                }
            }

            return false;
        }

        return str_contains($this->portalCreditSearchValue($credit, $searchIn), $needle);
    }

    private function portalCreditSearchValue(array $credit, string $field): string
    {
        $value = match ($field) {
            'id'         => (string) ($credit['idCredit'] ?? ''),
            'type'       => (string) ($credit['typeCredit'] ?? ''),
            'montant'    => number_format((float) ($credit['montantDemande'] ?? 0), 2, '.', ' '),
            'mensualite' => number_format((float) ($credit['mensualite'] ?? 0), 2, '.', ' '),
            'duree'      => (string) ($credit['duree'] ?? ''),
            'taux'       => (string) ($credit['tauxInteret'] ?? ''),
            'date'       => (string) ($credit['dateDemande'] ?? ''),
            'statut'     => (string) ($credit['statut'] ?? ''),
            default      => '',
        };

        return $this->normalizeCreditText($value);
    }

    /**
     * @param array<int, array<string, mixed>> $credits
     * @return array{all:int, en_attente:int, accepte:int, en_cours:int, rejete:int, cloture:int}
     */
    private function buildPortalCreditStatusCounts(array $credits): array
    {
        $counts = ['all' => count($credits), 'en_attente' => 0, 'accepte' => 0, 'en_cours' => 0, 'rejete' => 0, 'cloture' => 0];

        foreach ($credits as $c) {
            $statut = $this->normalizeCreditText((string) ($c['statut'] ?? ''));
            if (str_contains($statut, 'attente') || str_contains($statut, 'pending')) {
                $counts['en_attente']++;
            } elseif (str_contains($statut, 'accept')) {
                $counts['accepte']++;
            } elseif (str_contains($statut, 'cours')) {
                $counts['en_cours']++;
            } elseif (str_contains($statut, 'rejet') || str_contains($statut, 'refus')) {
                $counts['rejete']++;
            } elseif (str_contains($statut, 'clot')) {
                $counts['cloture']++;
            }
        }

        return $counts;
    }

    private function normalizeCreditText(string $value): string
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
        $history = $session->get('nexora.credits_history', []);

        $labels = [
            'credit_add'    => 'Ajouter crédit',
            'credit_edit'   => 'Modifier crédit',
            'credit_delete' => 'Supprimer crédit',
        ];

        $resolvedAction = $action;
        if ($action === 'credit_save') {
            $resolvedAction = ($data['idCredit'] ?? '') !== '' ? 'credit_edit' : 'credit_add';
        }

        $idCredit = (string) ($data['idCredit'] ?? '—');
        $type     = (string) ($data['typeCredit'] ?? '');
        $montant  = isset($data['montantDemande']) ? number_format((float) $data['montantDemande'], 2, '.', ' ').' DT' : '—';
        $statut   = (string) ($data['statut'] ?? '');

        $detail = $type !== '' ? $type : ($idCredit !== '—' ? '#'.$idCredit : '—');
        if ($statut !== '') {
            $detail .= ' · '.$statut;
        }
        if ($montant !== '—') {
            $detail .= ' · '.$montant;
        }

        array_unshift($history, [
            'action'    => $labels[$resolvedAction] ?? $resolvedAction,
            'detail'    => $detail,
            'idCredit'  => $idCredit,
            'timestamp' => date('d/m/Y H:i:s'),
            'type'      => str_contains($resolvedAction, 'delete') ? 'delete' : 'save',
        ]);

        $session->set('nexora.credits_history', array_slice($history, 0, 50));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(Request $request): array
    {
        return (array) $request->getSession()->get('nexora.credits_history', []);
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
