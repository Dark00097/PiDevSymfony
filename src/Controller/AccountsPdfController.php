<?php

namespace App\Controller;

use App\Controller\Sections\AccountsController as AccountsSectionController;
use App\Service\AuthService;
use App\Service\BankingService;
use App\Service\ExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountsPdfController extends AbstractController
{
    public function __construct(
        private readonly AccountsSectionController $accountsSectionController,
    ) {
    }

    #[Route('/admin/accounts/export/pdf', name: 'admin_account_export_pdf', methods: ['GET'])]
    public function exportAccountPdf(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        ExportService $exportService,
    ): Response {
        $session = $request->getSession();
        $user    = $authService->getAuthenticatedUser($session);

        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);
            return $this->redirectToRoute('login');
        }

        if (strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_ADMIN') {
            return $this->redirectToRoute('login');
        }

        $idCompte = $this->positiveQueryInt($request, 'idCompte');

        [$pdfContent, $filename] = $this->accountsSectionController->buildAccountPdf(
            $bankingService,
            $exportService,
            $idCompte
        );

        if ($pdfContent === '') {
            return new Response('Compte introuvable.', 404);
        }

        return new Response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    private function positiveQueryInt(Request $request, string $key): ?int
    {
        $value = $request->query->get($key);
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || !preg_match('/^\d+$/', $normalized)) {
            return null;
        }

        $intValue = (int) $normalized;

        return $intValue > 0 ? $intValue : null;
    }
}
