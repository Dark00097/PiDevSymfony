<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\BankingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestController extends AbstractController
{
    #[Route('/test-form', name: 'test_form', methods: ['GET'])]
    public function testForm(
        Request $request,
        AuthService $authService,
        BankingService $bankingService
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        
        if ($user === null) {
            return $this->redirectToRoute('login');
        }
        
        $userId = (int) $user['idUser'];
        $accounts = $bankingService->listAccounts($userId);
        
        return $this->render('test_form.html.twig', [
            'support' => [
                'accounts' => $accounts
            ]
        ]);
    }

    #[Route('/debug-form', name: 'debug_form', methods: ['GET', 'POST'])]
    public function debugForm(
        Request $request,
        AuthService $authService,
        BankingService $bankingService
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        
        if ($user === null) {
            return $this->redirectToRoute('login');
        }
        
        if ($request->isMethod('POST')) {
            error_log("=== DEBUG FORM POST ===");
            error_log("Données reçues: " . json_encode($request->request->all()));
            
            $action = $request->request->get('action');
            if ($action === 'transaction_save') {
                error_log("Action transaction_save détectée");
                
                try {
                    $userId = (int) $user['idUser'];
                    $transactionId = $bankingService->saveTransaction($request->request->all(), null, $userId);
                    error_log("Transaction créée avec ID: " . $transactionId);
                    
                    $this->addFlash('success', 'Transaction DEBUG créée avec succès ! ID: ' . $transactionId);
                } catch (\Throwable $e) {
                    error_log("Erreur DEBUG: " . $e->getMessage());
                    $this->addFlash('error', 'Erreur DEBUG: ' . $e->getMessage());
                }
            }
        }
        
        return $this->render('debug_form.html.twig');
    }
}