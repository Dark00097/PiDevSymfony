<?php

namespace App\Controller;

use App\Controller\Sections\AccountsController as AccountsSectionController;
use App\Controller\Sections\AdminAccountController as AdminAccountSectionController;
use App\Controller\Sections\CashbackController as CashbackSectionController;
use App\Controller\Sections\CoffrevirtuelleController as CoffrevirtuelleSectionController;
use App\Controller\Sections\CreditsController as CreditsSectionController;
use App\Controller\Sections\GarantiesController as GarantiesSectionController;
use App\Controller\Sections\NotificationsController as NotificationsSectionController;
use App\Controller\Sections\PartnersController as PartnersSectionController;
use App\Controller\Sections\ProfileController as ProfileSectionController;
use App\Controller\Sections\ReclamationController as ReclamationSectionController;
use App\Controller\Sections\TransactionsController as TransactionsSectionController;
use App\Service\ActivityService;
use App\Service\AuthService;
use App\Service\BankingService;
use App\Service\GamificationService;
use App\Service\GeminiService;
use App\Service\InsightsService;
use App\Service\NotificationService;
use App\Service\PaymentService;
use App\Service\RestructurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PortalController extends AbstractController
{
    private const FORM_FEEDBACK_SESSION_KEY = 'portal.form_feedback';

    public function __construct(
        private readonly AccountsSectionController $accountsSectionController,
        private readonly CoffrevirtuelleSectionController $coffrevirtuelleSectionController,
        private readonly TransactionsSectionController $transactionsSectionController,
        private readonly ReclamationSectionController $reclamationSectionController,
        private readonly CreditsSectionController $creditsSectionController,
        private readonly GarantiesSectionController $garantiesSectionController,
        private readonly CashbackSectionController $cashbackSectionController,
        private readonly PartnersSectionController $partnersSectionController,
        private readonly NotificationsSectionController $notificationsSectionController,
        private readonly ProfileSectionController $profileSectionController,
    ) {
    }

    #[Route('/portal', name: 'portal_dashboard', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        NotificationService $notificationService,
        PaymentService $paymentService,
        ActivityService $activityService,
        InsightsService $insightsService,
        GamificationService $gamificationService,
        GeminiService $geminiService,
        RestructurationService $restructurationService,
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
            return $this->redirectToRoute('admin_dashboard');
        }

        $tab = (string) $request->query->get('tab', 'dashboard');
        if ($tab === 'overview') {
            $tab = 'dashboard';
        }

        if ($request->isMethod('POST')) {
            $tab = (string) $request->request->get('tab', $tab);
            $panel = trim((string) $request->request->get('panel', ''));
            $selectedAccount = trim((string) $request->request->get('selected_account', $request->query->get('selected_account', '')));
            $showVault = trim((string) $request->request->get('show_vault', $request->query->get('show_vault', '')));
            $editVault = trim((string) $request->request->get('edit_vault', $request->query->get('edit_vault', '')));
            $editId = trim((string) $request->request->get('edit_id', $request->query->get('edit_id', '')));
            $searchQuery = trim((string) $request->request->get('q', $request->query->get('q', '')));
            $searchIn = trim((string) $request->request->get('search_in', $request->query->get('search_in', '')));
            $filter = trim((string) $request->request->get('filter', $request->query->get('filter', '')));
            $sort = trim((string) $request->request->get('sort', $request->query->get('sort', '')));
            $dir = trim((string) $request->request->get('dir', $request->query->get('dir', '')));
            $action = (string) $request->request->get('action', '');
            $paymentCredit = trim((string) $request->request->get('payment_credit', $request->query->get('payment_credit', '')));
            $this->handlePortalAction($request, $authService, $bankingService, $notificationService, $paymentService, $insightsService, $gamificationService, $user);

            if ($selectedAccount === '' && $action === 'vault_save') {
                $selectedAccount = trim((string) $request->request->get('idCompte', ''));
            }

            $routeParams = ['tab' => $tab];
            if ($panel !== '') {
                $routeParams['panel'] = $panel;
            }
            if ($selectedAccount !== '') {
                $routeParams['selected_account'] = $selectedAccount;
            }
            if ($showVault !== '') {
                $routeParams['show_vault'] = $showVault;
            }
            if ($editVault !== '') {
                $routeParams['edit_vault'] = $editVault;
            }
            if ($editId !== '') {
                $routeParams['edit_id'] = $editId;
            }
            if ($searchQuery !== '') {
                $routeParams['q'] = $searchQuery;
            }
            if ($searchIn !== '') {
                $routeParams['search_in'] = $searchIn;
            }
            if ($filter !== '') {
                $routeParams['filter'] = $filter;
            }
            if ($sort !== '') {
                $routeParams['sort'] = $sort;
            }
            if ($dir !== '') {
                $routeParams['dir'] = $dir;
            }
            if ($paymentCredit !== '') {
                $routeParams['payment_credit'] = $paymentCredit;
            }

            return $this->redirectToRoute('portal_dashboard', $routeParams);
        }

        $data = $this->buildPortalTabData($tab, $request, $bankingService, $notificationService, $paymentService, $activityService, $gamificationService, $restructurationService, $user);
        if ($tab === 'profile') {
            $profileAi = $request->getSession()->get('nexora.profile_ai_data');
            $hasLegacyJavaWording = false;
            if (is_array($profileAi)) {
                $securityAnalysis = is_array($profileAi['security_analysis'] ?? null) ? $profileAi['security_analysis'] : [];
                $securitySummary = (string) ($securityAnalysis['summary'] ?? '');
                $hasLegacyJavaWording = stripos($securitySummary, 'java') !== false;
            }
            if (!is_array($profileAi)) {
                $profileAi = $this->buildProfileAiData((int) $user['idUser'], $insightsService, $geminiService);
                $request->getSession()->set('nexora.profile_ai_data', $profileAi);
            } elseif ($hasLegacyJavaWording) {
                $profileAi = $this->buildProfileAiData((int) $user['idUser'], $insightsService, $geminiService);
                $request->getSession()->set('nexora.profile_ai_data', $profileAi);
            } elseif (!is_array($profileAi['coach'] ?? null)) {
                $profileAi['coach'] = $this->buildProfileCoachInsight($profileAi, $geminiService);
                $request->getSession()->set('nexora.profile_ai_data', $profileAi);
            }
            $data['support']['profile_ai'] = $profileAi;
        }

        $tabTemplate = $this->resolvePortalTabTemplate($tab);
        $tabStylesheets = $this->resolvePortalTabStylesheets($tab);

        return $this->render('interfaces/portal/UserDashboard.html.twig', array_merge($data, [
            'mode' => 'portal',
            'route_name' => 'portal_dashboard',
            'tab_template' => $tabTemplate,
            'tab_stylesheets' => $tabStylesheets,
            'current_user' => $user,
            'feature_links' => [
                ['label' => 'Insights', 'href' => $this->generateUrl('portal_features', ['section' => 'insights'])],
                ['label' => 'Games', 'href' => $this->generateUrl('portal_features', ['section' => 'games'])],
                ['label' => 'Payments', 'href' => $this->generateUrl('portal_features', ['section' => 'payments'])],
                ['label' => 'Exports', 'href' => $this->generateUrl('portal_features', ['section' => 'exports'])],
            ],
            'tabs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard'],
                ['key' => 'accounts', 'label' => 'Comptes'],
                ['key' => 'transactions', 'label' => 'Transactions'],
                ['key' => 'credits', 'label' => 'Credits'],
                ['key' => 'cashback', 'label' => 'Recompenses'],
                ['key' => 'garanties', 'label' => 'Garanties'],
                ['key' => 'complaints', 'label' => 'Reclamations'],
                ['key' => 'vaults', 'label' => 'Coffres'],
                ['key' => 'profile', 'label' => 'Profil'],
                ['key' => 'notifications', 'label' => 'Notifications'],
            ],
        ]));
    }

    #[Route('/portal/credits/analyze-capacity', name: 'portal_credit_capacity_analyze', methods: ['POST'])]
    public function analyzeCreditCapacity(
        Request $request,
        AuthService $authService,
        GeminiService $geminiService,
    ): JsonResponse {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->json([
                'ok' => false,
                'message' => 'Utilisateur non authentifie.',
            ], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            return $this->json([
                'ok' => false,
                'message' => $blockedReason,
            ], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $input = [
            'typeCredit' => trim((string) ($payload['typeCredit'] ?? '')),
            'montantDemande' => $this->toFloat($payload['montantDemande'] ?? null),
            'autofinancement' => $this->toFloat($payload['autofinancement'] ?? null),
            'duree' => max(0, $this->toInt($payload['duree'] ?? null)),
            'tauxInteret' => $this->toFloat($payload['tauxInteret'] ?? null),
            'mensualite' => $this->toFloat($payload['mensualite'] ?? null),
            'montantAccorde' => $this->toFloat($payload['montantAccorde'] ?? null),
            'salaire' => $this->toFloat($payload['salaire'] ?? null),
            'typeContrat' => trim((string) ($payload['typeContrat'] ?? '')),
            'ancienneteAnnees' => max(0, $this->toInt($payload['ancienneteAnnees'] ?? null)),
        ];

        $errors = [];
        if ($input['montantDemande'] <= 0) {
            $errors['montantDemande'] = 'Montant demande invalide.';
        }
        if ($input['autofinancement'] < 0) {
            $errors['autofinancement'] = 'Autofinancement invalide.';
        }
        if ($input['montantDemande'] > 0 && $input['autofinancement'] > $input['montantDemande']) {
            $errors['autofinancement'] = "L'autofinancement ne doit pas depasser le montant demande.";
        }
        if ($input['duree'] <= 0) {
            $errors['duree'] = 'Duree invalide.';
        }
        if ($input['tauxInteret'] < 0 || $input['tauxInteret'] > 100) {
            $errors['tauxInteret'] = "Taux d'interet invalide.";
        }
        if ($input['salaire'] <= 0) {
            $errors['salaire'] = 'Salaire invalide.';
        }
        if ($input['typeContrat'] === '') {
            $errors['typeContrat'] = 'Type de contrat requis.';
        }
        if ($input['ancienneteAnnees'] > 60) {
            $errors['ancienneteAnnees'] = "L'anciennete doit etre <= 60.";
        }

        if ($errors !== []) {
            return $this->json([
                'ok' => false,
                'message' => 'Veuillez corriger les champs invalides avant analyse.',
                'errors' => $errors,
            ], 422);
        }

        $analysis = $geminiService->analyzeCreditEligibility($input);

        return $this->json([
            'ok' => true,
            'provider' => (string) ($analysis['provider'] ?? 'Fallback'),
            'scoring' => [
                'approval_probability' => (float) ($analysis['approval_probability'] ?? 0),
                'failure_probability' => (float) ($analysis['failure_probability'] ?? 0),
                'score' => (float) ($analysis['score'] ?? 0),
                'risk_level' => (string) ($analysis['risk_level'] ?? 'Moyen'),
                'suggested_status' => (string) ($analysis['suggested_status'] ?? 'En attente'),
                'explanation' => (string) ($analysis['explanation'] ?? ''),
            ],
        ]);
    }

    #[Route('/portal/garanties/generate-description', name: 'portal_garantie_generate_description', methods: ['POST'])]
    public function generateGarantieDescription(
        Request $request,
        AuthService $authService,
        GeminiService $geminiService,
    ): JsonResponse {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->json([
                'ok' => false,
                'message' => 'Utilisateur non authentifie.',
            ], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            return $this->json([
                'ok' => false,
                'message' => $blockedReason,
            ], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $input = [
            'typeGarantie' => trim((string) ($payload['typeGarantie'] ?? '')),
            'valeurEstimee' => $this->toFloat($payload['valeurEstimee'] ?? null),
            'valeurRetenue' => $this->toFloat($payload['valeurRetenue'] ?? null),
            'dateEvaluation' => trim((string) ($payload['dateEvaluation'] ?? '')),
            'nomGarant' => trim((string) ($payload['nomGarant'] ?? '')),
            'adresseBien' => trim((string) ($payload['adresseBien'] ?? '')),
        ];

        $errors = [];
        if ($input['typeGarantie'] === '') {
            $errors['typeGarantie'] = 'Type de garantie requis.';
        }
        if ($input['valeurEstimee'] <= 0) {
            $errors['valeurEstimee'] = 'Valeur estimee invalide.';
        }
        if ($input['nomGarant'] === '') {
            $errors['nomGarant'] = 'Nom du garant requis.';
        }
        if ($input['adresseBien'] === '') {
            $errors['adresseBien'] = 'Adresse du bien requise.';
        }

        if ($errors !== []) {
            return $this->json([
                'ok' => false,
                'message' => 'Veuillez renseigner les champs requis avant la generation IA.',
                'errors' => $errors,
            ], 422);
        }

        $result = $geminiService->generateGuaranteeDescription($input);

        return $this->json([
            'ok' => true,
            'provider' => (string) ($result['provider'] ?? 'Fallback'),
            'description' => trim((string) ($result['text'] ?? '')),
        ]);
    }

    #[Route('/portal/garanties/generate-document', name: 'portal_garantie_generate_document', methods: ['POST'])]
    public function generateGarantieDocument(
        Request $request,
        AuthService $authService,
        GeminiService $geminiService,
    ): JsonResponse {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->json([
                'ok' => false,
                'message' => 'Utilisateur non authentifie.',
            ], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            return $this->json([
                'ok' => false,
                'message' => $blockedReason,
            ], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $input = [
            'typeGarantie' => trim((string) ($payload['typeGarantie'] ?? '')),
            'valeurEstimee' => $this->toFloat($payload['valeurEstimee'] ?? null),
            'valeurRetenue' => $this->toFloat($payload['valeurRetenue'] ?? null),
            'dateEvaluation' => trim((string) ($payload['dateEvaluation'] ?? '')),
            'nomGarant' => trim((string) ($payload['nomGarant'] ?? '')),
            'adresseBien' => trim((string) ($payload['adresseBien'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
        ];

        $errors = [];
        if ($input['typeGarantie'] === '') {
            $errors['typeGarantie'] = 'Type de garantie requis.';
        }
        if ($input['valeurEstimee'] <= 0) {
            $errors['valeurEstimee'] = 'Valeur estimee invalide.';
        }
        if ($input['nomGarant'] === '') {
            $errors['nomGarant'] = 'Nom du garant requis.';
        }
        if ($input['adresseBien'] === '') {
            $errors['adresseBien'] = 'Adresse du bien requise.';
        }

        if ($errors !== []) {
            return $this->json([
                'ok' => false,
                'message' => 'Veuillez renseigner les champs requis avant la generation du justificatif IA.',
                'errors' => $errors,
            ], 422);
        }

        try {
            $result = $geminiService->generateGuaranteeDescription($input);
            $summary = trim((string) ($result['text'] ?? ''));
            $documentPath = $this->createPortalGuaranteeAiDocument($input, $summary, (int) ($user['idUser'] ?? 0));
        } catch (\Throwable $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'La generation du justificatif IA a echoue.',
            ], 503);
        }

        return $this->json([
            'ok' => true,
            'provider' => (string) ($result['provider'] ?? 'Fallback'),
            'documentPath' => $documentPath,
            'message' => 'Justificatif IA genere avec succes.',
        ]);
    }

    #[Route('/portal/credits/chat-assistant', name: 'portal_credit_chat_assistant', methods: ['POST'])]
    public function creditChatAssistant(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        GeminiService $geminiService,
    ): JsonResponse {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->json([
                'ok' => false,
                'message' => 'Utilisateur non authentifie.',
            ], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            return $this->json([
                'ok' => false,
                'message' => $blockedReason,
            ], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $message = trim((string) ($payload['message'] ?? ''));
        $intent = trim((string) ($payload['intent'] ?? ''));
        if ($message === '' && $intent === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Message ou intention requis pour le chatbot.',
            ], 422);
        }

        $userId = (int) $user['idUser'];
        $credits = $bankingService->listCredits($userId);
        $garanties = $bankingService->listGaranties($userId);
        $selectedCreditId = max(0, $this->toInt($payload['selectedCreditId'] ?? null));
        $selectedGarantieId = max(0, $this->toInt($payload['selectedGarantieId'] ?? null));
        $selectedCredit = $this->findPortalCreditById($credits, $selectedCreditId);
        $selectedGarantie = $this->findPortalGarantieById($garanties, $selectedGarantieId);

        if ($selectedCredit === null && $selectedGarantie !== null) {
            $selectedCredit = $this->findPortalCreditById($credits, (int) ($selectedGarantie['idCredit'] ?? 0));
        }

        if ($selectedGarantie === null && $selectedCredit !== null) {
            $selectedGarantie = $this->findPortalGarantieById($garanties, (int) ($selectedCredit['idGarantie'] ?? 0));
        }

        $assistant = $geminiService->generateCreditAssistant([
            'intent' => $intent,
            'message' => $message,
            'draft' => $this->sanitizeCreditAssistantDraft($payload['draft'] ?? null),
            'portfolio' => $this->buildCreditAssistantPortfolio($credits, $garanties),
            'selected_credit' => $selectedCredit !== null ? $this->buildCreditAssistantCreditSnapshot($selectedCredit, $selectedGarantie) : [],
            'selected_garantie' => $selectedGarantie !== null ? $this->buildCreditAssistantGarantieSnapshot($selectedGarantie) : [],
            'credits' => array_map(
                fn (array $credit): array => $this->buildCreditAssistantCreditSnapshot(
                    $credit,
                    $this->findPortalGarantieById($garanties, (int) ($credit['idGarantie'] ?? 0))
                ),
                array_slice($credits, 0, 5)
            ),
            'garanties' => array_map(
                fn (array $garantie): array => $this->buildCreditAssistantGarantieSnapshot($garantie),
                array_slice($garanties, 0, 5)
            ),
        ]);

        return $this->json([
            'ok' => true,
            'provider' => (string) ($assistant['provider'] ?? 'Fallback'),
            'assistant' => [
                'intent' => (string) ($assistant['intent'] ?? 'recommend'),
                'title' => (string) ($assistant['title'] ?? 'Assistant credit'),
                'answer' => (string) ($assistant['answer'] ?? ''),
                'decision' => (string) ($assistant['decision'] ?? 'A etudier'),
                'score' => (float) ($assistant['score'] ?? 0),
                'risk_level' => (string) ($assistant['risk_level'] ?? 'Moyen'),
                'metrics' => array_values(array_filter(
                    is_array($assistant['metrics'] ?? null) ? $assistant['metrics'] : [],
                    static fn ($metric): bool => is_array($metric)
                        && trim((string) ($metric['label'] ?? '')) !== ''
                        && trim((string) ($metric['value'] ?? '')) !== ''
                )),
                'recommendations' => array_values(array_filter(
                    is_array($assistant['recommendations'] ?? null) ? $assistant['recommendations'] : [],
                    static fn ($item): bool => trim((string) $item) !== ''
                )),
                'offers' => array_values(array_filter(
                    is_array($assistant['offers'] ?? null) ? $assistant['offers'] : [],
                    static fn ($offer): bool => is_array($offer) && trim((string) ($offer['bank'] ?? '')) !== ''
                )),
                'best_offer' => is_array($assistant['best_offer'] ?? null) ? $assistant['best_offer'] : [],
                'constraints' => array_values(array_filter(
                    is_array($assistant['constraints'] ?? null) ? $assistant['constraints'] : [],
                    static fn ($constraint): bool => is_array($constraint) && trim((string) ($constraint['label'] ?? '')) !== ''
                )),
            ],
        ]);
    }

    #[Route('/portal/profile/ai-refresh', name: 'portal_profile_ai_refresh', methods: ['POST'])]
    public function refreshProfileAi(
        Request $request,
        AuthService $authService,
        InsightsService $insightsService,
        GeminiService $geminiService,
    ): JsonResponse {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->json([
                'ok' => false,
                'message' => 'Utilisateur non authentifie.',
            ], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            return $this->json([
                'ok' => false,
                'message' => $blockedReason,
            ], 403);
        }

        $profileAi = $this->buildProfileAiData((int) $user['idUser'], $insightsService, $geminiService);
        $session->set('nexora.profile_ai_data', $profileAi);

        return $this->json([
            'ok' => true,
            'message' => 'Analyse IA actualisee.',
            'profile_ai' => $profileAi,
        ]);
    }

    private function handlePortalAction(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        NotificationService $notificationService,
        PaymentService $paymentService,
        InsightsService $insightsService,
        GamificationService $gamificationService,
        array $user
    ): void {
        $action = (string) $request->request->get('action', '');
        $userId = (int) $user['idUser'];

        try {
            $profileImagePath = $action === 'profile_save'
                ? $this->handleProfileImageUpload($request, 'profile_image')
                : null;

            if ($action === 'garantie_save') {
                $existingDocument = trim((string) $request->request->get('existingDocumentJustificatif', ''));
                if ($existingDocument !== '') {
                    $request->request->set('documentJustificatif', $existingDocument);
                }
                $uploadedDocument = $this->handlePortalImageUpload(
                    $request,
                    'documentJustificatifFile',
                    'uploads/garanties',
                    'garantie_doc',
                    'Le document justificatif'
                );

                if ($uploadedDocument !== null) {
                    $request->request->set('documentJustificatif', $uploadedDocument);
                }
            }

            $accountsFlash = $this->accountsSectionController->handlePortalAction($action, $request, $bankingService, $userId);

            foreach ([
                $accountsFlash,
                str_starts_with($action, 'vault_') ? null : $this->coffrevirtuelleSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->transactionsSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->reclamationSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->creditsSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->garantiesSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->cashbackSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->profileSectionController->handlePortalAction($action, $request, $authService, $insightsService, $user, $profileImagePath),
                $this->notificationsSectionController->handleAction($action, $notificationService, $user),
            ] as $flash) {
                if ($flash !== null) {
                    $this->addFlash((string) $flash['type'], (string) $flash['message']);
                    return;
                }
            }

            switch ($action) {
                case 'send_payment_otp':
                    $fallbackOtp = $paymentService->sendPaymentOtp(
                        $userId,
                        (string) $request->request->get('telephone', ''),
                        $request->getSession(),
                        (string) $request->request->get('otp_channel', 'sms')
                    );
                    if ($fallbackOtp !== null) {
                        $this->addFlash('success', 'Code OTP local paiement: '.$fallbackOtp);
                    } else {
                        $this->addFlash('success', 'Code OTP envoye pour le paiement.');
                    }
                    return;
                case 'verify_payment_otp':
                    if (!$paymentService->verifyPaymentOtp(
                        $userId,
                        (string) $request->request->get('telephone', ''),
                        (string) $request->request->get('otp', ''),
                        $request->getSession()
                    )) {
                        throw new \RuntimeException('Le code OTP de paiement est invalide ou expire.');
                    }
                    $this->addFlash('success', 'Verification OTP confirmee pour le paiement.');
                    return;
                case 'pay_credit':
                    $paymentResult = $paymentService->payCreditInstallment(
                        $userId,
                        (int) $request->request->get('idCredit', 0),
                        (int) $request->request->get('idCompte', 0),
                        (float) $request->request->get('amount', 0),
                        $request->getSession(),
                        (string) $request->request->get('payment_mode', 'stripe'),
                        (string) $request->request->get('payment_method', '')
                    );
                    $this->addFlash(
                        'success',
                        sprintf(
                            'Mensualite payee via %s (%s).',
                            strtoupper((string) ($paymentResult['provider'] ?? 'stripe')),
                            (string) ($paymentResult['reference'] ?? 'N/A')
                        )
                    );
                    return;
                case 'wheel_spin':
                    $wheelResult = $gamificationService->spinWheel($userId);
                    $spinMessage = (string) ($wheelResult['spin_result']['message'] ?? '');
                    $this->addFlash('success', $spinMessage !== '' ? $spinMessage : 'Wheel spin completed.');
                    break;
                case 'wheel_bonus':
                    $wheelResult = $gamificationService->claimWheelBonus($userId, $this->requestInt($request, 'idCompte') ?? 0);
                    $this->addFlash('success', (bool) ($wheelResult['bonus_ready'] ?? false) ? 'Wheel bonus is still available.' : 'Wheel bonus credited (+50 DT).');
                    break;
            }
        } catch (\Throwable $exception) {
            if ($this->capturePortalFormFeedback($request, $action, $exception)) {
                return;
            }

            $this->addFlash('error', $exception->getMessage());
        }
    }

    private function buildPortalTabData(
        string $tab,
        Request $request,
        BankingService $bankingService,
        NotificationService $notificationService,
        PaymentService $paymentService,
        ActivityService $activityService,
        GamificationService $gamificationService,
        RestructurationService $restructurationService,
        array $user
    ): array {
        $userId = (int) $user['idUser'];
        $summary = $bankingService->getUserDashboard($userId);
        $data = [
            'tab' => $tab,
            'summary' => $summary,
            'items' => [],
            'support' => [],
            'notifications' => $notificationService->getRecentNotificationsFor($userId, (string) $user['role'], 20),
            'notifications_count' => $notificationService->countUnreadFor($userId, (string) $user['role']),
        ];

        if ($tab === 'accounts') {
            $accountsData = $this->accountsSectionController->buildPortalData($bankingService, $activityService, $gamificationService, $userId, $request);
            $vaultsData = $this->coffrevirtuelleSectionController->buildPortalData($bankingService, $userId);
            $data = $this->mergeTabData($data, $accountsData);
            $data = $this->mergeTabData($data, $vaultsData);
            $data['items'] = $accountsData['items'] ?? [];
        } elseif ($tab === 'transactions') {
            $queryParams = $request->query->all();
            $data = $this->mergeTabData($data, $this->transactionsSectionController->buildPortalData($bankingService, $userId, $queryParams));
        } elseif ($tab === 'credits') {
            $creditsData = $this->creditsSectionController->buildPortalData($bankingService, $userId, $request);
            $garantiesData = $this->garantiesSectionController->buildPortalData($bankingService, $userId, $request);
            $paymentVerification = $paymentService->getPaymentVerificationState($userId, $request->getSession());
            $restructurationPortfolio = $restructurationService->buildPortfolioScenarios($creditsData['items'] ?? []);
            $data = $this->mergeTabData($data, $creditsData);
            $data = $this->mergeTabData($data, $garantiesData);
            $data['items'] = $creditsData['items'] ?? [];
            $data['support'] = array_replace($data['support'], $this->consumePortalFormFeedback($request));
            $data['support']['payment_accounts'] = $bankingService->listAccounts($userId);
            $data['support']['payment_otp_sent'] = (bool) ($paymentVerification['otp_sent'] ?? false);
            $data['support']['payment_otp_verified'] = (bool) ($paymentVerification['otp_verified'] ?? false);
            $data['support']['payment_phone'] = (string) ($paymentVerification['phone'] ?? '');
            $data['support']['payment_channel'] = (string) ($paymentVerification['channel'] ?? 'sms');
            $data['support']['payment_open_credit_id'] = max(0, $request->query->getInt('payment_credit', 0));
            $data['support']['payment_history'] = $paymentService->getPaymentHistory($userId);
            $data['support']['payment_config'] = $paymentService->getPortalPaymentConfig();
            $data['support']['restructuration_scenarios'] = $restructurationPortfolio['by_credit'] ?? [];
            $data['support']['restructuration_first_eligible_credit_id'] = (int) ($restructurationPortfolio['first_eligible_credit_id'] ?? 0);
            $data['support']['restructuration_first_critical_credit_id'] = (int) ($restructurationPortfolio['first_critical_credit_id'] ?? 0);
        } elseif ($tab === 'garanties') {
            $garantiesData = $this->garantiesSectionController->buildPortalData($bankingService, $userId, $request);
            $creditsData = $this->creditsSectionController->buildPortalData($bankingService, $userId, $request);
            $data = $this->mergeTabData($data, $creditsData);
            $data = $this->mergeTabData($data, $garantiesData);
            $data['items'] = $garantiesData['items'] ?? [];
            $data['support']['credits'] = $creditsData['items'] ?? [];
            $data['support'] = array_replace($data['support'], $this->consumePortalFormFeedback($request));
        } elseif ($tab === 'cashback') {
            $cashbackData = $this->cashbackSectionController->buildPortalData($bankingService, $userId);
            $partnersData = $this->partnersSectionController->buildPortalData($bankingService);
            $data = $this->mergeTabData($data, $cashbackData);
            $data = $this->mergeTabData($data, $partnersData);
            $data['items'] = $cashbackData['items'] ?? [];
        } elseif ($tab === 'complaints') {
            $data = $this->mergeTabData($data, $this->reclamationSectionController->buildPortalData($bankingService, $userId));
        } elseif ($tab === 'vaults') {
            $vaultsData = $this->coffrevirtuelleSectionController->buildPortalData($bankingService, $userId);
            $accountsData = $this->accountsSectionController->buildPortalData($bankingService, $activityService, $gamificationService, $userId, $request);
            $data = $this->mergeTabData($data, $accountsData);
            $data = $this->mergeTabData($data, $vaultsData);
            $data['items'] = $vaultsData['items'] ?? [];
        } elseif ($tab === 'profile') {
            $data = $this->mergeTabData($data, $this->profileSectionController->buildPortalData($activityService, $userId));
        } elseif ($tab === 'notifications') {
            $data = $this->mergeTabData($data, $this->notificationsSectionController->buildPortalData($data['notifications']));
        }

        return $data;
    }

    private function mergeTabData(array $base, array $extra): array
    {
        if (isset($extra['items'])) {
            $base['items'] = $extra['items'];
        }

        if (isset($extra['support']) && is_array($extra['support'])) {
            $base['support'] = array_replace($base['support'], $extra['support']);
        }

        return $base;
    }

    private function requestInt(Request $request, string $key): ?int
    {
        $value = $request->request->get($key);
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function toFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        $normalized = trim((string) $value);

        return preg_match('/^-?\d+$/', $normalized) === 1 ? (int) $normalized : 0;
    }

    #[Route('/portal/credits/payments/checkout', name: 'portal_credit_checkout_start', methods: ['POST'])]
    public function startCreditCheckout(
        Request $request,
        AuthService $authService,
        PaymentService $paymentService,
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

        $creditId = (int) $request->request->get('idCredit', 0);
        $successUrl = $this->generateUrl('portal_credit_checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL).'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $this->generateUrl('portal_credit_checkout_cancel', ['payment_credit' => $creditId], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $checkout = $paymentService->createCreditCheckoutSession(
                (int) $user['idUser'],
                $creditId,
                (int) $request->request->get('idCompte', 0),
                (float) $request->request->get('amount', 0),
                $session,
                $successUrl,
                $cancelUrl,
                (string) ($user['email'] ?? ''),
                trim(sprintf('%s %s', $user['prenom'] ?? '', $user['nom'] ?? ''))
            );

            return $this->redirect((string) $checkout['url']);
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('portal_dashboard', [
                'tab' => 'credits',
                'payment_credit' => $creditId,
            ]);
        }
    }

    #[Route('/portal/credits/payments/checkout/success', name: 'portal_credit_checkout_success', methods: ['GET'])]
    public function creditCheckoutSuccess(
        Request $request,
        AuthService $authService,
        PaymentService $paymentService,
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        try {
            $result = $paymentService->finalizeCreditCheckoutSession(
                (int) $user['idUser'],
                (string) $request->query->get('session_id', '')
            );

            $this->addFlash(
                'success',
                sprintf(
                    'Paiement Stripe confirme pour le credit #%d (%s).',
                    (int) ($result['credit_id'] ?? 0),
                    (string) ($result['reference'] ?? 'N/A')
                )
            );

            return $this->redirectToRoute('portal_dashboard', [
                'tab' => 'credits',
                'payment_credit' => (int) ($result['credit_id'] ?? 0),
            ]);
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('portal_dashboard', ['tab' => 'credits']);
        }
    }

    #[Route('/portal/credits/payments/checkout/cancel', name: 'portal_credit_checkout_cancel', methods: ['GET'])]
    public function creditCheckoutCancel(Request $request): Response
    {
        $this->addFlash('info', 'Le paiement Stripe a ete annule.');

        return $this->redirectToRoute('portal_dashboard', [
            'tab' => 'credits',
            'payment_credit' => max(0, $request->query->getInt('payment_credit', 0)),
        ]);
    }

    private function buildProfileAiData(int $userId, InsightsService $insightsService, GeminiService $geminiService): array
    {
        $profileAi = [
            'generated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'security_analysis' => $insightsService->getAccountSecurityAnalysis($userId),
            'prediction' => $insightsService->getSpendingPrediction($userId),
            'account_advice' => $insightsService->getAccountAdvisor($userId),
            'cashback_advice' => $insightsService->getCashbackAdvisor($userId),
            'surplus' => $insightsService->detectMonthlySurplus($userId),
        ];

        $profileAi['coach'] = $this->buildProfileCoachInsight($profileAi, $geminiService);

        return $profileAi;
    }

    /**
     * @param array<string, mixed> $profileAi
     * @return array{
     *   provider: string,
     *   headline: string,
     *   summary: string,
     *   risk: string,
     *   opportunity: string,
     *   actions: array<int, string>
     * }
     */
    private function buildProfileCoachInsight(array $profileAi, GeminiService $geminiService): array
    {
        $security = is_array($profileAi['security_analysis'] ?? null) ? $profileAi['security_analysis'] : [];
        $prediction = is_array($profileAi['prediction'] ?? null) ? $profileAi['prediction'] : [];
        $accountAdvice = is_array($profileAi['account_advice'] ?? null) ? $profileAi['account_advice'] : [];
        $cashbackAdvice = is_array($profileAi['cashback_advice'] ?? null) ? $profileAi['cashback_advice'] : [];
        $surplus = is_array($profileAi['surplus'] ?? null) ? $profileAi['surplus'] : [];

        $topCategories = [];
        foreach (array_slice((array) ($prediction['predictions'] ?? []), 0, 3) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $category = trim((string) ($row['category'] ?? ''));
            if ($category === '') {
                continue;
            }
            $topCategories[] = [
                'category' => $category,
                'predicted_amount' => (float) ($row['predicted_amount'] ?? 0),
            ];
        }

        $partnerIdeas = [];
        foreach (array_slice((array) ($cashbackAdvice['recommended_partners'] ?? []), 0, 3) as $partner) {
            if (!is_array($partner)) {
                continue;
            }
            $name = trim((string) ($partner['name'] ?? ''));
            if ($name !== '') {
                $partnerIdeas[] = $name;
            }
        }

        return $geminiService->generateProfileCoach([
            'security_score' => (float) ($security['score'] ?? 0),
            'security_summary' => (string) ($security['summary'] ?? ''),
            'security_recommendations' => array_values(array_slice((array) ($security['recommendations'] ?? []), 0, 4)),
            'savings_score' => (float) ($prediction['savings_score'] ?? 0),
            'predicted_spending' => (float) ($prediction['total_predicted_spending'] ?? 0),
            'prediction_summary' => (string) ($prediction['summary'] ?? ''),
            'top_spending_categories' => $topCategories,
            'account_summary' => (string) ($accountAdvice['summary'] ?? ''),
            'account_actions' => array_values(array_slice((array) ($accountAdvice['action_items'] ?? []), 0, 4)),
            'cashback_summary' => (string) ($cashbackAdvice['summary'] ?? ''),
            'cashback_partners' => $partnerIdeas,
            'surplus' => [
                'show' => (bool) ($surplus['show'] ?? false),
                'current_income' => (float) ($surplus['current_income'] ?? 0),
                'average_income' => (float) ($surplus['average_income'] ?? 0),
                'surplus' => (float) ($surplus['surplus'] ?? 0),
                'message' => (string) ($surplus['message'] ?? ''),
            ],
        ]);
    }

    private function capturePortalFormFeedback(Request $request, string $action, \Throwable $exception): bool
    {
        $formKey = match ($action) {
            'credit_save' => 'credit',
            'garantie_save' => 'garantie',
            default => null,
        };

        if ($formKey === null) {
            return false;
        }

        $errors = $this->mapPortalFormErrors($formKey, $exception->getMessage());
        if ($errors === []) {
            return false;
        }

        $session = $request->getSession();
        $feedback = $session->get(self::FORM_FEEDBACK_SESSION_KEY, []);
        if (!is_array($feedback)) {
            $feedback = [];
        }

        $feedback[$formKey.'_form_feedback'] = [
            'errors' => $errors,
            'input' => $this->sanitizePortalFormInput($formKey, $request->request->all()),
        ];

        $session->set(self::FORM_FEEDBACK_SESSION_KEY, $feedback);

        return true;
    }

    private function consumePortalFormFeedback(Request $request): array
    {
        $session = $request->getSession();
        $feedback = $session->get(self::FORM_FEEDBACK_SESSION_KEY, []);
        $session->remove(self::FORM_FEEDBACK_SESSION_KEY);

        return is_array($feedback) ? $feedback : [];
    }

    private function sanitizePortalFormInput(string $formKey, array $input): array
    {
        $allowedFields = match ($formKey) {
            'credit' => ['idCredit', 'idCompte', 'idGarantie', 'typeCredit', 'dateDemande', 'montantDemande', 'autofinancement', 'duree', 'tauxInteret', 'mensualite', 'montantAccorde', 'salaire', 'typeContrat', 'ancienneteAnnees', 'statut'],
            'garantie' => ['idGarantie', 'typeGarantie', 'valeurEstimee', 'valeurRetenue', 'dateEvaluation', 'nomGarant', 'adresseBien', 'documentJustificatif', 'description', 'statut'],
            default => [],
        };

        $clean = [];
        foreach ($allowedFields as $field) {
            $clean[$field] = (string) ($input[$field] ?? '');
        }

        return $clean;
    }

    private function mapPortalFormErrors(string $formKey, string $message): array
    {
        $fieldPatterns = match ($formKey) {
            'credit' => [
                'idCompte' => ['Le compte associe est obligatoire', 'Compte requis pour le credit', 'Compte introuvable', 'Le compte selectionne n\'appartient pas a l\'utilisateur choisi'],
                'idGarantie' => ['Veuillez selectionner une garantie enregistree', 'Garantie selectionnee introuvable', 'Cette garantie est deja associee a un autre credit', 'Une garantie existante est obligatoire pour la demande de credit', 'La garantie selectionnee n\'appartient pas a cet utilisateur'],
                'typeCredit' => ['Le type de credit est obligatoire'],
                'dateDemande' => ['La date de demande est obligatoire', 'Date de demande invalide', 'La date doit etre au format'],
                'montantDemande' => ['Le montant demande est obligatoire', 'Le montant demande doit etre un nombre positif', 'Le montant doit etre compris', 'Montant demande invalide'],
                'autofinancement' => ['autofinancement est obligatoire', 'Autofinancement obligatoire', 'autofinancement doit etre positif ou nul', 'Autofinancement invalide'],
                'duree' => ['La duree est obligatoire', 'La duree doit etre comprise', 'Duree invalide'],
                'tauxInteret' => ['Le taux d\'interet est obligatoire', 'Le taux d\'interet doit etre compris'],
                'mensualite' => ['La mensualite doit etre un nombre positif', 'Mensualite invalide'],
                'montantAccorde' => ['Le montant accorde doit etre positif ou nul', 'Montant accorde invalide'],
                'salaire' => ['Le salaire est obligatoire', 'Le salaire doit etre un nombre positif', 'Le salaire doit etre compris'],
                'typeContrat' => ['Le type de contrat est obligatoire', 'Le type de contrat ne peut pas depasser'],
                'ancienneteAnnees' => ['anciennete est obligatoire', 'anciennete doit etre comprise'],
            ],
            'garantie' => [
                'typeGarantie' => ['Le type de garantie est obligatoire', 'Le type doit contenir au moins', 'Le type ne peut pas depasser'],
                'valeurEstimee' => ['La valeur estimee est obligatoire', 'La valeur estimee doit etre un nombre positif', 'La valeur estimee doit etre comprise'],
                'valeurRetenue' => ['La valeur retenue doit etre positive', 'Valeur retenue invalide', 'La valeur retenue ne peut pas depasser la valeur estimee'],
                'dateEvaluation' => ['La date d\'evaluation est obligatoire', 'Date d\'evaluation invalide', 'La date doit etre au format'],
                'nomGarant' => ['Le nom du garant est obligatoire', 'Le nom doit contenir au moins', 'Le nom du garant ne doit contenir que des lettres'],
                'adresseBien' => ['L\'adresse du bien est obligatoire', 'L\'adresse doit contenir au moins', 'Cette adresse de garantie est deja utilisee'],
                'documentJustificatif' => ['Le document justificatif est obligatoire', 'Le nom du document ne peut pas depasser', 'Le document justificatif doit etre une image', 'Le document justificatif n a pas pu etre televerse'],
                'description' => ['La description est obligatoire', 'La description doit contenir au moins', 'La description ne peut pas depasser'],
            ],
            default => [],
        };

        $errors = [];
        foreach ($fieldPatterns as $field => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($message, $pattern) !== false) {
                    $errors[$field] = rtrim($pattern, '.').'.';
                    break;
                }
            }
        }

        return $errors;
    }

    private function handleProfileImageUpload(Request $request, string $fieldName): ?string
    {
        $file = $request->files->get($fieldName);
        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!$file->isValid()) {
            throw new \RuntimeException('Profile image upload failed.');
        }

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        $mimeType = (string) $file->getMimeType();
        if (!array_key_exists($mimeType, $allowedMimeTypes)) {
            throw new \InvalidArgumentException('Only JPG, PNG, WEBP or GIF profile images are allowed.');
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $targetDirectory = $projectDir.'/public/uploads/profile';
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException('Unable to create profile upload directory.');
        }

        $fileName = sprintf('profile_%s.%s', bin2hex(random_bytes(10)), $allowedMimeTypes[$mimeType]);
        $file->move($targetDirectory, $fileName);

        return 'uploads/profile/'.$fileName;
    }

    private function handlePortalImageUpload(
        Request $request,
        string $fieldName,
        string $targetFolder,
        string $filePrefix,
        string $label
    ): ?string {
        $file = $request->files->get($fieldName);
        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!$file->isValid()) {
            throw new \RuntimeException($label.' n a pas pu etre televerse.');
        }

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        $mimeType = (string) $file->getMimeType();
        if (!array_key_exists($mimeType, $allowedMimeTypes)) {
            throw new \InvalidArgumentException($label.' doit etre une image JPG, PNG, WEBP ou GIF.');
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $absoluteTargetDirectory = $projectDir.'/public/'.$targetFolder;
        if (!is_dir($absoluteTargetDirectory) && !mkdir($absoluteTargetDirectory, 0777, true) && !is_dir($absoluteTargetDirectory)) {
            throw new \RuntimeException('Impossible de creer le dossier de televersement.');
        }

        $fileName = sprintf('%s_%s.%s', $filePrefix, bin2hex(random_bytes(10)), $allowedMimeTypes[$mimeType]);
        $file->move($absoluteTargetDirectory, $fileName);

        return trim($targetFolder, '/').'/'.$fileName;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createPortalGuaranteeAiDocument(array $input, string $summary, int $userId): string
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $targetFolder = 'uploads/garanties';
        $absoluteTargetDirectory = $projectDir.'/public/'.$targetFolder;
        if (!is_dir($absoluteTargetDirectory) && !mkdir($absoluteTargetDirectory, 0777, true) && !is_dir($absoluteTargetDirectory)) {
            throw new \RuntimeException('Impossible de creer le dossier de generation des justificatifs IA.');
        }

        $generatedAt = new \DateTimeImmutable('now');
        $reference = sprintf('NEX-GAR-%s-%04d', $generatedAt->format('YmdHis'), max(1, $userId));
        $svg = $this->buildPortalGuaranteeAiDocumentSvg($input, $summary, $reference, $generatedAt);
        $fileName = sprintf('garantie_doc_ai_%s.svg', bin2hex(random_bytes(10)));

        if (@file_put_contents($absoluteTargetDirectory.'/'.$fileName, $svg) === false) {
            throw new \RuntimeException('Le justificatif IA n a pas pu etre enregistre.');
        }

        return trim($targetFolder, '/').'/'.$fileName;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function buildPortalGuaranteeAiDocumentSvg(array $input, string $summary, string $reference, \DateTimeImmutable $generatedAt): string
    {
        $type = trim((string) ($input['typeGarantie'] ?? 'Garantie'));
        $guarantor = trim((string) ($input['nomGarant'] ?? 'Non precise'));
        $address = trim((string) ($input['adresseBien'] ?? 'Adresse non precisee'));
        $evaluationDate = trim((string) ($input['dateEvaluation'] ?? 'Non precisee'));
        $description = trim((string) ($input['description'] ?? ''));
        $estimated = number_format(max(0.0, (float) ($input['valeurEstimee'] ?? 0)), 2, '.', ' ');
        $retained = number_format(max(0.0, (float) ($input['valeurRetenue'] ?? 0)), 2, '.', ' ');
        $aiSummary = trim($summary) !== ''
            ? trim($summary)
            : 'Le dossier presente une garantie exploitable pour l analyse bancaire sous reserve de verification documentaire.';
        $manualDescription = $description !== '' ? $description : 'Aucune description complementaire fournie par le client.';

        $summaryLines = $this->wrapSvgText($aiSummary, 78);
        $addressLines = $this->wrapSvgText($address, 56);
        $descriptionLines = $this->wrapSvgText($manualDescription, 64);

        $summarySvg = $this->buildSvgTspans($summaryLines, 420, 300, 28, '#15314b', 18, 600);
        $addressSvg = $this->buildSvgTspans($addressLines, 80, 448, 24, '#4b647d', 16, 500);
        $descriptionSvg = $this->buildSvgTspans($descriptionLines, 80, 610, 24, '#38536d', 15, 500);
        $referenceEsc = $this->escapeSvgText($reference);
        $typeEsc = $this->escapeSvgText($type);
        $guarantorEsc = $this->escapeSvgText($guarantor);
        $evaluationDateEsc = $this->escapeSvgText($evaluationDate);
        $estimatedEsc = $this->escapeSvgText($estimated);
        $retainedEsc = $this->escapeSvgText($retained);
        $generatedAtEsc = $this->escapeSvgText($generatedAt->format('d/m/Y H:i'));

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="760" viewBox="0 0 1200 760" role="img" aria-labelledby="title desc">
  <title id="title">Justificatif IA Nexora</title>
  <desc id="desc">Document justificatif de garantie genere automatiquement par IA pour le dossier bancaire.</desc>
  <defs>
    <linearGradient id="nxHeader" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#0a2540"/>
      <stop offset="55%" stop-color="#1565c0"/>
      <stop offset="100%" stop-color="#10b981"/>
    </linearGradient>
    <linearGradient id="nxBadge" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#e0f2fe"/>
      <stop offset="100%" stop-color="#dcfce7"/>
    </linearGradient>
    <filter id="shadow" x="-10%" y="-10%" width="120%" height="120%">
      <feDropShadow dx="0" dy="10" stdDeviation="12" flood-color="#0a2540" flood-opacity="0.12"/>
    </filter>
  </defs>

  <rect width="1200" height="760" rx="28" fill="#eff6ff"/>
  <rect x="28" y="28" width="1144" height="704" rx="30" fill="#ffffff" filter="url(#shadow)"/>
  <rect x="28" y="28" width="1144" height="138" rx="30" fill="url(#nxHeader)"/>
  <rect x="66" y="64" width="170" height="54" rx="18" fill="rgba(255,255,255,0.14)"/>
  <text x="92" y="97" fill="#ffffff" font-size="34" font-weight="800" font-family="Segoe UI, Arial, sans-serif">NEXORA</text>
  <text x="68" y="206" fill="#10324f" font-size="34" font-weight="800" font-family="Segoe UI, Arial, sans-serif">Document justificatif IA</text>
  <text x="68" y="240" fill="#5b7b96" font-size="18" font-weight="500" font-family="Segoe UI, Arial, sans-serif">Preparation automatique du dossier de garantie bancaire</text>

  <rect x="822" y="188" width="300" height="84" rx="20" fill="url(#nxBadge)" stroke="#bfe3f0"/>
  <text x="850" y="220" fill="#0f766e" font-size="16" font-weight="800" font-family="Segoe UI, Arial, sans-serif">Reference</text>
  <text x="850" y="248" fill="#12344d" font-size="21" font-weight="800" font-family="Segoe UI, Arial, sans-serif">{$referenceEsc}</text>

  <rect x="68" y="280" width="1064" height="106" rx="24" fill="#f8fbff" stroke="#d9e8f5"/>
  <text x="80" y="318" fill="#62829c" font-size="15" font-weight="800" font-family="Segoe UI, Arial, sans-serif">Synthese IA</text>
  <text font-family="Segoe UI, Arial, sans-serif">{$summarySvg}</text>

  <rect x="68" y="412" width="500" height="152" rx="24" fill="#fbfdff" stroke="#dfeaf5"/>
  <rect x="588" y="412" width="544" height="152" rx="24" fill="#fbfdff" stroke="#dfeaf5"/>
  <rect x="68" y="584" width="1064" height="108" rx="24" fill="#f9fafb" stroke="#e5edf5"/>

  <text x="80" y="448" fill="#6a879f" font-size="15" font-weight="800" font-family="Segoe UI, Arial, sans-serif">Informations principales</text>
  <text x="80" y="486" fill="#12344d" font-size="18" font-weight="700" font-family="Segoe UI, Arial, sans-serif">Type: {$typeEsc}</text>
  <text x="80" y="518" fill="#12344d" font-size="18" font-weight="700" font-family="Segoe UI, Arial, sans-serif">Garant: {$guarantorEsc}</text>
  <text font-family="Segoe UI, Arial, sans-serif">{$addressSvg}</text>

  <text x="600" y="448" fill="#6a879f" font-size="15" font-weight="800" font-family="Segoe UI, Arial, sans-serif">Evaluation bancaire</text>
  <text x="600" y="486" fill="#12344d" font-size="18" font-weight="700" font-family="Segoe UI, Arial, sans-serif">Date d evaluation: {$evaluationDateEsc}</text>
  <text x="600" y="518" fill="#12344d" font-size="18" font-weight="700" font-family="Segoe UI, Arial, sans-serif">Valeur estimee: {$estimatedEsc} DT</text>
  <text x="600" y="550" fill="#12344d" font-size="18" font-weight="700" font-family="Segoe UI, Arial, sans-serif">Valeur retenue: {$retainedEsc} DT</text>

  <text x="80" y="620" fill="#6a879f" font-size="15" font-weight="800" font-family="Segoe UI, Arial, sans-serif">Description complementaire</text>
  <text font-family="Segoe UI, Arial, sans-serif">{$descriptionSvg}</text>

  <line x1="68" y1="714" x2="1132" y2="714" stroke="#dfe7ef"/>
  <text x="80" y="740" fill="#6d879f" font-size="13" font-weight="600" font-family="Segoe UI, Arial, sans-serif">Genere automatiquement le {$generatedAtEsc} - Document preparatoire a valider par Nexora.</text>
</svg>
SVG;
    }

    /**
     * @return array<int, string>
     */
    private function wrapSvgText(string $text, int $lineLength): array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($normalized === '') {
            return ['-'];
        }

        $words = preg_split('/\s+/', $normalized) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate, 'UTF-8') <= $lineLength) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines !== [] ? array_slice($lines, 0, 4) : ['-'];
    }

    /**
     * @param array<int, string> $lines
     */
    private function buildSvgTspans(array $lines, int $x, int $y, int $lineHeight, string $fill, int $fontSize, int $fontWeight): string
    {
        $buffer = [];
        foreach (array_values($lines) as $index => $line) {
            $buffer[] = sprintf(
                '<tspan x="%d" y="%d" fill="%s" font-size="%d" font-weight="%d">%s</tspan>',
                $x,
                $y + ($index * $lineHeight),
                $fill,
                $fontSize,
                $fontWeight,
                $this->escapeSvgText($line)
            );
        }

        return implode('', $buffer);
    }

    private function escapeSvgText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * @param array<int, array<string, mixed>> $credits
     * @param array<int, array<string, mixed>> $garanties
     * @return array<string, mixed>
     */
    private function buildCreditAssistantPortfolio(array $credits, array $garanties): array
    {
        $totalAmount = 0.0;
        $totalMonthly = 0.0;
        $riskSum = 0.0;
        $pending = 0;
        $accepted = 0;
        $refused = 0;

        foreach ($credits as $credit) {
            $totalAmount += (float) ($credit['montantDemande'] ?? 0);
            $totalMonthly += (float) ($credit['mensualite'] ?? 0);
            $riskSum += (float) ($credit['risk_score'] ?? 0);

            $status = strtolower(trim((string) ($credit['statut'] ?? '')));
            if (str_contains($status, 'attente')) {
                ++$pending;
            } elseif (str_contains($status, 'accepte') || str_contains($status, 'cours')) {
                ++$accepted;
            } elseif (str_contains($status, 'refuse') || str_contains($status, 'rejet')) {
                ++$refused;
            }
        }

        return [
            'credits_total' => count($credits),
            'garanties_total' => count($garanties),
            'pending_credits' => $pending,
            'accepted_credits' => $accepted,
            'refused_credits' => $refused,
            'open_credits' => $pending + $accepted,
            'max_active_credits' => 3,
            'total_amount' => round($totalAmount, 2),
            'total_monthly' => round($totalMonthly, 2),
            'average_risk' => count($credits) > 0 ? round($riskSum / count($credits), 1) : 0.0,
        ];
    }

    /**
     * @param array<string, mixed>|null $draft
     * @return array<string, mixed>
     */
    private function sanitizeCreditAssistantDraft(mixed $draft): array
    {
        if (!is_array($draft)) {
            return [];
        }

        return [
            'typeCredit' => trim((string) ($draft['typeCredit'] ?? '')),
            'montantDemande' => $this->toFloat($draft['montantDemande'] ?? null),
            'autofinancement' => $this->toFloat($draft['autofinancement'] ?? null),
            'duree' => max(0, $this->toInt($draft['duree'] ?? null)),
            'tauxInteret' => $this->toFloat($draft['tauxInteret'] ?? null),
            'mensualite' => $this->toFloat($draft['mensualite'] ?? null),
            'montantAccorde' => $this->toFloat($draft['montantAccorde'] ?? null),
            'salaire' => $this->toFloat($draft['salaire'] ?? null),
            'typeContrat' => trim((string) ($draft['typeContrat'] ?? '')),
            'ancienneteAnnees' => max(0, $this->toInt($draft['ancienneteAnnees'] ?? null)),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $credits
     * @return array<string, mixed>|null
     */
    private function findPortalCreditById(array $credits, int $creditId): ?array
    {
        if ($creditId <= 0) {
            return null;
        }

        foreach ($credits as $credit) {
            if ((int) ($credit['idCredit'] ?? 0) === $creditId) {
                return $credit;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $garanties
     * @return array<string, mixed>|null
     */
    private function findPortalGarantieById(array $garanties, int $garantieId): ?array
    {
        if ($garantieId <= 0) {
            return null;
        }

        foreach ($garanties as $garantie) {
            if ((int) ($garantie['idGarantie'] ?? 0) === $garantieId) {
                return $garantie;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $credit
     * @param array<string, mixed>|null $garantie
     * @return array<string, mixed>
     */
    private function buildCreditAssistantCreditSnapshot(array $credit, ?array $garantie = null): array
    {
        return [
            'idCredit' => (int) ($credit['idCredit'] ?? 0),
            'typeCredit' => trim((string) ($credit['typeCredit'] ?? '')),
            'montantDemande' => (float) ($credit['montantDemande'] ?? 0),
            'autofinancement' => (float) ($credit['autofinancement'] ?? 0),
            'duree' => (int) ($credit['duree'] ?? 0),
            'tauxInteret' => (float) ($credit['tauxInteret'] ?? 0),
            'mensualite' => (float) ($credit['mensualite'] ?? 0),
            'montantAccorde' => (float) ($credit['montantAccorde'] ?? 0),
            'dateDemande' => trim((string) ($credit['dateDemande'] ?? '')),
            'salaire' => (float) ($credit['salaire'] ?? 0),
            'typeContrat' => trim((string) ($credit['typeContrat'] ?? '')),
            'ancienneteAnnees' => (int) ($credit['ancienneteAnnees'] ?? 0),
            'statut' => trim((string) ($credit['statut'] ?? '')),
            'risk_score' => (float) ($credit['risk_score'] ?? 0),
            'garantie' => $garantie !== null ? $this->buildCreditAssistantGarantieSnapshot($garantie) : [],
        ];
    }

    /**
     * @param array<string, mixed> $garantie
     * @return array<string, mixed>
     */
    private function buildCreditAssistantGarantieSnapshot(array $garantie): array
    {
        return [
            'idGarantie' => (int) ($garantie['idGarantie'] ?? 0),
            'idCredit' => (int) ($garantie['idCredit'] ?? 0),
            'typeGarantie' => trim((string) ($garantie['typeGarantie'] ?? '')),
            'valeurEstimee' => (float) ($garantie['valeurEstimee'] ?? 0),
            'valeurRetenue' => (float) ($garantie['valeurRetenue'] ?? 0),
            'dateEvaluation' => trim((string) ($garantie['dateEvaluation'] ?? '')),
            'nomGarant' => trim((string) ($garantie['nomGarant'] ?? '')),
            'adresseBien' => trim((string) ($garantie['adresseBien'] ?? '')),
            'statut' => trim((string) ($garantie['statut'] ?? '')),
            'description' => trim((string) ($garantie['description'] ?? '')),
        ];
    }

    private function resolvePortalTabTemplate(string $tab): string
    {
        return match ($tab) {
            'accounts' => 'interfaces/portal/tabs/accounts.html.twig',
            'transactions' => 'interfaces/portal/tabs/transactions.html.twig',
            'credits' => 'interfaces/portal/tabs/credits.html.twig',
            'cashback' => 'interfaces/portal/tabs/cashback.html.twig',
            'garanties' => 'interfaces/portal/tabs/garanties.html.twig',
            'complaints' => 'interfaces/portal/tabs/complaints.html.twig',
            'vaults' => 'interfaces/portal/tabs/vaults.html.twig',
            'profile' => 'interfaces/portal/tabs/profile.html.twig',
            'notifications' => 'interfaces/portal/tabs/notifications.html.twig',
            default => 'interfaces/portal/tabs/dashboard.html.twig',
        };
    }

    private function resolvePortalTabStylesheets(string $tab): array
    {
        return match ($tab) {
            'dashboard' => ['styles/interfaces/sections/portal-dashboard.css'],
            'accounts' => ['styles/interfaces/sections/portal-accounts.css'],
            'transactions' => ['styles/interfaces/sections/portal-transactions.css'],
            'credits' => ['styles/interfaces/sections/portal-credits.css'],
            'profile' => ['styles/interfaces/sections/portal-profile.css'],
            default => [],
        };
    }
}
