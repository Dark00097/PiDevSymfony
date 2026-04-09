<?php

namespace App\Controller\Sections;

use App\Service\BankingService;
use Symfony\Component\HttpFoundation\Request;

final class TransactionsController
{
    public function buildAdminData(BankingService $bankingService): array
    {
        $transactions = $bankingService->listTransactions();

        return [
            'items' => $transactions,
            'support' => [
                'users' => $bankingService->listUsers(),
                'accounts' => $bankingService->listAccounts(),
                'transactions' => $transactions,
            ],
        ];
    }

    public function buildPortalData(BankingService $bankingService, int $userId): array
    {
        return [
            'items' => $bankingService->listTransactions($userId),
            'support' => [
                'accounts' => $bankingService->listAccounts($userId),
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
