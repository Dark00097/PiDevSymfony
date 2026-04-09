<?php

namespace App\Controller\Sections;

use App\Service\BankingService;
use Symfony\Component\HttpFoundation\Request;

final class GarantiesController
{
    public function buildAdminData(BankingService $bankingService): array
    {
        $garanties = $bankingService->listGaranties();

        return [
            'items' => $garanties,
            'support' => [
                'garanties' => $garanties,
                'garantie_type_stats' => $bankingService->getGarantieTypeDistribution(),
            ],
        ];
    }

    public function buildPortalData(BankingService $bankingService, int $userId): array
    {
        $garanties = $bankingService->listGaranties($userId);

        return [
            'items' => $garanties,
            'support' => [
                'garanties' => $garanties,
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'garantie_save':
                $bankingService->saveGarantie($request->request->all(), $this->requestInt($request, 'idGarantie'));

                return ['type' => 'success', 'message' => 'Garantie saved.'];

            case 'garantie_delete':
                $bankingService->deleteGarantie($this->requestInt($request, 'idGarantie') ?? 0);

                return ['type' => 'success', 'message' => 'Garantie deleted.'];
        }

        return null;
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'garantie_save':
                $bankingService->saveGarantie($request->request->all(), $this->requestInt($request, 'idGarantie'), $userId);

                return ['type' => 'success', 'message' => 'Garantie saved.'];

            case 'garantie_delete':
                $bankingService->deleteGarantie($this->requestInt($request, 'idGarantie') ?? 0, $userId);

                return ['type' => 'success', 'message' => 'Garantie deleted.'];
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
