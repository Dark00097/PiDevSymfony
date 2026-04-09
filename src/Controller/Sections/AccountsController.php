<?php

namespace App\Controller\Sections;

use App\Service\ActivityService;
use App\Service\BankingService;
use App\Service\GamificationService;
use Symfony\Component\HttpFoundation\Request;

final class AccountsController
{
    public function buildAdminData(BankingService $bankingService): array
    {
        $accounts = $bankingService->listAccounts();

        return [
            'items' => $accounts,
            'support' => [
                'users' => $bankingService->listUsers(),
                'accounts' => $accounts,
            ],
        ];
    }

    public function buildPortalData(
        BankingService $bankingService,
        ActivityService $activityService,
        GamificationService $gamificationService,
        int $userId
    ): array {
        $accounts = $bankingService->listAccounts($userId);

        return [
            'items' => $accounts,
            'support' => [
                'wheel' => $gamificationService->getWheelStatus($userId),
                'activity' => $activityService->listRecent($userId, 40),
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'account_save':
                $bankingService->saveAccount($request->request->all(), $this->requestInt($request, 'idCompte'));

                return ['type' => 'success', 'message' => 'Account saved.'];

            case 'account_delete':
                $bankingService->deleteAccount($this->requestInt($request, 'idCompte') ?? 0);

                return ['type' => 'success', 'message' => 'Account deleted.'];
        }

        return null;
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'account_save':
                $bankingService->saveAccount($request->request->all(), $this->requestInt($request, 'idCompte'), $userId);

                return ['type' => 'success', 'message' => 'Account saved.'];

            case 'account_delete':
                $bankingService->deleteAccount($this->requestInt($request, 'idCompte') ?? 0, $userId);

                return ['type' => 'success', 'message' => 'Account deleted.'];
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
