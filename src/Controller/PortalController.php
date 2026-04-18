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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        ActivityService $activityService,
        InsightsService $insightsService,
        GamificationService $gamificationService,
        GeminiService $geminiService,
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
            $this->handlePortalAction($request, $authService, $bankingService, $notificationService, $insightsService, $gamificationService, $user);

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

            return $this->redirectToRoute('portal_dashboard', $routeParams);
        }

        $data = $this->buildPortalTabData($tab, $request, $bankingService, $notificationService, $activityService, $gamificationService, $user);
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
        ActivityService $activityService,
        GamificationService $gamificationService,
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
            $data = $this->mergeTabData($data, $creditsData);
            $data = $this->mergeTabData($data, $garantiesData);
            $data['items'] = $creditsData['items'] ?? [];
            $data['support'] = array_replace($data['support'], $this->consumePortalFormFeedback($request));
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
