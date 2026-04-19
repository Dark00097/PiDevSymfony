<?php

namespace App\Controller;

use App\Controller\Sections\AccountsController as AccountsSectionController;
use App\Controller\Sections\AdminAccountController as AdminAccountSectionController;
use App\Controller\Sections\CashbackController as CashbackSectionController;
use App\Controller\Sections\CoffrevirtuelleController as CoffrevirtuelleSectionController;
use App\Controller\Sections\CreditsController as CreditsSectionController;
use App\Controller\Sections\DatabaseEntitiesController as DatabaseEntitiesSectionController;
use App\Controller\Sections\GarantiesController as GarantiesSectionController;
use App\Controller\Sections\NotificationsController as NotificationsSectionController;
use App\Controller\Sections\PartnersController as PartnersSectionController;
use App\Controller\Sections\ReclamationController as ReclamationSectionController;
use App\Controller\Sections\TransactionsController as TransactionsSectionController;
use App\Controller\Sections\UsersController as UsersSectionController;
use App\Service\AuthService;
use App\Service\BankingService;
use App\Service\ExportService;
use App\Service\GeminiService;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    public function __construct(
        private readonly UsersSectionController $usersSectionController,
        private readonly AccountsSectionController $accountsSectionController,
        private readonly CoffrevirtuelleSectionController $coffrevirtuelleSectionController,
        private readonly TransactionsSectionController $transactionsSectionController,
        private readonly ReclamationSectionController $reclamationSectionController,
        private readonly CreditsSectionController $creditsSectionController,
        private readonly DatabaseEntitiesSectionController $databaseEntitiesSectionController,
        private readonly GarantiesSectionController $garantiesSectionController,
        private readonly CashbackSectionController $cashbackSectionController,
        private readonly PartnersSectionController $partnersSectionController,
        private readonly NotificationsSectionController $notificationsSectionController,
        private readonly AdminAccountSectionController $adminAccountSectionController,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        GeminiService $geminiService,
        NotificationService $notificationService,
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

        if (strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_ADMIN') {
            return $this->redirectToRoute('login');
        }

        $tab = (string) $request->query->get('tab', 'dashboard');
        $panel = trim((string) $request->query->get('panel', ''));
        $editUserId = $this->positiveQueryInt($request, 'edit');
        [$tab, $panel] = $this->normalizeAdminTabAndPanel($tab, $panel);

        if ($request->isMethod('POST')) {
            $tab = (string) $request->request->get('tab', $tab);
            $panel = trim((string) $request->request->get('panel', $panel));
            [$tab, $panel] = $this->normalizeAdminTabAndPanel($tab, $panel);
            $this->handleAdminAction($request, $authService, $bankingService, $geminiService, $notificationService, $user);

            $routeParams = ['tab' => $tab];
            if ($panel !== '') {
                $routeParams['panel'] = $panel;
            }
            foreach (['q', 'filter', 'sort', 'dir', 'edit', 'edit_id', 'view_id'] as $queryKey) {
                $value = $request->request->get($queryKey);
                if ($value !== null && $value !== '') {
                    $routeParams[$queryKey] = $value;
                }
            }

            return $this->redirectToRoute('admin_dashboard', $routeParams);
        }

        $data = $this->buildAdminTabData($tab, $panel, $request, $bankingService, $notificationService, $user, $editUserId);
        if ($tab === 'users') {
            $data['support']['ai_assistant'] = $request->getSession()->get('nexora.users_ai_assistant');
            $data['support']['gemini_enabled'] = $geminiService->isConfigured();
        }

        $tabTemplate = $this->resolveAdminTabTemplate($tab);
        $tabStylesheets = $this->resolveAdminTabStylesheets($tab);

        return $this->render('interfaces/admin/MainView.html.twig', array_merge($data, [
            'mode' => 'admin',
            'route_name' => 'admin_dashboard',
            'tab_template' => $tabTemplate,
            'tab_stylesheets' => $tabStylesheets,
            'current_user' => $user,
            'feature_links' => [
                ['label' => 'Security Center', 'href' => $this->generateUrl('admin_features', ['section' => 'security'])],
                ['label' => 'Statistics', 'href' => $this->generateUrl('admin_features', ['section' => 'statistics'])],
            ],
            'tabs' => [
                ['key' => 'dashboard', 'label' => 'Tableau de Bord'],
                ['key' => 'users', 'label' => 'Gestion Utilisateurs'],
                ['key' => 'admin_account', 'label' => 'Compte admin'],
                ['key' => 'accounts', 'label' => 'Comptes Bancaires'],
                ['key' => 'transactions', 'label' => 'Transactions'],
                ['key' => 'credits', 'label' => 'Gestion Crédit'],
                ['key' => 'cashback', 'label' => 'Gestion Cashback'],
                ['key' => 'notifications', 'label' => 'Notifications'],
            ],
            'panel' => $panel,
        ]));
    }

    #[Route('/admin/credits/export/pdf/{kind}', name: 'admin_credits_export_pdf', requirements: ['kind' => 'credits|garanties'], methods: ['GET'])]
    public function exportCreditsAndGarantiesPdf(
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

        if (strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_ADMIN') {
            return $this->redirectToRoute('login');
        }

        $selectedUserId = $this->positiveQueryInt($request, 'userId');
        $users = $bankingService->listUsers();
        $userNamesById = [];
        foreach ($users as $userRow) {
            $id = (int) ($userRow['idUser'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $userNamesById[$id] = trim(sprintf('%s %s', (string) ($userRow['prenom'] ?? ''), (string) ($userRow['nom'] ?? '')));
        }

        $titleSuffix = 'Tous les utilisateurs';
        if ($selectedUserId !== null) {
            $titleSuffix = sprintf(
                'Utilisateur #%d%s',
                $selectedUserId,
                array_key_exists($selectedUserId, $userNamesById) && $userNamesById[$selectedUserId] !== ''
                    ? ' - '.$userNamesById[$selectedUserId]
                    : ''
            );
        }

        if ($kind === 'garanties') {
            $headers = ['ID Garantie', 'ID Credit', 'Utilisateur', 'Type', 'Valeur estimee', 'Valeur retenue', 'Statut', 'Date'];
            $rows = [];
            $estimatedTotal = 0.0;
            $retainedTotal = 0.0;
            foreach ($bankingService->listGaranties() as $garantie) {
                $rowUserId = $this->nullablePositiveInt($garantie['resolved_user_id'] ?? ($garantie['idUser'] ?? null));
                if ($selectedUserId !== null && $rowUserId !== $selectedUserId) {
                    continue;
                }

                $estimated = (float) ($garantie['valeurEstimee'] ?? 0);
                $retained = (float) ($garantie['valeurRetenue'] ?? 0);
                $estimatedTotal += $estimated;
                $retainedTotal += $retained;

                $rows[] = [
                    $garantie['idGarantie'] ?? '',
                    $garantie['idCredit'] ?? '',
                    $rowUserId !== null
                        ? sprintf(
                            '#%d%s',
                            $rowUserId,
                            (($userNamesById[$rowUserId] ?? '') !== '') ? ' - '.$userNamesById[$rowUserId] : ''
                        )
                        : '-',
                    (string) ($garantie['typeGarantie'] ?? ''),
                    number_format($estimated, 2, '.', ' '),
                    number_format($retained, 2, '.', ' '),
                    (string) ($garantie['statut'] ?? ''),
                    (string) ($garantie['dateEvaluation'] ?? ''),
                ];
            }

            $title = sprintf('Rapport Garanties - %s', $titleSuffix);
            $fileKind = 'garanties';
            $stats = [
                ['label' => 'Total garanties', 'value' => (string) count($rows)],
                ['label' => 'Valeur estimee', 'value' => number_format($estimatedTotal, 2, '.', ' ').' DT'],
                ['label' => 'Valeur retenue', 'value' => number_format($retainedTotal, 2, '.', ' ').' DT'],
            ];
            $subtitle = 'Export admin des garanties avec synthese par utilisateur ou globale.';
            $accent = '#00bcd4';
        } else {
            $headers = ['ID Credit', 'Utilisateur', 'Compte', 'Type', 'Montant demande', 'Mensualite', 'Statut', 'Date demande'];
            $rows = [];
            $totalAmount = 0.0;
            $totalMonthly = 0.0;
            foreach ($bankingService->listCredits() as $credit) {
                $rowUserId = $this->nullablePositiveInt($credit['idUser'] ?? null);
                if ($selectedUserId !== null && $rowUserId !== $selectedUserId) {
                    continue;
                }

                $amount = (float) ($credit['montantDemande'] ?? 0);
                $monthly = (float) ($credit['mensualite'] ?? 0);
                $totalAmount += $amount;
                $totalMonthly += $monthly;

                $rows[] = [
                    $credit['idCredit'] ?? '',
                    $rowUserId !== null
                        ? sprintf(
                            '#%d%s',
                            $rowUserId,
                            (($userNamesById[$rowUserId] ?? '') !== '') ? ' - '.$userNamesById[$rowUserId] : ''
                        )
                        : '-',
                    (string) ($credit['idCompte'] ?? ''),
                    (string) ($credit['typeCredit'] ?? ''),
                    number_format($amount, 2, '.', ' '),
                    number_format($monthly, 2, '.', ' '),
                    (string) ($credit['statut'] ?? ''),
                    (string) ($credit['dateDemande'] ?? ''),
                ];
            }

            $title = sprintf('Rapport Credits - %s', $titleSuffix);
            $fileKind = 'credits';
            $stats = [
                ['label' => 'Total credits', 'value' => (string) count($rows)],
                ['label' => 'Montant total', 'value' => number_format($totalAmount, 2, '.', ' ').' DT'],
                ['label' => 'Mensualite totale', 'value' => number_format($totalMonthly, 2, '.', ' ').' DT'],
            ];
            $subtitle = 'Export admin des credits avec synthese par utilisateur ou globale.';
            $accent = '#0db98f';
        }

        return new Response($exportService->buildPdf($title, $headers, $rows, $stats ?? [], $subtitle ?? null, $accent ?? '#1565c0'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="nexora-%s%s.pdf"', $fileKind, $selectedUserId !== null ? '-user-'.$selectedUserId : '-all-users'),
        ]);
    }

    #[Route('/admin/transactions/export/pdf', name: 'admin_transactions_export_pdf', methods: ['GET'])]
    public function exportTransactionsPdf(
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

        if (strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_ADMIN') {
            return $this->redirectToRoute('login');
        }

        $idTransaction = $this->positiveQueryInt($request, 'idTransaction');
        [$pdfContent, $filename] = $this->transactionsSectionController->buildTransactionPdf(
            $bankingService,
            $exportService,
            $idTransaction,
            $request->query->all()
        );

        if ($pdfContent === '') {
            return new Response('Transaction introuvable.', 404);
        }

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    private function handleAdminAction(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        GeminiService $geminiService,
        NotificationService $notificationService,
        array $user
    ): void {
        $action = (string) $request->request->get('action', '');
        $profileImagePath = in_array($action, ['user_save', 'admin_profile_save'], true)
            ? $this->handleProfileImageUpload($request, 'profile_image')
            : null;

        try {
            foreach ([
                $this->usersSectionController->handleAdminAction($action, $request, $bankingService, $geminiService, $profileImagePath),
                $this->accountsSectionController->handleAdminAction($action, $request, $bankingService, $user),
                $this->coffrevirtuelleSectionController->handleAdminAction($action, $request, $bankingService, $user),
                $this->transactionsSectionController->handleAdminAction($action, $request, $bankingService),
                $this->reclamationSectionController->handleAdminAction($action, $request, $bankingService),
                $this->creditsSectionController->handleAdminAction($action, $request, $bankingService),
                $this->garantiesSectionController->handleAdminAction($action, $request, $bankingService),
                $this->partnersSectionController->handleAdminAction($action, $request, $bankingService),
                $this->cashbackSectionController->handleAdminAction($action, $request, $bankingService),
                $this->notificationsSectionController->handleAction($action, $notificationService, $user),
                $this->adminAccountSectionController->handleAdminAction($action, $request, $authService, $user, $profileImagePath),
            ] as $flash) {
                if ($flash !== null) {
                    $this->addFlash((string) $flash['type'], (string) $flash['message']);
                    break;
                }
            }
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }
    }

    private function buildAdminTabData(
        string $tab,
        string $panel,
        Request $request,
        BankingService $bankingService,
        NotificationService $notificationService,
        array $user,
        ?int $editUserId = null
    ): array {
        $summary = $bankingService->getAdminDashboard();
        $data = [
            'tab' => $tab,
            'summary' => $summary,
            'items' => [],
            'support' => [],
            'notifications' => $notificationService->getRecentNotificationsFor((int) $user['idUser'], (string) $user['role'], 20),
            'notifications_count' => $notificationService->countUnreadFor((int) $user['idUser'], (string) $user['role']),
        ];
        $data['support']['active_panel'] = $panel;

        // Filter by userId for non-admin users
        $isAdmin = strtoupper((string) ($user['role'] ?? '')) === 'ROLE_ADMIN';
        $filterUserId = $isAdmin ? null : (int) $user['idUser'];

        if ($tab === 'users') {
            $data = $this->mergeTabData($data, $this->usersSectionController->buildAdminData($bankingService, $editUserId));
        } elseif ($tab === 'accounts') {
            $accountsData = $this->accountsSectionController->buildAdminData($bankingService, $request, $filterUserId);
            $vaultsData = $this->coffrevirtuelleSectionController->buildAdminData($bankingService, $filterUserId);
            $data = $this->mergeTabData($data, $accountsData);
            $data = $this->mergeTabData($data, $vaultsData);
            $data['items'] = $panel === 'coffre' ? ($vaultsData['items'] ?? []) : ($accountsData['items'] ?? []);
        } elseif ($tab === 'transactions') {
            $transactionsData = $this->transactionsSectionController->buildAdminData($bankingService, $request->query->all());
            $complaintsData = $this->reclamationSectionController->buildAdminData($bankingService, $request->query->all());
            $data = $this->mergeTabData($data, $transactionsData);
            $data = $this->mergeTabData($data, $complaintsData);
            $data['items'] = $panel === 'reclamation' ? ($complaintsData['items'] ?? []) : ($transactionsData['items'] ?? []);
        } elseif ($tab === 'credits') {
            $creditsData = $this->creditsSectionController->buildAdminData($bankingService);
            $garantiesData = $this->garantiesSectionController->buildAdminData($bankingService);
            $data = $this->mergeTabData($data, $creditsData);
            $data = $this->mergeTabData($data, $garantiesData);
            $data['items'] = $panel === 'garantie' ? ($garantiesData['items'] ?? []) : ($creditsData['items'] ?? []);
        } elseif ($tab === 'cashback') {
            $cashbackData = $this->cashbackSectionController->buildAdminData($bankingService);
            $partnersData = $this->partnersSectionController->buildAdminData($bankingService);
            $data = $this->mergeTabData($data, $cashbackData);
            $data = $this->mergeTabData($data, $partnersData);
            $data['items'] = $panel === 'partenaire' ? ($partnersData['items'] ?? []) : ($cashbackData['items'] ?? []);
        } elseif ($tab === 'database') {
            $data = $this->mergeTabData($data, $this->databaseEntitiesSectionController->buildAdminData());
        } elseif ($tab === 'notifications') {
            $data = $this->mergeTabData($data, $this->notificationsSectionController->buildAdminData($data['notifications']));
        } elseif ($tab === 'admin_account') {
            $data = $this->mergeTabData($data, $this->adminAccountSectionController->buildAdminData());
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
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
            return null;
        }

        $intValue = (int) $normalized;

        return $intValue > 0 ? $intValue : null;
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

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
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

    private function resolveAdminTabTemplate(string $tab): string
    {
        return match ($tab) {
            'users' => 'interfaces/admin/tabs/users.html.twig',
            'admin_account' => 'interfaces/admin/tabs/admin_account.html.twig',
            'accounts' => 'interfaces/admin/tabs/accounts.html.twig',
            'cashback' => 'interfaces/admin/tabs/cashback.html.twig',
            'database' => 'interfaces/admin/tabs/database.html.twig',
            'credits' => 'interfaces/admin/tabs/credits.html.twig',
            'transactions' => 'interfaces/admin/tabs/transactions.html.twig',
            'notifications' => 'interfaces/admin/tabs/notifications.html.twig',
            default => 'interfaces/admin/tabs/dashboard.html.twig',
        };
    }

    private function resolveAdminTabStylesheets(string $tab): array
    {
        return match ($tab) {
            'users' => ['styles/interfaces/sections/admin-users.css'],
            'admin_account' => ['styles/interfaces/sections/admin-profile.css'],
            'accounts' => ['styles/interfaces/sections/admin-accounts.css'],
            default => [],
        };
    }

    private function normalizeAdminTabAndPanel(string $tab, string $panel): array
    {
        $tab = strtolower(trim($tab));
        $panel = strtolower(trim($panel));

        $legacyTabMap = [
            'vaults' => ['accounts', 'coffre'],
            'complaints' => ['transactions', 'reclamation'],
            'garanties' => ['credits', 'garantie'],
            'partners' => ['cashback', 'partenaire'],
        ];

        if (array_key_exists($tab, $legacyTabMap)) {
            [$mappedTab, $mappedPanel] = $legacyTabMap[$tab];
            $tab = $mappedTab;
            if ($panel === '') {
                $panel = $mappedPanel;
            }
        }

        $allowedTabs = ['dashboard', 'users', 'admin_account', 'accounts', 'transactions', 'credits', 'cashback', 'database', 'notifications'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'dashboard';
        }

        $defaultPanelByTab = [
            'accounts' => 'compte',
            'transactions' => 'transaction',
            'credits' => 'credit',
            'cashback' => 'partenaire',
        ];

        $allowedPanelsByTab = [
            'accounts' => ['compte', 'coffre'],
            'transactions' => ['transaction', 'reclamation'],
            'credits' => ['credit', 'garantie'],
            'cashback' => ['partenaire', 'cashback'],
        ];

        if (array_key_exists($tab, $allowedPanelsByTab)) {
            if (!in_array($panel, $allowedPanelsByTab[$tab], true)) {
                $panel = $defaultPanelByTab[$tab];
            }
        } else {
            $panel = '';
        }

        return [$tab, $panel];
    }
}
