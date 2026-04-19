<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\BankingService;
use App\Service\ExportService;
use App\Service\GamificationService;
use App\Service\GeminiService;
use App\Service\InsightsService;
use App\Service\NotificationService;
use App\Service\PaymentService;
use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PortalFeaturesController extends AbstractController
{
    #[Route('/portal/features', name: 'portal_features_home', methods: ['GET'])]
    #[Route('/portal/features/{section}', name: 'portal_features', requirements: ['section' => 'insights|games|payments|exports'], methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        NotificationService $notificationService,
        InsightsService $insightsService,
        GamificationService $gamificationService,
        PaymentService $paymentService,
        string $section = 'insights',
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);
            $this->addFlash('error', $blockedReason);

            return $this->redirectToRoute('login');
        }

        if (strtoupper((string) ($user['role'] ?? '')) === 'ROLE_ADMIN') {
            return $this->redirectToRoute('admin_features');
        }

        $section = trim($section) !== '' ? $section : 'insights';
        $viewState = [
            'translation_language' => (string) $request->request->get('translation_language', 'en'),
            'parsed_account' => null,
            'generated_garantie' => null,
            'improved_reclamation' => null,
            'payment_result' => null,
            'stripe_customer' => null,
            'subscription_result' => null,
            'wheel_result' => null,
        ];

        if ($request->isMethod('POST')) {
            try {
                $viewState = $this->handleAction(
                    $request,
                    $user,
                    $authService,
                    $paymentService,
                    $insightsService,
                    $gamificationService,
                    $viewState
                );
                $this->addFlash('success', 'Portal feature action completed.');
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        $userId = (int) $user['idUser'];
        $weatherCity = trim((string) ($request->query->get('city', 'Tunis,TN')));
        $currencyAmount = (float) $request->query->get('amount', 100);

        return $this->render('interfaces/portal/PortalFeaturesView.html.twig', [
            'mode' => 'portal',
            'current_user' => $user,
            'section' => $section,
            'sections' => [
                ['key' => 'insights', 'label' => 'Insights'],
                ['key' => 'games', 'label' => 'Games'],
                ['key' => 'payments', 'label' => 'Payments'],
                ['key' => 'exports', 'label' => 'Exports'],
            ],
            'dashboard_route' => 'portal_dashboard',
            'feature_route' => 'portal_features',
            'notifications_count' => $notificationService->countUnreadFor($userId, (string) $user['role']),
            'notifications' => $notificationService->getRecentNotificationsFor($userId, (string) $user['role'], 8),
            'translation' => $insightsService->getTranslations($viewState['translation_language']),
            'security_analysis' => $insightsService->getAccountSecurityAnalysis($userId),
            'account_advice' => $insightsService->getAccountAdvisor($userId),
            'cashback_advice' => $insightsService->getCashbackAdvisor($userId),
            'prediction' => $insightsService->getSpendingPrediction($userId),
            'surplus' => $insightsService->detectMonthlySurplus($userId),
            'dragon' => $gamificationService->getDragonState($userId),
            'wheel' => $gamificationService->getWheelStatus($userId),
            'payments' => [
                'accounts' => $bankingService->listAccounts($userId),
                'credits' => $bankingService->listCredits($userId),
                'history' => $paymentService->getPaymentHistory($userId),
                'total_paid' => $paymentService->calculateTotalPaid($userId),
                'weather' => $paymentService->getWeatherRisk($weatherCity),
                'currency' => $paymentService->getCurrencyInsight($currencyAmount, (string) $request->getClientIp()),
                'stripe_state' => $paymentService->getStripeState($userId),
                'otp_verified' => ((int) (($request->getSession()->get('nexora.payment_otp_verified', []))['user_id'] ?? 0) === $userId),
                'weather_city' => $weatherCity,
                'currency_amount' => $currencyAmount,
            ],
            'vaults' => $bankingService->listVaults($userId),
            'exports_data' => [
                'transactions' => $bankingService->listTransactions($userId),
                'credits' => $bankingService->listCredits($userId),
                'garanties' => $bankingService->listGaranties($userId),
            ],
            'view_state' => $viewState,
        ]);
    }

    #[Route('/portal/features/analysis-report', name: 'portal_feature_analysis_download', methods: ['GET'])]
    public function analysisReport(Request $request, AuthService $authService, InsightsService $insightsService): Response
    {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);
            $this->addFlash('error', $blockedReason);

            return $this->redirectToRoute('login');
        }

        return new Response($insightsService->exportAccountSecurityAnalysis((int) $user['idUser']), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="nexora-account-analysis.txt"',
        ]);
    }

    #[Route('/portal/features/download/pdf/{kind}', name: 'portal_feature_pdf_download', methods: ['GET'])]
    public function pdfDownload(
        string $kind,
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        ExportService $exportService,
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);
            $this->addFlash('error', $blockedReason);

            return $this->redirectToRoute('login');
        }

        $userId = (int) $user['idUser'];
        $headers = [];
        $rows = [];
        $title = 'Nexora Export';
        $stats = [];
        $subtitle = null;
        $accent = '#1565c0';

        if ($kind === 'credits') {
            $credits = $bankingService->listCredits($userId);
            $title = 'Credits Report';
            $subtitle = 'Vue synthetique de vos credits, mensualites et statuts.';
            $accent = '#0db98f';
            $headers = ['ID', 'Type', 'Amount', 'Monthly', 'Status'];
            $totalAmount = 0.0;
            $totalMonthly = 0.0;
            foreach ($credits as $credit) {
                $amount = (float) ($credit['montantDemande'] ?? 0);
                $monthly = (float) ($credit['mensualite'] ?? 0);
                $totalAmount += $amount;
                $totalMonthly += $monthly;
                $rows[] = [
                    $credit['idCredit'],
                    $credit['typeCredit'],
                    number_format($amount, 2, '.', ' '),
                    number_format($monthly, 2, '.', ' '),
                    $credit['statut'],
                ];
            }
            $stats = [
                ['label' => 'Total dossiers', 'value' => (string) count($credits)],
                ['label' => 'Montant total', 'value' => number_format($totalAmount, 2, '.', ' ').' DT'],
                ['label' => 'Mensualite totale', 'value' => number_format($totalMonthly, 2, '.', ' ').' DT'],
            ];
        } elseif ($kind === 'garanties') {
            $garanties = $bankingService->listGaranties($userId);
            $title = 'Garanties Report';
            $subtitle = 'Vue synthetique de vos garanties, valeurs estimees et retenues.';
            $accent = '#00bcd4';
            $headers = ['ID', 'Type', 'Value', 'Retained', 'Status'];
            $estimatedTotal = 0.0;
            $retainedTotal = 0.0;
            foreach ($garanties as $garantie) {
                $estimated = (float) ($garantie['valeurEstimee'] ?? 0);
                $retained = (float) ($garantie['valeurRetenue'] ?? 0);
                $estimatedTotal += $estimated;
                $retainedTotal += $retained;
                $rows[] = [
                    $garantie['idGarantie'],
                    $garantie['typeGarantie'],
                    number_format($estimated, 2, '.', ' '),
                    number_format($retained, 2, '.', ' '),
                    $garantie['statut'],
                ];
            }
            $stats = [
                ['label' => 'Total garanties', 'value' => (string) count($garanties)],
                ['label' => 'Valeur estimee', 'value' => number_format($estimatedTotal, 2, '.', ' ').' DT'],
                ['label' => 'Valeur retenue', 'value' => number_format($retainedTotal, 2, '.', ' ').' DT'],
            ];
        } else {
            $title = 'Transactions Report';
            $headers = ['ID', 'Category', 'Amount', 'Type', 'Date'];
            foreach ($bankingService->listTransactions($userId) as $transaction) {
                $rows[] = [
                    $transaction['idTransaction'],
                    $transaction['categorie'],
                    number_format((float) ($transaction['montant_value'] ?? 0), 2, '.', ' '),
                    $transaction['typeTransaction'],
                    $transaction['dateTransaction'] ?? '-',
                ];
            }
        }

        return new Response($exportService->buildPdf($title, $headers, $rows, $stats, $subtitle, $accent), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="nexora-%s.pdf"', $kind),
        ]);
    }

    #[Route('/portal/features/transactions/{id}/qr', name: 'portal_feature_transaction_qr', methods: ['GET'])]
    public function transactionQr(
        int $id,
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        ExportService $exportService,
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);
            $this->addFlash('error', $blockedReason);

            return $this->redirectToRoute('login');
        }

        foreach ($bankingService->listTransactions((int) $user['idUser']) as $transaction) {
            if ((int) $transaction['idTransaction'] === $id) {
                $svgContent = $exportService->buildTransactionQrSvg($transaction);

                return $this->render('interfaces/portal/features/transaction_qr.html.twig', [
                    'transaction' => $transaction,
                    'qr_svg'      => $svgContent,
                ]);
            }
        }

        throw $this->createNotFoundException('Transaction introuvable.');
    }

    private function handleAction(
        Request $request,
        array $user,
        AuthService $authService,
        PaymentService $paymentService,
        InsightsService $insightsService,
        GamificationService $gamificationService,
        array $viewState,
    ): array {
        $action = (string) $request->request->get('action', '');
        $userId = (int) ($user['idUser'] ?? 0);

        if ($action === 'secure_account') {
            $authService->updateProfile($userId, ['biometric_enabled' => '1']);

            return $viewState;
        }

        if ($action === 'translate_labels') {
            $viewState['translation_language'] = (string) $request->request->get('translation_language', 'en');

            return $viewState;
        }

        if ($action === 'parse_account_note') {
            $viewState['parsed_account'] = $insightsService->parseAccountSpeechText((string) $request->request->get('speech_text', ''));

            return $viewState;
        }

        if ($action === 'generate_garantie_text') {
            $viewState['generated_garantie'] = $insightsService->generateGuaranteeDescription($request->request->all());

            return $viewState;
        }

        if ($action === 'improve_reclamation') {
            $viewState['improved_reclamation'] = $insightsService->improveComplaint((string) $request->request->get('raw_reclamation', ''));

            return $viewState;
        }

        if ($action === 'ack_surplus') {
            $insightsService->acknowledgeMonthlySurplus($userId, (string) $request->request->get('month', ''));

            return $viewState;
        }

        if ($action === 'dragon_feed') {
            $viewState['dragon'] = $gamificationService->feedDragon(
                $userId,
                (int) $request->request->get('idCompte', 0),
                (int) $request->request->get('idCoffre', 0),
                (float) $request->request->get('amount', 0)
            );

            return $viewState;
        }

        if ($action === 'wheel_spin') {
            $viewState['wheel_result'] = $gamificationService->spinWheel($userId);

            return $viewState;
        }

        if ($action === 'wheel_bonus') {
            $viewState['wheel_result'] = $gamificationService->claimWheelBonus($userId, (int) $request->request->get('idCompte', 0));

            return $viewState;
        }

        if ($action === 'send_payment_otp') {
            $fallbackOtp = $paymentService->sendPaymentOtp($userId, (string) $request->request->get('telephone', ''), $request->getSession());
            if ($fallbackOtp !== null) {
                $this->addFlash('success', 'Local payment OTP: '.$fallbackOtp);
            }

            return $viewState;
        }

        if ($action === 'verify_payment_otp') {
            if (!$paymentService->verifyPaymentOtp(
                $userId,
                (string) $request->request->get('telephone', ''),
                (string) $request->request->get('otp', ''),
                $request->getSession()
            )) {
                throw new \RuntimeException('Payment OTP is invalid or expired.');
            }

            return $viewState;
        }

        if ($action === 'create_customer') {
            $viewState['stripe_customer'] = $paymentService->createCustomer(
                $userId,
                trim(sprintf('%s %s', $user['prenom'] ?? '', $user['nom'] ?? '')),
                (string) ($user['email'] ?? '')
            );

            return $viewState;
        }

        if ($action === 'create_subscription') {
            $viewState['subscription_result'] = $paymentService->createSubscription(
                $userId,
                (int) $request->request->get('idCredit', 0),
                (float) $request->request->get('monthly_amount', 0)
            );

            return $viewState;
        }

        if ($action === 'cancel_subscription') {
            $paymentService->cancelSubscription($userId, (string) $request->request->get('subscription_id', ''));

            return $viewState;
        }

        if ($action === 'pay_credit') {
            $viewState['payment_result'] = $paymentService->payCreditInstallment(
                $userId,
                (int) $request->request->get('idCredit', 0),
                (int) $request->request->get('idCompte', 0),
                (float) $request->request->get('amount', 0),
                $request->getSession(),
                (string) $request->request->get('payment_mode', 'simulation'),
                (string) $request->request->get('payment_method', '')
            );

            return $viewState;
        }

        return $viewState;
    }

    #[Route('/portal/api/reclamation/improve-description', name: 'portal_api_reclamation_improve', methods: ['POST'])]
    public function improveReclamationDescription(Request $request, AuthService $authService, GeminiService $geminiService): JsonResponse
    {
        set_time_limit(15); // max 15s pour cet endpoint

        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return new JsonResponse(['error' => 'Non authentifié.'], 401);
        }

        if (!$geminiService->isConfigured()) {
            return new JsonResponse(['error' => 'Service IA non configuré.'], 503);
        }

        $description = trim((string) $request->request->get('description', ''));
        if ($description === '') {
            return new JsonResponse(['error' => 'Description vide.'], 400);
        }

        $improved = $geminiService->improveReclamationDescription($description, [
            'type'      => (string) $request->request->get('type', ''),
            'categorie' => (string) $request->request->get('categorie', ''),
            'montant'   => (string) $request->request->get('montant', ''),
        ]);

        return new JsonResponse(['improved' => $improved]);
    }

    #[Route('/portal/stripe/checkout/{id}', name: 'portal_stripe_checkout', methods: ['GET'])]
    public function stripeCheckout(
        int $id,
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        StripeService $stripeService,
    ): Response {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        if (!$stripeService->isConfigured()) {
            $this->addFlash('error', 'Le paiement Stripe n\'est pas configuré.');
            return $this->redirectToRoute('portal_dashboard', ['tab' => 'transactions']);
        }

        foreach ($bankingService->listTransactions((int) $user['idUser']) as $tx) {
            if ((int) $tx['idTransaction'] === $id) {
                $successUrl = $this->generateUrl('portal_stripe_success', ['id' => $id], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
                $cancelUrl  = $this->generateUrl('portal_dashboard', ['tab' => 'transactions'], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

                try {
                    $checkoutUrl = $stripeService->createCheckoutSession($tx, $successUrl, $cancelUrl);
                    return $this->redirect($checkoutUrl);
                } catch (\RuntimeException $e) {
                    $this->addFlash('error', $e->getMessage());
                    return $this->redirectToRoute('portal_dashboard', ['tab' => 'transactions']);
                }
            }
        }

        throw $this->createNotFoundException('Transaction introuvable.');
    }

    #[Route('/portal/stripe/success/{id}', name: 'portal_stripe_success', methods: ['GET'])]
    public function stripeSuccess(
        int $id,
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        StripeService $stripeService,
    ): Response {
        $user = $authService->getAuthenticatedUser($request->getSession());
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        foreach ($bankingService->listTransactions((int) $user['idUser']) as $tx) {
            if ((int) $tx['idTransaction'] === $id) {
                $montant     = (float) ($tx['montant_value']    ?? 0);
                $montantPaye = (float) ($tx['montantPaye_value'] ?? 0);
                $restant     = max(0, $montant - $montantPaye);

                // Marque la transaction comme entièrement payée
                $stripeService->markTransactionPaid($id, $montantPaye + $restant, $montant);

                $this->addFlash('success', sprintf('Paiement de %.3f TND effectué avec succès !', $restant));
                return $this->redirectToRoute('portal_dashboard', ['tab' => 'transactions']);
            }
        }

        return $this->redirectToRoute('portal_dashboard', ['tab' => 'transactions']);
    }
}
