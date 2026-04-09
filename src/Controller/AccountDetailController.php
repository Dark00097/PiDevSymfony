<?php

namespace App\Controller;

use App\Controller\Sections\AccountsController as AccountsSectionController;
use App\Service\AuthService;
use App\Service\BankingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountDetailController extends AbstractController
{
    public function __construct(
        private readonly AccountsSectionController $accountsSectionController,
    ) {
    }

    #[Route('/admin/accounts/{id}/detail', name: 'admin_account_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(
        int $id,
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
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

        $data = $this->accountsSectionController->buildAccountDetail($bankingService, $id);

        if ($data === null) {
            throw $this->createNotFoundException('Compte introuvable.');
        }

        // Security: non-admin users can only view their own accounts
        $isAdmin = strtoupper((string) ($user['role'] ?? '')) === 'ROLE_ADMIN';
        if (!$isAdmin) {
            $account = $data['account'] ?? [];
            $accountUserId = (int) ($account['idUser'] ?? 0);
            if ($accountUserId !== (int) $user['idUser']) {
                throw $this->createAccessDeniedException('Accès refusé.');
            }
        }

        return $this->render('interfaces/admin/account-detail.html.twig', array_merge($data, [
            'current_user' => $user,
        ]));
    }
}
