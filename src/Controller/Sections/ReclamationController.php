<?php

namespace App\Controller\Sections;

use App\Form\ReclamationType;
use App\Service\BankingService;
use Symfony\Component\HttpFoundation\Request;

final class ReclamationController
{
    public function buildAdminData(BankingService $bankingService): array
    {
        $reclamations = $bankingService->listReclamations();

        return [
            'items' => $reclamations,
            'support' => [
                'reclamations' => $reclamations,
                'forms' => [
                    'reclamation' => ReclamationType::class,
                ],
            ],
        ];
    }

    public function buildPortalData(BankingService $bankingService, int $userId): array
    {
        return [
            'items' => $bankingService->listReclamations($userId),
            'support' => [
                'transactions' => $bankingService->listTransactions($userId),
                'forms' => [
                    'reclamation' => ReclamationType::class,
                ],
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'reclamation_save':
                $bankingService->saveReclamation($request->request->all(), $this->requestInt($request, 'idReclamation'));

                return ['type' => 'success', 'message' => 'Reclamation saved.'];

            case 'reclamation_delete':
                $bankingService->deleteReclamation($this->requestInt($request, 'idReclamation') ?? 0);

                return ['type' => 'success', 'message' => 'Reclamation deleted.'];

            case 'reclamation_blur':
                $bankingService->toggleReclamationBlur(
                    $this->requestInt($request, 'idReclamation') ?? 0,
                    (string) $request->request->get('is_blurred', '0') === '1'
                );

                return ['type' => 'success', 'message' => 'Reclamation blur status updated.'];
        }

        return null;
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'reclamation_save':
                $bankingService->saveReclamation($request->request->all(), $this->requestInt($request, 'idReclamation'), $userId);

                return ['type' => 'success', 'message' => 'Reclamation saved.'];

            case 'reclamation_delete':
                $bankingService->deleteReclamation($this->requestInt($request, 'idReclamation') ?? 0, $userId);

                return ['type' => 'success', 'message' => 'Reclamation deleted.'];
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
