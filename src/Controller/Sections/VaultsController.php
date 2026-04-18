<?php

namespace App\Controller\Sections;

use App\Service\BankingService;
use Symfony\Component\HttpFoundation\Request;

final class VaultsController
{
    public function buildAdminData(BankingService $bankingService): array
    {
        $vaults = $bankingService->listVaults();

        return [
            'items' => $vaults,
            'support' => [
                'vaults' => $vaults,
            ],
        ];
    }

    public function buildPortalData(BankingService $bankingService, int $userId): array
    {
        $vaults = $bankingService->listVaults($userId);

        return [
            'items' => $vaults,
            'support' => [
                'accounts' => $bankingService->listAccounts($userId),
                'vaults' => $vaults,
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'vault_save':
                $bankingService->saveVault($request->request->all(), $this->requestInt($request, 'idCoffre'));

                return ['type' => 'success', 'message' => 'Vault saved.'];

            case 'vault_delete':
                $bankingService->deleteVault($this->requestInt($request, 'idCoffre') ?? 0);

                return ['type' => 'success', 'message' => 'Vault deleted.'];
        }

        return null;
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'vault_save':
                $bankingService->saveVault($request->request->all(), $this->requestInt($request, 'idCoffre'), $userId);

                return ['type' => 'success', 'message' => 'Vault saved.'];

            case 'vault_delete':
                $bankingService->deleteVault($this->requestInt($request, 'idCoffre') ?? 0, $userId);

                return ['type' => 'success', 'message' => 'Vault deleted.'];
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
