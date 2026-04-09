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
use App\Service\InsightsService;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PortalController extends AbstractController
{
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
            if (!is_array($profileAi)) {
                $profileAi = $this->buildProfileAiData((int) $user['idUser'], $insightsService);
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
        $profileImagePath = $action === 'profile_save'
            ? $this->handleProfileImageUpload($request, 'profile_image')
            : null;

        try {
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
            $creditsData = $this->creditsSectionController->buildPortalData($bankingService, $userId);
            $garantiesData = $this->garantiesSectionController->buildPortalData($bankingService, $userId);
            $data = $this->mergeTabData($data, $creditsData);
            $data = $this->mergeTabData($data, $garantiesData);
            $data['items'] = $creditsData['items'] ?? [];
        } elseif ($tab === 'garanties') {
            $garantiesData = $this->garantiesSectionController->buildPortalData($bankingService, $userId);
            $creditsData = $this->creditsSectionController->buildPortalData($bankingService, $userId);
            $data = $this->mergeTabData($data, $creditsData);
            $data = $this->mergeTabData($data, $garantiesData);
            $data['items'] = $garantiesData['items'] ?? [];
            $data['support']['credits'] = $creditsData['items'] ?? [];
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

    private function buildProfileAiData(int $userId, InsightsService $insightsService): array
    {
        return [
            'generated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'security_analysis' => $insightsService->getAccountSecurityAnalysis($userId),
            'prediction' => $insightsService->getSpendingPrediction($userId),
            'account_advice' => $insightsService->getAccountAdvisor($userId),
            'cashback_advice' => $insightsService->getCashbackAdvisor($userId),
            'surplus' => $insightsService->detectMonthlySurplus($userId),
        ];
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
