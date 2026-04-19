<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\CurrencyExchangeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CurrencyConverterController extends AbstractController
{
    #[Route('/portal/currency-converter', name: 'portal_currency_converter', methods: ['GET'])]
    public function index(
        Request $request,
        AuthService $authService,
        CurrencyExchangeService $currencyService
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        // Obtenir tous les taux de change
        $rates = $currencyService->getAllRates('TND');
        
        return $this->render('currency/converter.html.twig', [
            'current_user' => $user,
            'supported_currencies' => CurrencyExchangeService::SUPPORTED_CURRENCIES,
            'currency_symbols' => CurrencyExchangeService::CURRENCY_SYMBOLS,
            'currency_names' => CurrencyExchangeService::CURRENCY_NAMES,
            'rates' => $rates,
        ]);
    }

    #[Route('/portal/api/currency/convert', name: 'portal_api_currency_convert', methods: ['POST'])]
    public function convert(
        Request $request,
        AuthService $authService,
        CurrencyExchangeService $currencyService
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        
        if ($user === null) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $amount = (float) $request->request->get('amount', 0);
        $from = strtoupper(trim((string) $request->request->get('from', 'TND')));
        $to = strtoupper(trim((string) $request->request->get('to', 'EUR')));

        if ($amount <= 0) {
            return $this->json(['error' => 'Montant invalide'], 400);
        }

        if (!$currencyService->isCurrencySupported($from) || !$currencyService->isCurrencySupported($to)) {
            return $this->json(['error' => 'Devise non supportée'], 400);
        }

        try {
            $details = $currencyService->getConversionDetails($amount, $from, $to);
            
            return $this->json([
                'success' => true,
                'conversion' => $details,
            ]);
        } catch (\Throwable $e) {
            error_log('Currency conversion error: ' . $e->getMessage());
            return $this->json([
                'error' => 'Erreur lors de la conversion',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/portal/api/currency/rates', name: 'portal_api_currency_rates', methods: ['GET'])]
    public function getRates(
        Request $request,
        AuthService $authService,
        CurrencyExchangeService $currencyService
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        
        if ($user === null) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $baseCurrency = strtoupper(trim((string) $request->query->get('base', 'TND')));

        if (!$currencyService->isCurrencySupported($baseCurrency)) {
            return $this->json(['error' => 'Devise non supportée'], 400);
        }

        try {
            $rates = $currencyService->getAllRates($baseCurrency);
            
            return $this->json([
                'success' => true,
                'base' => $baseCurrency,
                'rates' => $rates,
                'timestamp' => time(),
            ]);
        } catch (\Throwable $e) {
            error_log('Currency rates error: ' . $e->getMessage());
            return $this->json([
                'error' => 'Erreur lors de la récupération des taux',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
