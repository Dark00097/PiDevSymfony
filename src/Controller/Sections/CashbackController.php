<?php

namespace App\Controller\Sections;

use App\Service\BankingService;
use Symfony\Component\HttpFoundation\Request;

final class CashbackController
{
    public function buildAdminData(BankingService $bankingService): array
    {
        $cashbacks = $bankingService->listCashbacks();

        return [
            'items' => $cashbacks,
            'support' => [
                'users' => $bankingService->listUsers(),
                'cashbacks' => $cashbacks,
            ],
        ];
    }

    public function buildPortalData(BankingService $bankingService, int $userId): array
    {
        return [
            'items' => $bankingService->listCashbacks($userId),
            'support' => [],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'cashback_save':
                $bankingService->saveCashback($request->request->all(), $this->requestInt($request, 'id_cashback'));

                return ['type' => 'success', 'message' => 'Cashback saved.'];

            case 'cashback_delete':
                $bankingService->deleteCashback($this->requestInt($request, 'id_cashback') ?? 0);

                return ['type' => 'success', 'message' => 'Cashback deleted.'];

            case 'cashback_reward':
                $bankingService->grantCashbackReward(
                    $this->requestInt($request, 'id_cashback') ?? 0,
                    (float) $request->request->get('bonus_amount', 0),
                    (string) $request->request->get('bonus_note', '')
                );

                return ['type' => 'success', 'message' => 'Cashback reward granted.'];

            case 'cashback_bonus':
                $bankingService->setCashbackBonusDecision(
                    $this->requestInt($request, 'id_cashback') ?? 0,
                    (string) $request->request->get('bonus_decision', 'Rejected') === 'Approved',
                    (string) $request->request->get('bonus_note', '')
                );

                return ['type' => 'success', 'message' => 'Cashback decision saved.'];
        }

        return null;
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'cashback_save':
                $bankingService->saveCashback($request->request->all(), $this->requestInt($request, 'id_cashback'), $userId);

                return ['type' => 'success', 'message' => 'Cashback saved.'];

            case 'cashback_delete':
                $bankingService->deleteCashback($this->requestInt($request, 'id_cashback') ?? 0, $userId);

                return ['type' => 'success', 'message' => 'Cashback deleted.'];

            case 'cashback_rating':
                $bankingService->submitCashbackRating(
                    $this->requestInt($request, 'id_cashback') ?? 0,
                    $userId,
                    (float) $request->request->get('user_rating', 0),
                    (string) $request->request->get('user_rating_comment', '')
                );

                return ['type' => 'success', 'message' => 'Rating submitted.'];
        }

        return null;
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
