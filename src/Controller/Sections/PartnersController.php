<?php

namespace App\Controller\Sections;

use App\Service\BankingService;
use Symfony\Component\HttpFoundation\Request;

final class PartnersController
{
    public function buildAdminData(BankingService $bankingService): array
    {
        $partners = $bankingService->listPartenaires();

        return [
            'items' => $partners,
            'support' => [
                'partners' => $partners,
                'partners_items' => $partners,
            ],
        ];
    }

    public function buildPortalData(BankingService $bankingService): array
    {
        return [
            'items' => [],
            'support' => [
                'partners' => $bankingService->listPartenaires(),
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'partner_save':
                $bankingService->savePartenaire($request->request->all(), $this->requestInt($request, 'idPartenaire'));

                return ['type' => 'success', 'message' => 'Partner saved.'];

            case 'partner_delete':
                $bankingService->deletePartenaire($this->requestInt($request, 'idPartenaire') ?? 0);

                return ['type' => 'success', 'message' => 'Partner deleted.'];
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
