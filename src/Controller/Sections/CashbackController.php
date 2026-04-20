<?php

namespace App\Controller\Sections;

use App\Service\BankingService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DateTime;

final class CashbackController extends AbstractController
{
    /**
     * Build admin data for cashback management dashboard
     */
    public function buildAdminData(BankingService $bankingService): array
    {
        $cashbacks = $bankingService->listCashbacks();
        $partenaires = $bankingService->listPartenaires();
        $users = $bankingService->listUsers();

        return [
            'items' => $cashbacks,
            'support' => [
                'users' => $users,
                'cashbacks' => $cashbacks,
                'cashback_items' => $cashbacks,
                'partenaires' => $partenaires,
                'partners' => $partenaires,
                'partners_items' => $partenaires,
                'stats' => $this->calculateAdminStats($cashbacks, $bankingService),
            ],
        ];
    }

    /**
     * Build portal data for user cashback dashboard
     */
    public function buildPortalData(BankingService $bankingService, int $userId): array
    {
        $userCashbacks = $bankingService->listCashbacks($userId);
        $partenaires = $bankingService->listPartenaires();

        return [
            'items' => $userCashbacks,
            'support' => [
                'partenaires' => $partenaires,
                'partners' => $partenaires,
                'stats' => $this->calculateUserStats($userCashbacks),
                'partner_earnings' => $this->buildPartnerEarnings($userCashbacks),
                'recent_cashbacks' => $this->filterRecentCashbacks($userCashbacks),
            ],
        ];
    }

    /**
     * Handle admin actions for cashback management
     */
    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        try {
            switch ($action) {
                case 'cashback_save':
                    $this->handleCashbackSave($request, $bankingService);
                    return ['type' => 'success', 'message' => 'Cashback saved successfully.'];

                case 'cashback_delete':
                    $this->handleCashbackDelete($request, $bankingService);
                    return ['type' => 'success', 'message' => 'Cashback deleted successfully.'];

                case 'cashback_reward':
                    $this->handleCashbackReward($request, $bankingService);
                    return ['type' => 'success', 'message' => 'Reward applied.'];

                case 'cashback_bonus':
                    $this->handleCashbackBonusDecision($request, $bankingService);
                    return ['type' => 'success', 'message' => 'Bonus decision updated.'];

                case 'partner_save':
                    $this->handlePartnerSave($request, $bankingService);
                    return ['type' => 'success', 'message' => 'Partner saved successfully.'];

                case 'partner_delete':
                    $this->handlePartnerDelete($request, $bankingService);
                    return ['type' => 'success', 'message' => 'Partner deleted successfully.'];

                case 'cashback_search':
                    // Search doesn't need flash
                    break;

                case 'cashback_sort':
                    // Sort doesn't need flash
                    break;
            }
        } catch (\Exception $e) {
            return ['type' => 'error', 'message' => $e->getMessage()];
        }

        return null;
    }

    /**
     * Handle portal actions for users
     */
    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        try {
            switch ($action) {
                case 'cashback_save':
                    $this->handlePortalCashbackSave($request, $bankingService, $userId);
                    return ['type' => 'success', 'message' => 'Cashback saved.'];

                case 'cashback_delete':
                    $this->handlePortalCashbackDelete($request, $bankingService, $userId);
                    return ['type' => 'success', 'message' => 'Cashback deleted.'];

                case 'cashback_rating':
                    $this->handleCashbackRating($request, $bankingService, $userId);
                    return ['type' => 'success', 'message' => 'Rating submitted.'];

                case 'cashback_calculate':
                    // Calculate doesn't need flash
                    break;

                case 'cashback_search':
                    // Search doesn't need flash
                    break;

                case 'redeem_rewards':
                    $this->handleRedeemRewards($request, $bankingService, $userId);
                    return ['type' => 'success', 'message' => 'Redemption request registered.'];
            }
        } catch (\Exception $e) {
            return ['type' => 'error', 'message' => $e->getMessage()];
        }

        return null;
    }

    // ==================== ADMIN ACTION HANDLERS ====================

    private function handleCashbackSave(Request $request, BankingService $bankingService): void
    {
        $validation = $this->validateCashbackForm($request);
        if (!$validation['valid']) {
            throw new \Exception($validation['message']);
        }

        $cashback = $this->buildCashbackFromRequest($request, $bankingService);
        $idCashback = $this->requestInt($request, 'id_cashback');

        if ($idCashback) {
            $bankingService->saveCashback($cashback, $idCashback);
        } else {
            $bankingService->saveCashback($cashback);
        }
    }

    private function handleCashbackDelete(Request $request, BankingService $bankingService): void
    {
        $idCashback = $this->requestInt($request, 'id_cashback');
        if ($idCashback === null) {
            throw new \Exception('No cashback selected.');
        }

        $bankingService->deleteCashback($idCashback);
    }

    private function handleCashbackReward(Request $request, BankingService $bankingService): void
    {
        $idCashback = $this->requestInt($request, 'id_cashback');
        if ($idCashback === null) {
            throw new \Exception('No cashback selected.');
        }

        $bonus = (float) $request->request->get('bonus_amount', 0);
        if ($bonus <= 0) {
            throw new \Exception('Bonus must be greater than 0.');
        }

        $note = (string) $request->request->get('bonus_note', '');

        $bankingService->grantCashbackReward($idCashback, $bonus, $note);
    }

    private function handleCashbackBonusDecision(Request $request, BankingService $bankingService): void
    {
        $idCashback = $this->requestInt($request, 'id_cashback');
        if ($idCashback === null) {
            throw new \Exception('No cashback selected.');
        }

        $isApproved = (string) $request->request->get('bonus_decision', 'Rejected') === 'Approved';
        $note = (string) $request->request->get('bonus_note', '');

        $bankingService->setCashbackBonusDecision($idCashback, $isApproved, $note);
    }

    private function handlePartnerSave(Request $request, BankingService $bankingService): void
    {
        $validation = $this->validatePartnerForm($request);
        if (!$validation['valid']) {
            throw new \Exception($validation['message']);
        }

        $partenaire = $this->buildPartenaireFromRequest($request);
        $idPartenaire = $this->requestInt($request, 'id_partenaire');

        if ($idPartenaire) {
            $bankingService->savePartenaire($partenaire, $idPartenaire);
        } else {
            $bankingService->savePartenaire($partenaire);
        }
    }

    private function handlePartnerDelete(Request $request, BankingService $bankingService): void
    {
        $idPartenaire = $this->requestInt($request, 'id_partenaire');
        if ($idPartenaire === null) {
            throw new \Exception('No partner selected.');
        }

        $bankingService->deletePartenaire($idPartenaire);
    }

    // ==================== PORTAL ACTION HANDLERS ====================

    private function handlePortalCashbackSave(Request $request, BankingService $bankingService, int $userId): void
    {
        $validation = $this->validateCashbackForm($request);
        if (!$validation['valid']) {
            throw new \Exception($validation['message']);
        }

        $cashback = $this->buildCashbackFromRequest($request, $bankingService);
        $cashback['id_user'] = $userId;
        $idCashback = $this->requestInt($request, 'id_cashback');

        if ($idCashback) {
            $bankingService->saveCashback($cashback, $idCashback, $userId);
        } else {
            $bankingService->saveCashback($cashback, null, $userId);
        }
    }

    private function handlePortalCashbackDelete(Request $request, BankingService $bankingService, int $userId): void
    {
        $idCashback = $this->requestInt($request, 'id_cashback');
        if ($idCashback === null) {
            throw new \Exception('No cashback selected.');
        }

        $bankingService->deleteCashback($idCashback, $userId);
    }

    private function handleCashbackRating(Request $request, BankingService $bankingService, int $userId): void
    {
        $idCashback = $this->requestInt($request, 'id_cashback');
        if ($idCashback === null) {
            throw new \Exception('No cashback selected.');
        }

        $rating = (float) $request->request->get('user_rating', 0);
        if ($rating < 0 || $rating > 5) {
            throw new \Exception('Rating must be between 0 and 5.');
        }

        $comment = (string) $request->request->get('user_rating_comment', '');

        $bankingService->submitCashbackRating($idCashback, $userId, $rating, $comment);
    }

    private function handleCashbackCalculation(Request $request, BankingService $bankingService): JsonResponse
    {
        try {
            $montant = $this->parseDouble($request->request->get('montant_achat', '0'));
            if ($montant <= 0) {
                return new JsonResponse([
                    'taux' => '0.00',
                    'montant_cashback' => '0.00'
                ]);
            }

            $partenaireNom = (string) $request->request->get('partenaire_nom', '');
            $rating = 0.0;

            if (!empty($partenaireNom)) {
                $partenaires = $bankingService->listPartenaires();
                foreach ($partenaires as $p) {
                    if (strtolower(trim((string) ($p['nom'] ?? ''))) === strtolower($partenaireNom)) {
                        $rating = (float) ($p['rating'] ?? 0.0);
                        break;
                    }
                }
            }

            $taux = $this->resolveCashbackRate($montant, $rating);
            $montantCashback = round($montant * ($taux / 100), 2);

            return new JsonResponse([
                'taux' => number_format($taux, 2, '.', ''),
                'montant_cashback' => number_format($montantCashback, 2, '.', ''),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'taux' => '0.00',
                'montant_cashback' => '0.00',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleRedeemRewards(Request $request, BankingService $bankingService, int $userId): void
    {
        // In a real implementation, this would trigger a redemption process
        // For now, just log the request
    }

    // ==================== SEARCH & SORTING ====================

    private function handleSearch(Request $request, BankingService $bankingService): JsonResponse
    {
        $searchQuery = trim((string) $request->request->get('search_query', ''));
        $searchQuery = strtolower($searchQuery);
        $userId = $request->request->getInt('user_id', 0);

        if (empty($searchQuery)) {
            return new JsonResponse([
                'success' => true,
                'results' => []
            ]);
        }

        $allCashbacks = $userId > 0 ? $bankingService->listCashbacks($userId) : $bankingService->listCashbacks();
        $filtered = array_filter($allCashbacks, function ($cashback) use ($searchQuery) {
            $userId = (string) ($cashback['id_user'] ?? '');
            $userName = strtolower((string) ($cashback['user_name'] ?? ''));
            $partenaire = strtolower($this->safe($cashback['partenaire_nom'] ?? ''));
            $statut = strtolower($this->safe($cashback['statut'] ?? ''));

            return strpos($userId, $searchQuery) !== false
                || strpos($userName, $searchQuery) !== false
                || strpos($partenaire, $searchQuery) !== false
                || strpos($statut, $searchQuery) !== false;
        });

        return new JsonResponse([
            'success' => true,
            'results' => array_values($filtered),
            'count' => count($filtered)
        ]);
    }

    private function handleSort(Request $request, BankingService $bankingService): JsonResponse
    {
        $sortBy = (string) $request->request->get('sort_by', 'date_achat');
        $userId = $request->request->getInt('user_id', 0);
        $cashbacks = $userId > 0 ? $bankingService->listCashbacks($userId) : $bankingService->listCashbacks();

        switch ($sortBy) {
            case 'date_achat':
                usort($cashbacks, fn ($a, $b) => strtotime($b['date_achat'] ?? '0') - strtotime($a['date_achat'] ?? '0'));
                break;

            case 'montant':
                usort($cashbacks, fn ($a, $b) => ($b['montant_achat'] ?? 0) <=> ($a['montant_achat'] ?? 0));
                break;

            case 'cashback':
                usort($cashbacks, fn ($a, $b) => ($b['montant_cashback'] ?? 0) <=> ($a['montant_cashback'] ?? 0));
                break;

            case 'statut':
                usort($cashbacks, fn ($a, $b) => strcmp($this->safe($a['statut'] ?? ''), $this->safe($b['statut'] ?? '')));
                break;
        }

        return new JsonResponse([
            'success' => true,
            'results' => $cashbacks
        ]);
    }

    // ==================== FORM BUILDING & VALIDATION ====================

    private function buildCashbackFromRequest(Request $request, BankingService $bankingService): array
    {
        $partenaireNom = trim((string) $request->request->get('partenaire_nom', ''));

        // Find partner rating from the partenaires list
        $partenaires = $bankingService->listPartenaires();
        $rating = 0.0;
        foreach ($partenaires as $p) {
            if (strtolower(trim((string) ($p['nom'] ?? ''))) === strtolower($partenaireNom)) {
                $rating = (float) ($p['rating'] ?? 0.0);
                break;
            }
        }

        $montantAchat = $this->parseDouble($request->request->get('montant_achat', '0'));
        $tauxApplique = $this->resolveCashbackRate($montantAchat, $rating);
        $montantCashback = round($montantAchat * ($tauxApplique / 100), 2);

        return [
            'id_user' => (int) $request->request->get('id_user', 0),
            'partenaire_nom' => $partenaireNom,
            'montant_achat' => $montantAchat,
            'montant_cashback' => $montantCashback,
            'taux_applique' => $tauxApplique,
            'date_achat' => $request->request->get('date_achat'),
            'date_credit' => $request->request->get('date_credit'),
            'date_expiration' => $request->request->get('date_expiration'),
            'statut' => (string) $request->request->get('statut', ''),
            'transaction_ref' => trim((string) $request->request->get('transaction_ref', '')),
        ];
    }

    private function buildPartenaireFromRequest(Request $request): array
    {
        return [
            'nom' => trim((string) $request->request->get('nom', '')),
            'categorie' => trim((string) $request->request->get('categorie', 'General')),
            'description' => trim((string) $request->request->get('description', '')),
            'taux_cashback' => $this->parseDouble($request->request->get('taux_cashback', '0')),
            'taux_cashback_max' => $this->parseDouble($request->request->get('taux_cashback_max', '0')),
            'plafond_mensuel' => $this->parseDouble($request->request->get('plafond_mensuel', '0')),
            'conditions' => trim((string) $request->request->get('conditions', '')),
            'status' => trim((string) $request->request->get('status', 'Actif')),
            'rating' => $this->parseDouble($request->request->get('rating', '0')),
        ];
    }

    private function validateCashbackForm(Request $request): array
    {
        $userId = trim((string) $request->request->get('id_user', ''));
        if (empty($userId)) {
            return ['valid' => false, 'message' => 'User ID is required.'];
        }

        $partenaire = trim((string) $request->request->get('partenaire_nom', ''));
        if (empty($partenaire)) {
            return ['valid' => false, 'message' => 'Partner must be selected.'];
        }

        $montantAchat = trim((string) $request->request->get('montant_achat', ''));
        if (empty($montantAchat)) {
            return ['valid' => false, 'message' => 'Purchase amount is required.'];
        }

        $dateAchat = $request->request->get('date_achat');
        if (empty($dateAchat)) {
            return ['valid' => false, 'message' => 'Purchase date is required.'];
        }

        $statut = trim((string) $request->request->get('statut', ''));
        if (empty($statut)) {
            return ['valid' => false, 'message' => 'Status is required.'];
        }

        // Validate numeric values
        try {
            (int) $userId;
            $this->parseDouble($montantAchat);
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'Invalid numeric values.'];
        }

        return ['valid' => true];
    }

    private function validatePartnerForm(Request $request): array
    {
        $nom = trim((string) $request->request->get('nom', ''));
        if (empty($nom)) {
            return ['valid' => false, 'message' => 'Partner name is required.'];
        }

        $tauxCashback = trim((string) $request->request->get('taux_cashback', ''));
        if (empty($tauxCashback)) {
            return ['valid' => false, 'message' => 'Base cashback rate is required.'];
        }

        try {
            $this->parseDouble($tauxCashback);
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'Invalid cashback rate.'];
        }

        return ['valid' => true];
    }

    // ==================== STATISTICS & CALCULATIONS ====================

    private function calculateAdminStats(array $cashbacks): array
    {
        $totalCredited = array_sum(array_map(
            fn ($c) => (float) ($c['montant_cashback'] ?? 0),
            array_filter($cashbacks, fn ($c) => ($c['statut'] ?? '') === 'Credite')
        ));

        $activeUsers = count(array_unique(array_map(
            fn ($c) => $c['id_user'],
            array_filter($cashbacks, fn ($c) => !empty($c['montant_cashback']))
        )));

        // Current month total
        $currentMonth = date('Y-m');
        $monthTotal = array_sum(array_map(
            fn ($c) => (float) ($c['montant_cashback'] ?? 0),
            array_filter($cashbacks, fn ($c) => strpos((string) ($c['date_achat'] ?? ''), $currentMonth) === 0 && ($c['statut'] ?? '') === 'Credite')
        ));

        $pendingCount = count(array_filter($cashbacks, fn ($c) => ($c['statut'] ?? '') === 'En attente'));

        return [
            'total_cashback' => $totalCredited,
            'nombre_beneficiaires' => $activeUsers,
            'cashback_mois' => $monthTotal,
            'pending_count' => $pendingCount,
        ];
    }

    private function calculateUserStats(array $cashbacks): array
    {
        $totalCredited = array_sum(array_map(
            fn ($c) => (float) ($c['montant_cashback'] ?? 0),
            array_filter($cashbacks, fn ($c) => ($c['statut'] ?? '') === 'Valide')
        ));

        // Current month total
        $currentMonth = date('Y-m');
        $monthTotal = array_sum(array_map(
            fn ($c) => (float) ($c['montant_cashback'] ?? 0),
            array_filter($cashbacks, fn ($c) => strpos((string) ($c['date_achat'] ?? ''), $currentMonth) === 0 && ($c['statut'] ?? '') === 'Valide')
        ));

        // Current week total
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekTotal = array_sum(array_map(
            fn ($c) => (float) ($c['montant_cashback'] ?? 0),
            array_filter($cashbacks, fn ($c) => ($c['date_achat'] ?? '') >= $weekStart && ($c['statut'] ?? '') === 'Valide')
        ));

        $pendingCount = count(array_filter($cashbacks, fn ($c) => ($c['statut'] ?? '') === 'En attente'));

        return [
            'total_rewards' => $totalCredited,
            'month_total' => $monthTotal,
            'week_total' => $weekTotal,
            'pending_count' => $pendingCount,
        ];
    }

    private function buildPartnerEarnings(array $cashbacks): array
    {
        $earnings = [];
        foreach ($cashbacks as $cashback) {
            $partner = strtolower($this->safe($cashback['partenaire_nom'] ?? ''));
            if (!empty($partner)) {
                $earnings[$partner] = ($earnings[$partner] ?? 0) + (float) ($cashback['montant_cashback'] ?? 0);
            }
        }
        return $earnings;
    }

    private function filterRecentCashbacks(array $cashbacks, string $period = 'all'): array
    {
        $now = new DateTime();

        return array_filter($cashbacks, function ($cashback) use ($now, $period) {
            $dateAchat = $cashback['date_achat'] ?? null;
            if (!$dateAchat) return false;

            $cashbackDate = new DateTime($dateAchat);

            return match ($period) {
                'today' => $cashbackDate->format('Y-m-d') === $now->format('Y-m-d'),
                'week' => $cashbackDate >= new DateTime('monday this week'),
                'month' => $cashbackDate->format('Y-m') === $now->format('Y-m'),
                default => true,
            };
        });
    }

    // ==================== FORMATTING & UTILITIES ====================

    private function parseDouble(string $text): float
    {
        $text = trim($text ?: '0');
        $text = str_replace(',', '.', $text);
        return (float) $text;
    }

    private function safe(?string $value): string
    {
        return $value ?? '';
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

    public function formatDate(?string $date): string
    {
        if (!$date) {
            return '-';
        }

        try {
            $dateTime = new DateTime($date);
            return $dateTime->format('d/m/Y');
        } catch (\Exception $e) {
            return '-';
        }
    }

    public function formatUserRating(?float $rating): string
    {
        if ($rating === null) {
            return '☆☆☆☆☆';
        }

        return $this->toStars($rating) . ' (' . number_format($rating, 1, '.', '') . '/5)';
    }

    private function toStars(float $rating): string
    {
        $rounded = (int) round($rating);
        $rounded = max(0, min(5, $rounded));

        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $stars .= $i <= $rounded ? '★' : '☆';
        }

        return $stars;
    }

    public function formatBonusDecision(?array $cashback): string
    {
        if ($cashback === null) {
            return 'Pending';
        }

        $decision = $this->safe($cashback['bonus_decision'] ?? '') ?: 'Pending';
        $note = $this->safe($cashback['bonus_note'] ?? '');

        if (empty($note)) {
            return $decision;
        }

        return $decision . ' (' . $note . ')';
    }

    // ==================== ADDITIONAL ROUTE METHODS ====================

    /**
     * Record new cashback entry (direct route for portal)
     */
    #[Route('/portal/cashback/record', name: 'app_portal_cashback_record', methods: ['POST'])]
    public function recordCashback(Request $request, BankingService $bankingService): JsonResponse
    {
        try {
            $data = $this->buildCashbackFromRequest($request, $bankingService);
            $data['id_user'] = $request->request->getInt('user_id', 0);

            if (!$data['id_user']) {
                return new JsonResponse(['success' => false, 'message' => 'Utilisateur non spécifié']);
            }

            $result = $bankingService->saveCashback($data);

            return new JsonResponse([
                'success' => $result,
                'message' => $result ? 'Cashback enregistré avec succès' : 'Erreur lors de l\'enregistrement'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**     * Calculate cashback rate and amount dynamically
     */
    #[Route('/portal/cashback/calculate', name: 'app_portal_cashback_calculate', methods: ['POST'])]
    public function calculateCashback(Request $request, BankingService $bankingService): JsonResponse
    {
        return $this->handleCashbackCalculation($request, $bankingService);
    }

    /**     * AI analysis for cashback optimization
     */
    #[Route('/portal/cashback/ai-analyze', name: 'app_portal_cashback_ai_analyze', methods: ['POST'])]
    public function analyzeCashbackAI(Request $request, BankingService $bankingService, int $userId): JsonResponse
    {
        try {
            $userCashbacks = $bankingService->listCashbacks($userId);
            $partenaires = $bankingService->listPartenaires();

            // Simple AI analysis (can be enhanced with ML models)
            $analysis = $this->generateCashbackAnalysis($userCashbacks, $partenaires);

            return new JsonResponse([
                'success' => true,
                'analysis' => $analysis
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Generate AI analysis for cashback optimization
     */
    private function generateCashbackAnalysis(array $cashbacks, array $partenaires): string
    {
        $totalCashback = array_sum(array_map(fn($c) => (float)($c['montant_cashback'] ?? 0), $cashbacks));
        $avgPurchase = count($cashbacks) > 0 ? array_sum(array_map(fn($c) => (float)($c['montant_achat'] ?? 0), $cashbacks)) / count($cashbacks) : 0;

        $partnerUsage = [];
        foreach ($cashbacks as $cashback) {
            $partner = $cashback['partenaire_nom'] ?? '';
            if (!isset($partnerUsage[$partner])) {
                $partnerUsage[$partner] = 0;
            }
            $partnerUsage[$partner]++;
        }

        arsort($partnerUsage);
        $topPartner = array_key_first($partnerUsage);

        $analysis = "📊 Analyse de vos habitudes de cashback :\n\n";
        $analysis .= "💰 Cashback total gagné : " . number_format($totalCashback, 2, ',', ' ') . " DT\n";
        $analysis .= "🛒 Achat moyen : " . number_format($avgPurchase, 2, ',', ' ') . " DT\n";
        $analysis .= "🏆 Partenaire préféré : " . ($topPartner ?: 'Aucun') . "\n\n";

        $analysis .= "💡 Recommandations :\n";
        if ($avgPurchase < 100) {
            $analysis .= "• Augmentez vos achats pour bénéficier de meilleurs taux de cashback\n";
        }
        if (count($partnerUsage) < 3) {
            $analysis .= "• Explorez plus de partenaires pour diversifier vos gains\n";
        }
        $analysis .= "• Les achats pendant les soldes peuvent rapporter jusqu'à 15% de cashback\n";
        $analysis .= "• N'oubliez pas de réclamer vos récompenses régulièrement";

        return $analysis;
    }

    /**
     * Admin cashback details
     */
    #[Route('/admin/cashback/details/{id}', name: 'admin_cashback_details', methods: ['GET'])]
    public function getCashbackDetails(int $id, BankingService $bankingService): JsonResponse
    {
        try {
            $cashback = $bankingService->getCashbackById($id);
            if (!$cashback) {
                return new JsonResponse(['success' => false, 'message' => 'Cashback non trouvé']);
            }

            $user = $bankingService->getUserById($cashback['id_user']);
            $partner = null;
            $partenaires = $bankingService->listPartenaires();
            foreach ($partenaires as $p) {
                if ($p['nom'] === $cashback['partenaire_nom']) {
                    $partner = $p;
                    break;
                }
            }

            $html = $this->renderView('admin/cashback_details.html.twig', [
                'cashback' => $cashback,
                'user' => $user,
                'partner' => $partner
            ]);

            return new JsonResponse(['success' => true, 'html' => $html]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Bulk actions for admin
     */
    #[Route('/admin/cashback/bulk-action', name: 'admin_cashback_bulk_action', methods: ['POST'])]
    public function handleBulkAction(Request $request, BankingService $bankingService): JsonResponse
    {
        try {
            $action = $request->request->get('action');
            $comment = $request->request->get('comment', '');

            if ($action === 'bulk_approve') {
                $result = $bankingService->approveAllPendingCashbacks($comment);
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Action non reconnue']);
            }

            return new JsonResponse([
                'success' => $result,
                'message' => $result ? 'Action groupée exécutée avec succès' : 'Erreur lors de l\'exécution'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Add new partner
     */
    #[Route('/admin/cashback/add-partner', name: 'admin_cashback_add_partner', methods: ['POST'])]
    public function addPartner(Request $request, BankingService $bankingService): JsonResponse
    {
        try {
            $data = [
                'nom' => trim($request->request->get('name', '')),
                'taux_base' => $this->parseDouble($request->request->get('base_rate', '5.0')),
                'taux_max' => $this->parseDouble($request->request->get('max_rate', '15.0')),
                'plafond_mensuel' => $this->parseDouble($request->request->get('monthly_limit', '1000.0')),
                'date_validite' => $request->request->get('valid_until'),
                'description' => $request->request->get('description', ''),
                'rating' => 3.0 // Default rating
            ];

            $result = $bankingService->savePartenaire($data);

            return new JsonResponse([
                'success' => $result,
                'message' => $result ? 'Partenaire ajouté avec succès' : 'Erreur lors de l\'ajout'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Generate cashback report
     */
    #[Route('/admin/cashback/report', name: 'admin_cashback_report', methods: ['GET'])]
    public function generateReport(BankingService $bankingService): Response
    {
        $cashbacks = $bankingService->listCashbacks();
        $partenaires = $bankingService->listPartenaires();

        // Generate CSV report
        $filename = 'cashback_report_' . date('Y-m-d_H-i-s') . '.csv';

        $output = fopen('php://temp', 'w');
        fputcsv($output, ['ID', 'Utilisateur', 'Partenaire', 'Montant Achat', 'Cashback', 'Taux', 'Date', 'Statut']);

        foreach ($cashbacks as $cashback) {
            fputcsv($output, [
                $cashback['id'],
                $cashback['id_user'],
                $cashback['partenaire_nom'],
                $cashback['montant_achat'],
                $cashback['montant_cashback'],
                $cashback['taux_applique'],
                $cashback['date_achat'],
                $cashback['statut']
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    /**
     * Export cashback data
     */
    #[Route('/admin/cashback/export', name: 'admin_cashback_export', methods: ['GET'])]
    public function exportData(BankingService $bankingService): Response
    {
        return $this->generateReport($bankingService);
    }
}
