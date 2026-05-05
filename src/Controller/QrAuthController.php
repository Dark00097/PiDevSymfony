<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\MobileAuthService;
use App\Service\QrSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class QrAuthController extends AbstractController
{
    #[Route('/portal/profile/mobile-trust/start', name: 'portal_mobile_trust_start', methods: ['POST'])]
    public function startTrustQr(
        Request $request,
        AuthService $authService,
        MobileAuthService $mobileAuthService,
        QrSessionService $qrSessionService
    ): JsonResponse {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->json(['ok' => false, 'message' => 'User not authenticated.'], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            return $this->json(['ok' => false, 'message' => $blockedReason], 403);
        }

        if (strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_USER') {
            return $this->json(['ok' => false, 'message' => 'Only user accounts can generate device trust QR.'], 403);
        }

        try {
            $mobileAuthService->ensureSchemaReady();
            $trust = $qrSessionService->createTrustSession((int) ($user['idUser'] ?? 0));
            $payload = json_encode([
                'type' => 'trust_device',
                'token' => $trust['token'],
                'expires_at' => $trust['expires_at'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $svg = $qrSessionService->buildQrSvg($payload !== false ? $payload : 'invalid');
        } catch (\Throwable $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return $this->json([
            'ok' => true,
            'token' => $trust['token'],
            'expires_at' => $trust['expires_at'],
            'qr_payload' => $payload,
            'qr_svg_data_url' => 'data:image/svg+xml;base64,'.base64_encode($svg),
            'trusted_devices' => $mobileAuthService->listTrustedDevices((int) ($user['idUser'] ?? 0)),
        ]);
    }

    #[Route('/auth/qr/start', name: 'auth_qr_start', methods: ['POST'])]
    public function startWebLoginQr(Request $request, AuthService $authService, QrSessionService $qrSessionService): JsonResponse
    {
        $session = $request->getSession();
        $currentUser = $authService->getAuthenticatedUser($session);
        if ($currentUser !== null) {
            $blockedReason = $authService->getLoginBlockReason($currentUser);
            if ($blockedReason === null) {
                return $this->json([
                    'ok' => true,
                    'already_authenticated' => true,
                    'redirect_url' => $this->resolveRoleRedirectUrl((string) ($currentUser['role'] ?? '')),
                ]);
            }

            $authService->logoutUser($session);
        }

        $browserSessionId = $session->getId();
        if (trim($browserSessionId) === '') {
            $session->start();
            $browserSessionId = $session->getId();
        }

        try {
            $loginSession = $qrSessionService->createWebLoginSession(
                $browserSessionId,
                (string) $request->getClientIp(),
                (string) $request->headers->get('User-Agent', '')
            );
            $payload = json_encode([
                'type' => 'web_login',
                'token' => $loginSession['token'],
                'expires_at' => $loginSession['expires_at'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $svg = $qrSessionService->buildQrSvg($payload !== false ? $payload : 'invalid');
        } catch (\Throwable $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return $this->json([
            'ok' => true,
            'token' => $loginSession['token'],
            'expires_at' => $loginSession['expires_at'],
            'qr_payload' => $payload,
            'qr_svg_data_url' => 'data:image/svg+xml;base64,'.base64_encode($svg),
        ]);
    }

    #[Route('/auth/qr/poll', name: 'auth_qr_poll', methods: ['GET'])]
    public function pollWebLoginQr(Request $request, AuthService $authService, QrSessionService $qrSessionService): JsonResponse
    {
        $token = trim((string) $request->query->get('token', ''));
        if ($token === '') {
            return $this->json(['ok' => false, 'message' => 'QR token is required.'], 422);
        }

        $session = $request->getSession();
        $browserSessionId = $session->getId();
        if (trim($browserSessionId) === '') {
            return $this->json(['ok' => false, 'message' => 'Browser session is missing.'], 422);
        }

        $row = $qrSessionService->readWebLoginSessionForBrowser($token, $browserSessionId);
        if ($row === null) {
            return $this->json(['ok' => false, 'message' => 'QR session not found for this browser.'], 404);
        }

        $status = strtoupper((string) ($row['status'] ?? 'PENDING'));
        $expiresAt = (string) ($row['expires_at'] ?? '');
        if ($this->isExpired($expiresAt) && $status === 'PENDING') {
            return $this->json([
                'ok' => true,
                'status' => 'EXPIRED',
                'message' => 'QR session expired.',
            ]);
        }

        if ($status === 'APPROVED') {
            $approvedUserId = $qrSessionService->consumeApprovedWebLoginSession($token, $browserSessionId);
            if ($approvedUserId === null) {
                return $this->json([
                    'ok' => true,
                    'status' => 'EXPIRED',
                    'message' => 'QR session expired.',
                ]);
            }

            $user = $authService->findUserById($approvedUserId);
            if ($user === null) {
                return $this->json([
                    'ok' => false,
                    'status' => 'ERROR',
                    'message' => 'Approved user account not found.',
                ], 422);
            }

            $blockedReason = $authService->getLoginBlockReason($user);
            if ($blockedReason !== null) {
                return $this->json([
                    'ok' => false,
                    'status' => 'BLOCKED',
                    'message' => $blockedReason,
                ], 403);
            }

            $authService->loginUser($session, $user);

            return $this->json([
                'ok' => true,
                'status' => 'APPROVED',
                'redirect_url' => $this->resolveRoleRedirectUrl((string) ($user['role'] ?? 'ROLE_USER')),
            ]);
        }

        if ($status === 'CONSUMED') {
            $user = $authService->getAuthenticatedUser($session);
            if ($user !== null && $authService->getLoginBlockReason($user) === null) {
                return $this->json([
                    'ok' => true,
                    'status' => 'APPROVED',
                    'redirect_url' => $this->resolveRoleRedirectUrl((string) ($user['role'] ?? 'ROLE_USER')),
                ]);
            }
        }

        return $this->json([
            'ok' => true,
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }

    private function resolveRoleRedirectUrl(string $role): string
    {
        return strtoupper($role) === 'ROLE_ADMIN'
            ? $this->generateUrl('admin_dashboard')
            : $this->generateUrl('portal_dashboard');
    }

    private function isExpired(string $expiresAt): bool
    {
        if (trim($expiresAt) === '') {
            return true;
        }

        try {
            $expiry = new \DateTimeImmutable($expiresAt);
        } catch (\Throwable) {
            return true;
        }

        return $expiry < new \DateTimeImmutable('now');
    }
}

