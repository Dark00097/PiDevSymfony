<?php

namespace App\Controller;

use App\Service\AdminSecuritySettingsService;
use App\Service\AuthService;
use App\Service\NotificationService;
use App\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminFeaturesController extends AbstractController
{
    #[Route('/admin/features', name: 'admin_features_home', methods: ['GET'])]
    #[Route('/admin/features/{section}', name: 'admin_features', requirements: ['section' => 'security|statistics'], methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AuthService $authService,
        AdminSecuritySettingsService $adminSecuritySettingsService,
        StatisticsService $statisticsService,
        NotificationService $notificationService,
        string $section = 'security',
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

        $section = trim($section) !== '' ? $section : 'security';
        $sectionStylesheets = ['styles/interfaces/admin-features.css'];
        if ($section === 'security') {
            $sectionStylesheets[] = 'styles/interfaces/sections/admin-security.css';
        }

        if ($request->isMethod('POST')) {
            try {
                $this->handleAction($request, $user, $authService, $adminSecuritySettingsService);
                $this->addFlash('success', 'Admin feature settings were updated.');

                return $this->redirectToRoute('admin_features', ['section' => $section]);
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('interfaces/admin/MainView.html.twig', [
            'mode' => 'admin',
            'current_user' => $user,
            'tab' => $section,
            'tab_template' => $section === 'statistics'
                ? 'interfaces/admin/features/statistics.html.twig'
                : 'interfaces/admin/features/security.html.twig',
            'tabs' => [
                ['key' => 'dashboard', 'label' => 'Tableau de Bord'],
                ['key' => 'users', 'label' => 'Gestion Utilisateurs'],
                ['key' => 'admin_account', 'label' => 'Compte admin'],
                ['key' => 'accounts', 'label' => 'Comptes Bancaires'],
                ['key' => 'transactions', 'label' => 'Transactions'],
                ['key' => 'credits', 'label' => 'Gestion Credit'],
                ['key' => 'cashback', 'label' => 'Gestion Cashback'],
                ['key' => 'notifications', 'label' => 'Notifications'],
            ],
            'route_name' => 'admin_dashboard',
            'tab_stylesheets' => $sectionStylesheets,
            'notifications_count' => $notificationService->countUnreadFor((int) $user['idUser'], (string) $user['role']),
            'notifications' => $notificationService->getRecentNotificationsFor((int) $user['idUser'], (string) $user['role'], 8),
            'settings' => $adminSecuritySettingsService->getSettings(),
            'settings_file' => $adminSecuritySettingsService->getStoragePath(),
            'statistics' => $statisticsService->getAdminStatistics(),
        ]);
    }

    private function handleAction(
        Request $request,
        array $user,
        AuthService $authService,
        AdminSecuritySettingsService $adminSecuritySettingsService,
    ): void {
        $action = (string) $request->request->get('action', '');
        $userId = (int) ($user['idUser'] ?? 0);

        if ($action === 'save_security_settings') {
            $adminSecuritySettingsService->saveSettings([
                'require_biometric_on_admin_login' => (string) $request->request->get('require_biometric_on_admin_login', '0') === '1',
                'require_biometric_on_sensitive_actions' => (string) $request->request->get('require_biometric_on_sensitive_actions', '0') === '1',
                'enable_email_otp' => (string) $request->request->get('enable_email_otp', '0') === '1',
            ]);

            return;
        }

        if ($action === 'save_admin_profile') {
            $authService->updateProfile($userId, [
                'nom' => (string) $request->request->get('nom', ''),
                'prenom' => (string) $request->request->get('prenom', ''),
                'telephone' => (string) $request->request->get('telephone', ''),
                'email' => (string) $request->request->get('email', ''),
                'biometric_enabled' => (string) $request->request->get('biometric_enabled', (string) ($user['biometric_enabled'] ?? '0')),
            ]);

            return;
        }

        if ($action === 'change_admin_password') {
            $currentPassword = (string) $request->request->get('current_password', '');
            $newPassword = (string) $request->request->get('new_password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if ($newPassword !== $confirmPassword) {
                throw new \InvalidArgumentException('New password and confirmation do not match.');
            }

            if (strlen($newPassword) < 8) {
                throw new \InvalidArgumentException('New password must be at least 8 characters.');
            }

            $authService->changePassword($userId, $currentPassword, $newPassword);
        }
    }
}
