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
use App\Controller\Sections\SupportChatController as SupportChatSectionController;
use App\Controller\Sections\TransactionsController as TransactionsSectionController;
use App\Controller\Sections\UsersController as UsersSectionController;
use App\Service\AuthService;
use App\Service\BankingService;
use App\Service\CloudinaryUploader;
use App\Service\CreditAiAssistantService;
use App\Service\ExportService;
use App\Service\GeminiService;
use App\Service\NotificationService;
use App\Service\SupportChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private readonly SupportChatSectionController $supportChatSectionController,
        private readonly SupportChatService $supportChatService,
        private readonly CloudinaryUploader $cloudinaryUploader,
        private readonly CreditAiAssistantService $creditAiAssistantService,
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
                ['key' => 'support', 'label' => 'Support Chat'],
                ['key' => 'notifications', 'label' => 'Notifications'],
            ],
            'panel' => $panel,
        ]));
    }

    #[Route('/admin/document/{id}/valider', name: 'admin_document_valider', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function validerDocument(
        int $id,
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null || strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_ADMIN') {
            return $this->redirectToRoute('login');
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);
            $this->addFlash('error', $blockedReason);

            return $this->redirectToRoute('login');
        }

        $request->request->set('idGarantie', (string) $id);
        $request->request->set('statutVerificationDocument', 'valide');
        $request->request->set('statutDocument', 'valide');
        $flash = $this->garantiesSectionController->handleAdminAction('garantie_document_review', $request, $bankingService);
        if ($flash !== null) {
            $this->addFlash((string) ($flash['type'] ?? 'info'), (string) ($flash['message'] ?? 'Action terminee.'));
        } else {
            $this->addFlash('success', 'Document valide.');
        }

        return $this->redirectToRoute('admin_dashboard', ['tab' => 'credits', 'panel' => 'garantie']);
    }

    #[Route('/admin/document/{id}/refuser', name: 'admin_document_refuser', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function refuserDocument(
        int $id,
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null || strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_ADMIN') {
            return $this->redirectToRoute('login');
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);
            $this->addFlash('error', $blockedReason);

            return $this->redirectToRoute('login');
        }

        $request->request->set('idGarantie', (string) $id);
        $request->request->set('statutVerificationDocument', 'rejete');
        $request->request->set('statutDocument', 'refuse');
        $flash = $this->garantiesSectionController->handleAdminAction('garantie_document_review', $request, $bankingService);
        if ($flash !== null) {
            $this->addFlash((string) ($flash['type'] ?? 'info'), (string) ($flash['message'] ?? 'Action terminee.'));
        } else {
            $this->addFlash('success', 'Document refuse.');
        }

        return $this->redirectToRoute('admin_dashboard', ['tab' => 'credits', 'panel' => 'garantie']);
    }

    #[Route('/admin/document/{id}/demander-complement', name: 'admin_document_demander_complement', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function demanderPieceComplementaire(
        int $id,
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        NotificationService $notificationService,
    ): JsonResponse {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null || strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_ADMIN') {
            return new JsonResponse(['ok' => false, 'message' => 'Acces reserve a l admin.'], 403);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);

            return new JsonResponse(['ok' => false, 'message' => $blockedReason], 401);
        }

        $csrfToken = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->isCsrfTokenValid('admin_complement_request', $csrfToken)) {
            return new JsonResponse(['ok' => false, 'message' => 'Jeton CSRF invalide.'], 419);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $selectedCredit = null;
        foreach ($bankingService->listCredits() as $credit) {
            if ((int) ($credit['idCredit'] ?? 0) === $id) {
                $selectedCredit = $credit;
                break;
            }
        }
        if ($selectedCredit === null) {
            return new JsonResponse(['ok' => false, 'message' => 'Credit introuvable.'], 404);
        }

        $garantieId = max(0, (int) ($payload['idGarantie'] ?? 0));
        $adminMessage = trim((string) ($payload['message'] ?? ''));
        if ($adminMessage === '') {
            $adminMessage = sprintf(
                'Des pieces complementaires sont requises pour finaliser le dossier credit #%d.',
                $id
            );
        }

        try {
            $selectedCredit['statut'] = 'Piece complementaire demandee';
            $bankingService->saveCredit($selectedCredit, $id);

            $updatedDocumentStatus = 'en_attente';
            if ($garantieId > 0) {
                foreach ($bankingService->listGaranties() as $garantie) {
                    if ((int) ($garantie['idGarantie'] ?? 0) !== $garantieId) {
                        continue;
                    }
                    if ((int) ($garantie['idCredit'] ?? 0) !== $id) {
                        continue;
                    }

                    $garantie['statut'] = 'En cours_verification';
                    $garantie['statutVerificationDocument'] = 'en_attente';
                    $garantie['statutDocument'] = 'en_attente';
                    $garantie['remarqueAdmin'] = $adminMessage;
                    $bankingService->saveGarantie($garantie, $garantieId);
                    break;
                }
            }

            $targetUserId = (int) ($selectedCredit['idUser'] ?? $selectedCredit['resolved_user_id'] ?? 0);
            if ($targetUserId > 0) {
                $notificationService->createNotification(
                    $targetUserId,
                    null,
                    $targetUserId,
                    'CREDIT_COMPLEMENT_REQUEST',
                    'Piece complementaire demandee',
                    $adminMessage
                );
            }

            return new JsonResponse([
                'ok' => true,
                'message' => 'Demande de piece complementaire envoyee avec succes.',
                'creditStatus' => 'Piece complementaire demandee',
                'documentStatus' => $updatedDocumentStatus,
                'messageClient' => $adminMessage,
            ]);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'ok' => false,
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Impossible de traiter la demande.',
            ], 500);
        }
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

    #[Route('/admin/users/export/pdf', name: 'admin_users_export_pdf', methods: ['GET'])]
    public function exportUsersPdf(
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

        $users = $bankingService->listUsers();
        $fields = $this->buildUsersExportFields($users);
        $headers = array_map(fn (string $field): string => $this->buildUsersExportLabel($field), $fields);
        $rows = [];

        foreach ($users as $row) {
            $line = [];
            foreach ($fields as $field) {
                $line[] = $this->buildUsersExportValue($row[$field] ?? null, $field);
            }
            $rows[] = $line;
        }

        $activeCount = 0;
        $pendingCount = 0;
        $adminCount = 0;
        foreach ($users as $row) {
            $status = strtoupper(trim((string) ($row['status'] ?? '')));
            $role = strtoupper(trim((string) ($row['role'] ?? '')));
            if ($status === 'ACTIVE') {
                $activeCount++;
            }
            if ($status === 'PENDING') {
                $pendingCount++;
            }
            if ($role === 'ROLE_ADMIN') {
                $adminCount++;
            }
        }

        $stats = [
            ['label' => 'Total utilisateurs', 'value' => (string) count($users)],
            ['label' => 'Actifs', 'value' => (string) $activeCount],
            ['label' => 'En attente', 'value' => (string) $pendingCount],
            ['label' => 'Administrateurs', 'value' => (string) $adminCount],
        ];

        $pdfContent = $exportService->buildPdf(
            'Rapport Utilisateurs - Tous les comptes',
            $headers,
            $rows,
            $stats,
            'Export complet des utilisateurs depuis le panneau admin Nexora.',
            '#6c63ff'
        );

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="nexora-users-all.pdf"',
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
                $this->supportChatSectionController->handleAdminAction($action, $request, $this->supportChatService, $user),
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
        // Always inject pending accounts so the bell panel works on every tab
        $data['support']['pending_accounts'] = $bankingService->listPendingAccounts();

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
            $data['support'] = array_replace($data['support'], $this->buildCreditAiViewData($request, $data['support']));
        } elseif ($tab === 'cashback') {
            $cashbackData = $this->cashbackSectionController->buildAdminData($bankingService);
            $partnersData = $this->partnersSectionController->buildAdminData($bankingService);
            $data = $this->mergeTabData($data, $cashbackData);
            $data = $this->mergeTabData($data, $partnersData);
            $data['items'] = $panel === 'partenaire' ? ($partnersData['items'] ?? []) : ($cashbackData['items'] ?? []);
        } elseif ($tab === 'support') {
            $data = $this->mergeTabData($data, $this->supportChatSectionController->buildAdminData($this->supportChatService, $request));
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

    /**
     * @param array<string, mixed> $support
     * @return array<string, mixed>
     */
    private function buildCreditAiViewData(Request $request, array $support): array
    {
        $credits = is_array($support['credits'] ?? null) ? $support['credits'] : [];
        $garanties = is_array($support['garanties'] ?? null) ? $support['garanties'] : [];
        if ($credits === []) {
            return [
                'ai_selected_credit_id' => 0,
                'ai_analysis' => null,
                'ai_simulation' => null,
            ];
        }

        $selectedCreditId = $this->positiveQueryInt($request, 'ai_credit_id');
        if ($selectedCreditId === null) {
            $selectedCreditId = (int) ($credits[0]['idCredit'] ?? 0);
        }

        $selectedCredit = null;
        foreach ($credits as $credit) {
            if ((int) ($credit['idCredit'] ?? 0) === $selectedCreditId) {
                $selectedCredit = $credit;
                break;
            }
        }
        if (!is_array($selectedCredit)) {
            $selectedCredit = $credits[0];
            $selectedCreditId = (int) ($selectedCredit['idCredit'] ?? 0);
        }

        $linkedGarantie = null;
        foreach ($garanties as $garantie) {
            if ((int) ($garantie['idCredit'] ?? 0) === $selectedCreditId) {
                $linkedGarantie = $garantie;
                break;
            }
        }
        if (is_array($linkedGarantie)) {
            $selectedCredit['garantieType'] = $linkedGarantie['typeGarantie'] ?? '';
            $selectedCredit['garantieValeur'] = $linkedGarantie['valeurRetenue'] ?? $linkedGarantie['valeurEstimee'] ?? 0;
            $selectedCredit['valeurRetenue'] = $linkedGarantie['valeurRetenue'] ?? 0;
            $selectedCredit['valeurEstimee'] = $linkedGarantie['valeurEstimee'] ?? 0;
            $selectedCredit['documentJustificatif'] = $linkedGarantie['documentJustificatif'] ?? '';
            $selectedCredit['statutDocument'] = $linkedGarantie['statutDocument'] ?? ($linkedGarantie['statutVerificationDocument'] ?? '');
            $selectedCredit['statutVerificationDocument'] = $linkedGarantie['statutVerificationDocument'] ?? '';
            $selectedCredit['statutGarantie'] = $linkedGarantie['statut'] ?? '';
        }

        $analysis = $this->creditAiAssistantService->generateFinalDecision($selectedCredit);

        $simAmount = $request->query->get('sim_amount');
        $simDuration = $request->query->get('sim_duration');
        $simulation = null;
        if ($simAmount !== null && $simDuration !== null && trim((string) $simAmount) !== '' && trim((string) $simDuration) !== '') {
            $amount = (float) str_replace(',', '.', (string) $simAmount);
            $duration = (int) $simDuration;
            if ($amount > 0 && $duration > 0) {
                $simulation = $this->creditAiAssistantService->simulateScenario($selectedCredit, $amount, $duration);
            }
        }

        return [
            'ai_selected_credit_id' => $selectedCreditId,
            'ai_selected_credit' => $selectedCredit,
            'ai_analysis' => $analysis,
            'ai_simulation' => $simulation,
        ];
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

        return $this->cloudinaryUploader->uploadProfileImage($file);
    }

    private function resolveAdminTabTemplate(string $tab): string
    {
        return match ($tab) {
            'users' => 'interfaces/admin/tabs/users.html.twig',
            'admin_account' => 'interfaces/admin/tabs/admin_account.html.twig',
            'accounts' => 'interfaces/admin/tabs/accounts.html.twig',
            'cashback' => 'interfaces/admin/tabs/cashback.html.twig',
            'support' => 'interfaces/admin/tabs/support.html.twig',
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

        $allowedTabs = ['dashboard', 'users', 'admin_account', 'accounts', 'transactions', 'credits', 'cashback', 'support', 'database', 'notifications'];
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
            'accounts' => ['compte', 'coffre', 'ia'],
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

    /**
     * @param list<array<string, mixed>> $users
     * @return list<string>
     */
    private function buildUsersExportFields(array $users): array
    {
        $preferredOrder = [
            'idUser',
            'nom',
            'prenom',
            'email',
            'telephone',
            'role',
            'status',
            'created_at',
            'updated_at',
            'account_opened_from',
            'account_opened_location',
            'account_opened_lat',
            'account_opened_lng',
            'last_online_at',
            'last_online_from',
            'last_login_location',
            'last_login_lat',
            'last_login_lng',
            'biometric_enabled',
            'profile_image_path',
        ];

        $collected = [];
        foreach ($users as $row) {
            foreach ($row as $key => $_value) {
                if (!is_string($key) || $this->isSensitiveUsersExportField($key)) {
                    continue;
                }
                $collected[$key] = true;
            }
        }

        if ($collected === []) {
            return ['idUser', 'nom', 'prenom', 'email', 'telephone', 'role', 'status', 'created_at'];
        }

        $ordered = [];
        foreach ($preferredOrder as $field) {
            if (isset($collected[$field])) {
                $ordered[] = $field;
                unset($collected[$field]);
            }
        }

        ksort($collected, SORT_NATURAL | SORT_FLAG_CASE);

        return array_merge($ordered, array_keys($collected));
    }

    private function isSensitiveUsersExportField(string $field): bool
    {
        $normalized = strtolower(trim($field));

        return str_contains($normalized, 'password')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'salt')
            || str_contains($normalized, 'descriptor')
            || str_contains($normalized, 'otp')
            || str_contains($normalized, 'pin');
    }

    private function buildUsersExportLabel(string $field): string
    {
        return match ($field) {
            'idUser' => 'ID Utilisateur',
            'nom' => 'Nom',
            'prenom' => 'Prenom',
            'email' => 'Email',
            'telephone' => 'Telephone',
            'role' => 'Role',
            'status' => 'Statut',
            'created_at' => 'Cree le',
            'updated_at' => 'Maj le',
            'account_opened_from' => 'Source inscription',
            'account_opened_location' => 'Lieu inscription',
            'account_opened_lat' => 'Lat inscription',
            'account_opened_lng' => 'Lng inscription',
            'last_online_at' => 'Derniere connexion',
            'last_online_from' => 'Dernier appareil',
            'last_login_location' => 'Derniere localisation',
            'last_login_lat' => 'Lat derniere connexion',
            'last_login_lng' => 'Lng derniere connexion',
            'biometric_enabled' => 'Biometrie',
            'profile_image_path' => 'Photo profil',
            default => ucfirst(strtolower((string) preg_replace('/\s+/', ' ', trim((string) preg_replace('/(?<!^)([A-Z])/', ' $1', str_replace('_', ' ', $field)))))),
        };
    }

    private function buildUsersExportValue(mixed $value, string $field): string
    {
        if ($value === null) {
            return '-';
        }

        if ($field === 'biometric_enabled') {
            return ((string) $value === '1') ? 'Oui' : 'Non';
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $json !== false ? $json : '-';
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : '-';
    }

    #[Route('/admin/api/reclamations/analyse', name: 'admin_api_reclamations_analyse', methods: ['POST'])]
    public function analyseReclamations(
        Request $request,
        AuthService $authService,
        GeminiService $geminiService,
        \Doctrine\DBAL\Connection $connection
    ): Response {
        try {
            $session = $request->getSession();
            $user = $authService->getAuthenticatedUser($session);

            if ($user === null || strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_ADMIN') {
                return $this->json(['error' => 'Accès refusé'], 403);
            }

            if (!$geminiService->isConfigured()) {
                return $this->json(['error' => 'API IA non configurée. Vérifiez GROK_API_KEY dans .env'], 503);
            }

            $reclamations = $connection->fetchAllAssociative(
                'SELECT r.idReclamation, r.typeReclamation, r.description, r.status, r.dateReclamation,
                        u.nom, u.prenom
                 FROM reclamation r
                 LEFT JOIN users u ON u.idUser = r.idUser
                 ORDER BY r.dateReclamation DESC
                 LIMIT 100'
            );

            if (empty($reclamations)) {
                return $this->json(['error' => 'Aucune réclamation à analyser'], 404);
            }

            $total       = count($reclamations);
            $byType      = [];
            $byStatus    = [];
            $bySentiment = [];
            foreach ($reclamations as $r) {
                $t    = $r['typeReclamation'] ?? 'Inconnu';
                $s    = $r['status']          ?? 'Inconnu';
                $sent = 'neutral'; // colonne sentiment non disponible en BDD
                $byType[$t]         = ($byType[$t]         ?? 0) + 1;
                $byStatus[$s]       = ($byStatus[$s]       ?? 0) + 1;
                $bySentiment[$sent] = ($bySentiment[$sent] ?? 0) + 1;
            }

            arsort($byType);
            $topTypes  = array_slice($byType, 0, 5, true);
            $typesStr  = implode(', ', array_map(fn($k, $v) => "$k ($v)", array_keys($topTypes), $topTypes));
            $statusStr = implode(', ', array_map(fn($k, $v) => "$k ($v)", array_keys($byStatus), $byStatus));
            $sentStr   = implode(', ', array_map(fn($k, $v) => "$k ($v)", array_keys($bySentiment), $bySentiment));

            $prompt = sprintf(
                "Tu es un analyste bancaire expert. Analyse ces %d réclamations clients et fournis un rapport structuré en français.\n\n" .
                "DONNÉES :\n- Types : %s\n- Statuts : %s\n- Sentiments : %s\n\n" .
                "RAPPORT (format structuré) :\n" .
                "1. RÉSUMÉ EXÉCUTIF (2-3 phrases)\n" .
                "2. PROBLÈMES PRINCIPAUX (3 problèmes fréquents avec impact)\n" .
                "3. ANALYSE DES SENTIMENTS (interprétation)\n" .
                "4. RECOMMANDATIONS (3 actions concrètes)\n" .
                "5. INDICATEUR DE RISQUE (Faible/Moyen/Élevé avec justification)",
                $total, $typesStr, $statusStr, $sentStr
            );

            $result = $geminiService->analyseReclamations($prompt);

            if ($result === null) {
                return $this->json([
                    'error' => 'Erreur API IA : Clé API invalide ou expirée. Veuillez vérifier GROK_API_KEY dans .env et obtenir une nouvelle clé sur https://console.groq.com/keys'
                ], 500);
            }

            return $this->json([
                'analyse' => $result,
                'stats'   => [
                    'total'        => $total,
                    'by_type'      => $byType,
                    'by_status'    => $byStatus,
                    'by_sentiment' => $bySentiment,
                ],
            ]);

        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/admin/api/reclamation/respond-ai', name: 'admin_api_reclamation_respond_ai', methods: ['POST'])]
    public function respondWithAI(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        \App\Service\ReclamationResponseService $responseService,
        \Doctrine\DBAL\Connection $connection
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        
        if ($user === null || strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_ADMIN') {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $reclamationId = (int) $request->request->get('reclamationId', 0);
        $userName = trim((string) $request->request->get('userName', ''));
        $reclamationType = trim((string) $request->request->get('reclamationType', ''));
        $description = trim((string) $request->request->get('description', ''));
        $generateOnly = $request->request->get('generateOnly', '0') === '1';
        $sendEmail = $request->request->get('sendEmail', '0') === '1';
        $customResponse = trim((string) $request->request->get('customResponse', ''));

        if ($reclamationId <= 0) {
            return $this->json(['error' => 'ID réclamation invalide'], 400);
        }

        try {
            // Récupérer les informations complètes de la réclamation
            $reclamation = $connection->fetchAssociative(
                'SELECT r.*, u.email, u.nom, u.prenom 
                 FROM reclamation r
                 LEFT JOIN users u ON u.idUser = r.idUser
                 WHERE r.idReclamation = ?',
                [$reclamationId]
            );

            if (!$reclamation) {
                return $this->json(['error' => 'Réclamation introuvable'], 404);
            }

            $userEmail = $reclamation['email'] ?? '';
            $fullName = trim(($reclamation['prenom'] ?? '') . ' ' . ($reclamation['nom'] ?? ''));
            
            if (empty($userEmail)) {
                return $this->json(['error' => 'Email utilisateur introuvable'], 400);
            }

            // Si on a une réponse personnalisée, l'utiliser, sinon générer avec l'IA
            if ($customResponse !== '') {
                $aiResponse = $customResponse;
            } else {
                // Générer la réponse avec l'IA
                $aiResponse = $responseService->generateResponse(
                    $reclamationType ?: $reclamation['typeReclamation'] ?? 'Réclamation',
                    $description ?: $reclamation['description'] ?? '',
                    $fullName ?: $userName
                );
            }

            // Si on veut juste générer sans envoyer
            if ($generateOnly) {
                return $this->json([
                    'success' => true,
                    'response' => $aiResponse,
                    'email' => $userEmail,
                ]);
            }

            // Si on veut envoyer l'email
            if ($sendEmail || $customResponse !== '') {
                $emailSent = false;
                $emailError = null;
                try {
                    $responseService->sendResponseEmail(
                        $userEmail,
                        $fullName ?: $userName,
                        $reclamationType ?: $reclamation['typeReclamation'] ?? 'Réclamation',
                        $aiResponse,
                        $reclamationId
                    );
                    $emailSent = true;
                } catch (\Throwable $mailEx) {
                    error_log('SMTP error (non-blocking): ' . $mailEx->getMessage());
                    $emailError = 'Email non envoyé (problème réseau SMTP) — la réponse a bien été générée.';
                }

                return $this->json([
                    'success' => true,
                    'message' => $emailSent ? 'Email envoyé avec succès' : $emailError,
                    'email_sent' => $emailSent,
                    'response' => $aiResponse,
                    'email' => $userEmail,
                ]);
            }

            // Par défaut, générer et envoyer
            $emailSent = false;
            $emailError = null;
            try {
                $responseService->sendResponseEmail(
                    $userEmail,
                    $fullName ?: $userName,
                    $reclamationType ?: $reclamation['typeReclamation'] ?? 'Réclamation',
                    $aiResponse,
                    $reclamationId
                );
                $emailSent = true;
            } catch (\Throwable $mailEx) {
                error_log('SMTP error (non-blocking): ' . $mailEx->getMessage());
                $emailError = 'Réponse générée — email non envoyé (problème réseau SMTP).';
            }

            return $this->json([
                'success' => true,
                'message' => $emailSent ? 'Réponse générée et envoyée par email' : $emailError,
                'email_sent' => $emailSent,
                'response' => $aiResponse,
                'email' => $userEmail,
            ]);
            
        } catch (\Throwable $e) {
            error_log('AI Response error: ' . $e->getMessage());
            return $this->json([
                'error' => 'Erreur lors de la génération de la réponse',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
