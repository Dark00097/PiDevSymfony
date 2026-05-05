<?php

namespace App\Controller\Sections;

use App\Form\CoffrevirtuelType;
use App\Service\BankingService;
use Symfony\Component\HttpFoundation\Request;

final class CoffrevirtuelleController
{
    /**
     * @param int|null $filterUserId
     * @return array<string, mixed>
     */
    public function buildAdminData(BankingService $bankingService, ?int $filterUserId = null): array
    {
        $vaults = $bankingService->listVaults($filterUserId);

        return [
            'items' => $vaults,
            'support' => [
                'vaults' => $vaults,
                'forms' => [
                    'coffrevirtuel' => CoffrevirtuelType::class,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPortalData(BankingService $bankingService, int $userId): array
    {
        $vaults = $bankingService->listVaults($userId);

        return [
            'items' => $vaults,
            'support' => [
                'accounts' => $bankingService->listAccounts($userId),
                'vaults' => $vaults,
                'forms' => [
                    'coffrevirtuel' => CoffrevirtuelType::class,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $currentUser
     * @return array<string, mixed>|null
     */
    public function handleAdminAction(string $action, Request $request, BankingService $bankingService, ?array $currentUser = null): ?array
    {
        $connectedUserId = $currentUser !== null ? (int) ($currentUser['idUser'] ?? 0) : null;

        switch ($action) {
            case 'vault_save':
                $data = $request->request->all();
                if (($data['idUser'] ?? '') === '' && $connectedUserId !== null && $connectedUserId > 0) {
                    $data['idUser'] = (string) $connectedUserId;
                }
                $bankingService->saveVault($data, $this->requestInt($request, 'idCoffre'));

                return ['type' => 'success', 'message' => 'Vault saved.'];

            case 'vault_delete':
                $bankingService->deleteVault($this->requestInt($request, 'idCoffre') ?? 0);

                return ['type' => 'success', 'message' => 'Vault deleted.'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
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
