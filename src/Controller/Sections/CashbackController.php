<?php

namespace App\Controller\Sections;

use App\Service\AuthService;
use App\Service\BankingService;
use App\Service\CashbackCompanionService;
use App\Service\ExportService;
use App\Service\QrSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CashbackController extends AbstractController
{
    public function buildAdminData(BankingService $bankingService): array
    {
        $cashbacks = $bankingService->listCashbacks();
        $partners = $bankingService->listPartenaires();

        return [
            'items' => $cashbacks,
            'support' => [
                'users' => $bankingService->listUsers(),
                'cashbacks' => $cashbacks,
                'cashback_items' => $cashbacks,
                'partners' => $partners,
                'partenaires' => $partners,
                'partners_items' => $partners,
                'stats' => $this->calculateAdminStats($cashbacks),
            ],
        ];
    }

    public function buildPortalData(BankingService $bankingService, int $userId): array
    {
        $cashbacks = $bankingService->listCashbacks($userId);
        $partners = $bankingService->listPartenaires();

        return [
            'items' => $cashbacks,
            'support' => [
                'partners' => $partners,
                'partenaires' => $partners,
                'stats' => $this->calculateUserStats($cashbacks),
                'partner_earnings' => $this->buildPartnerEarnings($cashbacks),
                'recent_cashbacks' => $this->filterRecentCashbacks($cashbacks),
                'assistant_starters' => [
                    'Quelles sont les meilleures offres cashback du moment ?',
                    'Donne-moi un resume de mes cashback en attente',
                    'Comment optimiser mes partenaires cashback ?',
                ],
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        try {
            switch ($action) {
                case 'cashback_save':
                    $data = $this->buildCashbackPayload($request, $bankingService);
                    $this->validateCashbackForm($data, true);
                    $bankingService->saveCashback($data, $this->requestInt($request, 'id_cashback'));

                    return ['type' => 'success', 'message' => 'Cashback saved.'];

                case 'cashback_delete':
                    $idCashback = $this->requestInt($request, 'id_cashback');
                    if ($idCashback === null) {
                        throw new \InvalidArgumentException('Cashback introuvable.');
                    }

                    $bankingService->deleteCashback($idCashback);

                    return ['type' => 'success', 'message' => 'Cashback deleted.'];

                case 'cashback_reward':
                    $idCashback = $this->requestInt($request, 'id_cashback');
                    if ($idCashback === null) {
                        throw new \InvalidArgumentException('Cashback introuvable.');
                    }

                    $bonusAmount = max(0, (float) $request->request->get('bonus_amount', 0));
                    if ($bonusAmount <= 0) {
                        throw new \InvalidArgumentException('Le bonus doit etre superieur a 0.');
                    }

                    $bankingService->grantCashbackReward(
                        $idCashback,
                        $bonusAmount,
                        trim((string) $request->request->get('bonus_note', ''))
                    );

                    return ['type' => 'success', 'message' => 'Cashback reward granted.'];

                case 'cashback_bonus':
                    $idCashback = $this->requestInt($request, 'id_cashback');
                    if ($idCashback === null) {
                        throw new \InvalidArgumentException('Cashback introuvable.');
                    }

                    $bankingService->setCashbackBonusDecision(
                        $idCashback,
                        (string) $request->request->get('bonus_decision', 'Rejected') === 'Approved',
                        trim((string) $request->request->get('bonus_note', ''))
                    );

                    return ['type' => 'success', 'message' => 'Cashback decision saved.'];
            }
        } catch (\Throwable $exception) {
            return ['type' => 'error', 'message' => $exception->getMessage()];
        }

        return null;
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        try {
            switch ($action) {
                case 'cashback_save':
                    $data = $this->buildCashbackPayload($request, $bankingService);
                    $this->validateCashbackForm($data, false);
                    $bankingService->saveCashback($data, $this->requestInt($request, 'id_cashback'), $userId);

                    return ['type' => 'success', 'message' => 'Cashback saved.'];

                case 'cashback_delete':
                    $idCashback = $this->requestInt($request, 'id_cashback');
                    if ($idCashback === null) {
                        throw new \InvalidArgumentException('Cashback introuvable.');
                    }

                    $bankingService->deleteCashback($idCashback, $userId);

                    return ['type' => 'success', 'message' => 'Cashback deleted.'];

                case 'cashback_rating':
                    $idCashback = $this->requestInt($request, 'id_cashback');
                    if ($idCashback === null) {
                        throw new \InvalidArgumentException('Cashback introuvable.');
                    }

                    $rating = (float) $request->request->get('user_rating', 0);
                    if ($rating < 0 || $rating > 5) {
                        throw new \InvalidArgumentException('La note doit etre comprise entre 0 et 5.');
                    }

                    $bankingService->submitCashbackRating(
                        $idCashback,
                        $userId,
                        $rating,
                        trim((string) $request->request->get('user_rating_comment', ''))
                    );

                    return ['type' => 'success', 'message' => 'Rating submitted.'];
            }
        } catch (\Throwable $exception) {
            return ['type' => 'error', 'message' => $exception->getMessage()];
        }

        return null;
    }

    #[Route('/portal/cashback/calculate', name: 'app_portal_cashback_calculate', methods: ['POST'])]
    public function calculateCashback(Request $request, BankingService $bankingService): JsonResponse
    {
        $amount = max(0, (float) $request->request->get('montant_achat', 0));
        if ($amount <= 0) {
            return new JsonResponse([
                'taux' => '0.00',
                'montant_cashback' => '0.00',
            ]);
        }

        $partner = $this->resolvePartnerInput($request, $bankingService);
        $rate = $this->resolveCashbackRate($amount, (float) ($partner['rating'] ?? 0.0));
        $cashbackAmount = round($amount * ($rate / 100), 2);

        return new JsonResponse([
            'partner_name' => (string) ($partner['name'] ?? ''),
            'taux' => number_format($rate, 2, '.', ''),
            'montant_cashback' => number_format($cashbackAmount, 2, '.', ''),
        ]);
    }

    #[Route('/portal/cashback/assistant', name: 'app_portal_cashback_assistant', methods: ['POST'])]
    public function assistant(
        Request $request,
        AuthService $authService,
        CashbackCompanionService $cashbackCompanionService
    ): JsonResponse {
        try {
            $user = $authService->getAuthenticatedUser($request->getSession());
            if ($user === null) {
                return $this->json(['ok' => false, 'message' => 'Utilisateur non authentifie.'], 401);
            }

            $reply = $cashbackCompanionService->buildAssistantReply(
                (int) ($user['idUser'] ?? 0),
                trim((string) $request->request->get('message', ''))
            );

            return $this->json([
                'ok' => true,
                'reply' => $reply,
            ]);
        } catch (\Throwable $exception) {
            return $this->json([
                'ok' => false,
                'message' => 'Assistant cashback indisponible pour le moment.',
                'debug' => $exception->getMessage(),
            ], 500);
        }
    }

    #[Route('/portal/cashback/bundle', name: 'app_portal_cashback_bundle', methods: ['GET'])]
    public function historyBundle(
        Request $request,
        AuthService $authService,
        CashbackCompanionService $cashbackCompanionService,
        QrSessionService $qrSessionService
    ): JsonResponse {
        try {
            $user = $authService->getAuthenticatedUser($request->getSession());
            if ($user === null) {
                return $this->json(['ok' => false, 'message' => 'Utilisateur non authentifie.'], 401);
            }

            $bundleData = $cashbackCompanionService->buildHistoryBundle((int) ($user['idUser'] ?? 0));
            $bundle = (array) $bundleData['bundle'];
            $payload = $cashbackCompanionService->buildQrPayload($bundle, (string) $bundleData['hash']);
            $svg = $qrSessionService->buildQrSvg($payload, 300);

            return $this->json([
                'ok' => true,
                'hash' => (string) $bundleData['hash'],
                'summary' => (array) ($bundle['summary'] ?? []),
                'generated_at' => (string) ($bundle['generated_at'] ?? ''),
                'recommended_partners' => (array) ($bundle['recommended_partners'] ?? []),
                'qr_image_data_url' => 'data:image/svg+xml;base64,'.base64_encode($svg),
                'download_url' => $this->generateUrl('app_portal_cashback_bundle_download'),
            ]);
        } catch (\Throwable $exception) {
            return $this->json([
                'ok' => false,
                'message' => 'Generation du QR cashback indisponible pour le moment.',
                'debug' => $exception->getMessage(),
            ], 500);
        }
    }

    #[Route('/portal/cashback/bundle/download', name: 'app_portal_cashback_bundle_download', methods: ['GET'])]
    public function downloadHistoryBundle(
        Request $request,
        AuthService $authService,
        CashbackCompanionService $cashbackCompanionService,
        ExportService $exportService
    ): Response {
        try {
            $user = $authService->getAuthenticatedUser($request->getSession());
            if ($user === null) {
                return new Response('Utilisateur non authentifie.', 401);
            }

            $bundleData = $cashbackCompanionService->buildHistoryBundle((int) ($user['idUser'] ?? 0));
            $pdf = $exportService->buildCashbackBundlePdf((array) $bundleData['bundle'], (string) $bundleData['hash']);
            $filename = sprintf(
                'cashback-bundle-user-%d-%s.pdf',
                (int) ($user['idUser'] ?? 0),
                date('Ymd-His')
            );

            return new Response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (\Throwable $exception) {
            return new Response('Impossible de generer le PDF cashback: '.$exception->getMessage(), 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCashbackPayload(Request $request, BankingService $bankingService): array
    {
        $payload = $request->request->all();
        $partner = $this->resolvePartnerInput($request, $bankingService);

        if (($partner['id'] ?? null) !== null) {
            $payload['id_partenaire'] = $partner['id'];
        }

        if (($partner['name'] ?? '') !== '') {
            $payload['partenaire_nom'] = $partner['name'];
        }

        $payload['montant_achat'] = max(0, (float) ($payload['montant_achat'] ?? 0));
        $payload['date_achat'] = trim((string) ($payload['date_achat'] ?? date('Y-m-d')));
        $payload['statut'] = trim((string) ($payload['statut'] ?? 'En attente'));
        $payload['transaction_ref'] = trim((string) ($payload['transaction_ref'] ?? ''));

        return $payload;
    }

    /**
     * @return array{id:?int,name:string,rating:float}
     */
    private function resolvePartnerInput(Request $request, BankingService $bankingService): array
    {
        $partnerId = $this->requestInt($request, 'id_partenaire');
        $partnerName = trim((string) $request->request->get('partenaire_nom', ''));

        foreach ($bankingService->listPartenaires() as $partner) {
            $currentId = isset($partner['idPartenaire']) ? (int) $partner['idPartenaire'] : null;
            $currentName = trim((string) ($partner['nom'] ?? ''));

            if ($partnerId !== null && $currentId === $partnerId) {
                return [
                    'id' => $currentId,
                    'name' => $currentName,
                    'rating' => (float) ($partner['rating'] ?? 0),
                ];
            }

            if ($partnerName !== '' && strcasecmp($currentName, $partnerName) === 0) {
                return [
                    'id' => $currentId,
                    'name' => $currentName,
                    'rating' => (float) ($partner['rating'] ?? 0),
                ];
            }
        }

        return [
            'id' => $partnerId,
            'name' => $partnerName,
            'rating' => 0.0,
        ];
    }


    /**
     * @param array<string, mixed> $data
     */
    private function validateCashbackForm(array $data, bool $requireUserId): void
    {
        if ($requireUserId && (int) ($data['id_user'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Veuillez selectionner un utilisateur.');
        }

        if (trim((string) ($data['partenaire_nom'] ?? '')) === '') {
            throw new \InvalidArgumentException('Veuillez selectionner ou saisir un partenaire.');
        }

        if ((float) ($data['montant_achat'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Le montant d achat doit etre superieur a 0.');
        }

        if (trim((string) ($data['date_achat'] ?? '')) === '') {
            throw new \InvalidArgumentException('La date d achat est obligatoire.');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $cashbacks
     * @return array<string, float|int>
     */
    private function calculateAdminStats(array $cashbacks): array
    {
        $totalCashback = 0.0;
        $activeUsers = [];
        $monthTotal = 0.0;
        $pendingCount = 0;
        $currentMonth = date('Y-m');

        foreach ($cashbacks as $cashback) {
            $amount = (float) ($cashback['montant_cashback'] ?? 0);
            $status = (string) ($cashback['statut'] ?? '');
            $date = (string) ($cashback['date_achat'] ?? '');

            if ($status === 'Credite') {
                $totalCashback += $amount;
            }

            if (!empty($cashback['id_user'])) {
                $activeUsers[(int) $cashback['id_user']] = true;
            }

            if (str_starts_with($date, $currentMonth) && $status === 'Credite') {
                $monthTotal += $amount;
            }

            if ($status === 'En attente') {
                $pendingCount++;
            }
        }

        return [
            'total_cashback' => $totalCashback,
            'nombre_beneficiaires' => count($activeUsers),
            'cashback_mois' => $monthTotal,
            'pending_count' => $pendingCount,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $cashbacks
     * @return array<string, float|int>
     */
    private function calculateUserStats(array $cashbacks): array
    {
        $totalRewards = 0.0;
        $monthTotal = 0.0;
        $weekTotal = 0.0;
        $pendingCount = 0;
        $currentMonth = date('Y-m');
        $weekStart = date('Y-m-d', strtotime('monday this week'));

        foreach ($cashbacks as $cashback) {
            $amount = (float) ($cashback['montant_cashback'] ?? 0);
            $status = (string) ($cashback['statut'] ?? '');
            $date = (string) ($cashback['date_achat'] ?? '');

            if ($status === 'Valide' || $status === 'Credite') {
                $totalRewards += $amount;
            }

            if (str_starts_with($date, $currentMonth) && ($status === 'Valide' || $status === 'Credite')) {
                $monthTotal += $amount;
            }

            if ($date >= $weekStart && ($status === 'Valide' || $status === 'Credite')) {
                $weekTotal += $amount;
            }

            if ($status === 'En attente') {
                $pendingCount++;
            }
        }

        return [
            'total_rewards' => $totalRewards,
            'month_total' => $monthTotal,
            'week_total' => $weekTotal,
            'pending_count' => $pendingCount,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $cashbacks
     * @return array<string, float>
     */
    private function buildPartnerEarnings(array $cashbacks): array
    {
        $earnings = [];

        foreach ($cashbacks as $cashback) {
            $name = strtolower(trim((string) ($cashback['partenaire_nom'] ?? '')));
            if ($name === '') {
                continue;
            }

            $earnings[$name] = ($earnings[$name] ?? 0.0) + (float) ($cashback['montant_cashback'] ?? 0);
        }

        return $earnings;
    }

    /**
     * @param array<int, array<string, mixed>> $cashbacks
     * @return array<int, array<string, mixed>>
     */
    private function filterRecentCashbacks(array $cashbacks, string $period = 'all'): array
    {
        $now = new \DateTimeImmutable();

        return array_values(array_filter($cashbacks, static function (array $cashback) use ($now, $period): bool {
            $dateRaw = trim((string) ($cashback['date_achat'] ?? ''));
            if ($dateRaw === '') {
                return false;
            }

            try {
                $cashbackDate = new \DateTimeImmutable($dateRaw);
            } catch (\Throwable) {
                return false;
            }

            return match ($period) {
                'today' => $cashbackDate->format('Y-m-d') === $now->format('Y-m-d'),
                'week' => $cashbackDate >= new \DateTimeImmutable('monday this week'),
                'month' => $cashbackDate->format('Y-m') === $now->format('Y-m'),
                default => true,
            };
        }));
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
