<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\BankingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DebugTransactionController extends AbstractController
{
    #[Route('/debug-transaction', name: 'debug_transaction', methods: ['GET', 'POST'])]
    public function debugTransaction(
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
        
        $result = null;
        $error = null;
        
        if ($request->isMethod('POST')) {
            try {
                error_log("=== DEBUG TRANSACTION DIRECT ===");
                error_log("POST Data: " . json_encode($request->request->all()));
                
                // Données de test fixes
                $testData = [
                    'compte' => $request->request->get('compte'),
                    'dateTransaction' => $request->request->get('dateTransaction', date('Y-m-d')),
                    'typeTransaction' => 'DEPOT',
                    'montant' => (float) $request->request->get('montant', 100.00),
                    'currency' => 'TND',
                    'description' => 'Test transaction debug'
                ];
                
                error_log("Test Data: " . json_encode($testData));
                
                // Appel direct au BankingService
                $transactionId = $bankingService->saveTransaction($testData, null, $userId);
                
                error_log("Transaction créée avec ID: " . $transactionId);
                
                $result = "✅ SUCCESS! Transaction créée avec ID: " . $transactionId;
                
            } catch (\Throwable $e) {
                error_log("ERREUR DEBUG: " . $e->getMessage());
                error_log("TRACE: " . $e->getTraceAsString());
                $error = "❌ ERREUR: " . $e->getMessage();
            }
        }
        
        return $this->render('debug_transaction.html.twig', [
            'accounts' => $accounts,
            'result' => $result,
            'error' => $error,
            'user' => $user
        ]);
    }

    #[Route('/ultra-simple-test', name: 'ultra_simple_test', methods: ['GET', 'POST'])]
    public function ultraSimpleTest(Request $request): Response {
        return $this->render('ultra_simple_form.html.twig');
    }

    #[Route('/simple-transaction-test', name: 'simple_transaction_test', methods: ['GET', 'POST'])]
    public function simpleTransactionTest(
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
        
        $result = null;
        $error = null;
        
        if ($request->isMethod('POST')) {
            try {
                error_log("=== SIMPLE TRANSACTION TEST ===");
                error_log("POST Data: " . json_encode($request->request->all()));
                
                // Appel direct au BankingService avec les données du formulaire
                $transactionId = $bankingService->saveTransaction($request->request->all(), null, $userId);
                
                error_log("Transaction créée avec ID: " . $transactionId);
                
                $result = "✅ SUCCESS! Transaction créée avec ID: " . $transactionId . " - Vérifiez dans la base de données!";
                
            } catch (\Throwable $e) {
                error_log("ERREUR SIMPLE TRANSACTION: " . $e->getMessage());
                error_log("TRACE: " . $e->getTraceAsString());
                $error = "❌ ERREUR: " . $e->getMessage();
            }
        }
        
        return $this->render('simple_transaction_form.html.twig', [
            'accounts' => $accounts,
            'result' => $result,
            'error' => $error
        ]);
    }
}