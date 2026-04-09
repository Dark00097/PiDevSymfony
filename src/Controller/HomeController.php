<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\BankingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(Request $request, AuthService $authService, BankingService $bankingService): Response
    {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user !== null) {
            return strtoupper((string) ($user['role'] ?? '')) === 'ROLE_ADMIN'
                ? $this->redirectToRoute('admin_dashboard')
                : $this->redirectToRoute('portal_dashboard');
        }

        return $this->render('home/index.html.twig', [
            'stats' => $bankingService->getLandingStats(),
        ]);
    }
}
