<?php

namespace App\Controller\Sections;

use App\Service\BankingService;
use Symfony\Component\HttpFoundation\Request;

final class CreditsController
{
    public function buildAdminData(BankingService $bankingService): array
    {
        $credits = $bankingService->listCredits();

        return [
            'items' => $credits,
            'support' => [
                'users' => $bankingService->listUsers(),
                'accounts' => $bankingService->listAccounts(),
                'credits' => $credits,
                'credit_type_stats' => $bankingService->getCreditTypeDistribution(),
            ],
        ];
    }

    public function buildPortalData(BankingService $bankingService, int $userId): array
    {
        $credits = $bankingService->listCredits($userId);

        return [
            'items' => $credits,
            'support' => [
                'accounts' => $bankingService->listAccounts($userId),
                'credits' => $credits,
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'credit_save':
                $bankingService->saveCredit($request->request->all(), $this->requestInt($request, 'idCredit'));

                return ['type' => 'success', 'message' => 'Credit saved.'];

            case 'credit_delete':
                $bankingService->deleteCredit($this->requestInt($request, 'idCredit') ?? 0);

                return ['type' => 'success', 'message' => 'Credit deleted.'];
        }

        return null;
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'credit_save':
                $bankingService->saveCredit($request->request->all(), $this->requestInt($request, 'idCredit'), $userId);

                return ['type' => 'success', 'message' => 'Credit saved.'];

            case 'credit_delete':
                $bankingService->deleteCredit($this->requestInt($request, 'idCredit') ?? 0, $userId);

                return ['type' => 'success', 'message' => 'Credit deleted.'];
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
