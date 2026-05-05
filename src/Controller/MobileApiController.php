<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\MobileAuthService;
use App\Service\NotificationService;
use App\Service\QrSessionService;
use App\Service\SupportChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mobile')]
final class MobileApiController extends AbstractController
{
    #[Route('/login', name: 'mobile_api_login', methods: ['POST'])]
    public function login(Request $request, AuthService $authService, MobileAuthService $mobileAuthService): JsonResponse
    {
        $payload = $this->readPayload($request);
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $deviceId = $mobileAuthService->normalizeDeviceId((string) ($payload['device_id'] ?? ''));
        $deviceName = $mobileAuthService->normalizeDeviceName((string) ($payload['device_name'] ?? 'Mobile device'));
        $platform = trim((string) ($payload['platform'] ?? ''));
        $appVersion = trim((string) ($payload['app_version'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['ok' => false, 'message' => 'Valid email is required.'], 422);
        }

        if (trim($password) === '') {
            return $this->json(['ok' => false, 'message' => 'Password is required.'], 422);
        }

        if ($deviceId === '') {
            return $this->json(['ok' => false, 'message' => 'device_id is required.'], 422);
        }

        try {
            $mobileAuthService->ensureSchemaReady();
            $user = $authService->authenticate($email, $password, $request);
        } catch (\Throwable $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        if ($user === null) {
            return $this->json(['ok' => false, 'message' => 'Invalid email or password.'], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            return $this->json(['ok' => false, 'message' => $blockedReason], 403);
        }

        if (strtoupper((string) ($user['role'] ?? '')) !== 'ROLE_USER') {
            return $this->json(['ok' => false, 'message' => 'Only user accounts can sign in on the mobile app.'], 403);
        }

        try {
            $issued = $mobileAuthService->issueAccessToken((int) ($user['idUser'] ?? 0), $deviceId, $deviceName, $platform, $appVersion);
        } catch (\Throwable $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], 500);
        }

        $trusted = $mobileAuthService->isDeviceTrusted((int) ($user['idUser'] ?? 0), $deviceId);

        return $this->json([
            'ok' => true,
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'trusted_device' => $trusted,
            'user' => $this->normalizeUser($user),
        ]);
    }

    #[Route('/logout', name: 'mobile_api_logout', methods: ['POST'])]
    public function logout(Request $request, MobileAuthService $mobileAuthService): JsonResponse
    {
        $context = $mobileAuthService->authenticateRequest($request);
        if ($context === null) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized mobile token.'], 401);
        }

        $mobileAuthService->revokeAccessTokenById((int) ($context['token']['idMobileAccessToken'] ?? 0));

        return $this->json(['ok' => true]);
    }

    #[Route('/me', name: 'mobile_api_me', methods: ['GET'])]
    public function me(Request $request, AuthService $authService, MobileAuthService $mobileAuthService): JsonResponse
    {
        $context = $this->requireActiveMobileContext($request, $authService, $mobileAuthService);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        return $this->json([
            'ok' => true,
            'trusted_device' => (bool) ($context['trusted_device'] ?? false),
            'user' => $this->normalizeUser((array) ($context['user'] ?? [])),
        ]);
    }

    #[Route('/profile', name: 'mobile_api_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, AuthService $authService, MobileAuthService $mobileAuthService): JsonResponse
    {
        $context = $this->requireActiveMobileContext($request, $authService, $mobileAuthService);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = (array) ($context['user'] ?? []);
        $userId = (int) ($user['idUser'] ?? 0);

        if ($request->isMethod('POST')) {
            $payload = $this->readPayload($request);
            $update = [];
            foreach (['nom', 'prenom', 'email', 'telephone'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $update[$field] = trim((string) $payload[$field]);
                }
            }

            try {
                $authService->updateProfile($userId, $update);
                $user = $authService->findUserById($userId) ?? $user;
            } catch (\Throwable $exception) {
                return $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }
        }

        return $this->json([
            'ok' => true,
            'trusted_device' => (bool) ($context['trusted_device'] ?? false),
            'user' => $this->normalizeUser($user),
        ]);
    }

    #[Route('/notifications', name: 'mobile_api_notifications', methods: ['GET'])]
    public function notifications(
        Request $request,
        AuthService $authService,
        MobileAuthService $mobileAuthService,
        NotificationService $notificationService
    ): JsonResponse {
        $context = $this->requireActiveMobileContext($request, $authService, $mobileAuthService);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = (array) ($context['user'] ?? []);
        $userId = (int) ($user['idUser'] ?? 0);
        $role = (string) ($user['role'] ?? 'ROLE_USER');
        $limit = max(1, min(50, (int) $request->query->get('limit', 20)));

        return $this->json([
            'ok' => true,
            'items' => $notificationService->getRecentNotificationsFor($userId, $role, $limit),
            'unread_count' => $notificationService->countUnreadFor($userId, $role),
        ]);
    }

    #[Route('/support/messages', name: 'mobile_api_support_messages', methods: ['GET'])]
    public function supportMessages(
        Request $request,
        AuthService $authService,
        MobileAuthService $mobileAuthService,
        SupportChatService $supportChatService
    ): JsonResponse {
        $context = $this->requireActiveMobileContext($request, $authService, $mobileAuthService);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        if (!$supportChatService->isSchemaReady()) {
            return $this->json(['ok' => false, 'message' => $supportChatService->getSchemaMissingMessage()], 503);
        }

        $user = (array) ($context['user'] ?? []);
        $userId = (int) ($user['idUser'] ?? 0);
        $afterId = max(0, (int) $request->query->get('after_id', 0));
        $messages = $afterId > 0
            ? $supportChatService->listUserConversationSince($userId, $afterId)
            : $supportChatService->listUserConversation($userId);
        $supportChatService->markUserConversationAsRead($userId);

        return $this->json([
            'ok' => true,
            'messages' => array_map(fn (array $message): array => $this->normalizeSupportMessage($message), $messages),
        ]);
    }

    #[Route('/support/messages', name: 'mobile_api_support_send', methods: ['POST'])]
    public function supportSend(
        Request $request,
        AuthService $authService,
        MobileAuthService $mobileAuthService,
        SupportChatService $supportChatService
    ): JsonResponse {
        $context = $this->requireActiveMobileContext($request, $authService, $mobileAuthService);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        if (!$supportChatService->isSchemaReady()) {
            return $this->json(['ok' => false, 'message' => $supportChatService->getSchemaMissingMessage()], 503);
        }

        $payload = $this->readPayload($request);
        $messageText = (string) ($payload['message'] ?? '');

        try {
            $messageId = $supportChatService->sendFromUserToAdmin((int) ($context['user']['idUser'] ?? 0), $messageText);
            $message = $supportChatService->findMessageById($messageId);
        } catch (\Throwable $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return $this->json([
            'ok' => true,
            'message' => $message !== null ? $this->normalizeSupportMessage($message) : null,
        ]);
    }

    #[Route('/trusted-devices', name: 'mobile_api_trusted_devices', methods: ['GET'])]
    public function trustedDevices(Request $request, AuthService $authService, MobileAuthService $mobileAuthService): JsonResponse
    {
        $context = $this->requireActiveMobileContext($request, $authService, $mobileAuthService);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        return $this->json([
            'ok' => true,
            'trusted_device' => (bool) ($context['trusted_device'] ?? false),
            'devices' => $mobileAuthService->listTrustedDevices((int) ($context['user']['idUser'] ?? 0)),
        ]);
    }

    #[Route('/trust/confirm', name: 'mobile_api_trust_confirm', methods: ['POST'])]
    public function confirmTrust(
        Request $request,
        AuthService $authService,
        MobileAuthService $mobileAuthService,
        QrSessionService $qrSessionService
    ): JsonResponse {
        $context = $this->requireActiveMobileContext($request, $authService, $mobileAuthService);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $payload = $this->readPayload($request);
        $qrToken = trim((string) ($payload['qr_token'] ?? ''));
        if ($qrToken === '') {
            return $this->json(['ok' => false, 'message' => 'qr_token is required.'], 422);
        }

        $user = (array) ($context['user'] ?? []);
        $token = (array) ($context['token'] ?? []);

        $deviceId = $mobileAuthService->normalizeDeviceId((string) ($payload['device_id'] ?? ($token['device_id'] ?? '')));
        $deviceName = $mobileAuthService->normalizeDeviceName((string) ($payload['device_name'] ?? ($token['device_name'] ?? 'Mobile device')));
        $platform = trim((string) ($payload['platform'] ?? ($token['platform'] ?? '')));
        $appVersion = trim((string) ($payload['app_version'] ?? ($token['app_version'] ?? '')));

        try {
            $trustedDeviceId = $mobileAuthService->trustDevice((int) ($user['idUser'] ?? 0), $deviceId, $deviceName, $platform, $appVersion);
            $qrSessionService->consumeTrustSession($qrToken, (int) ($user['idUser'] ?? 0), $trustedDeviceId);
        } catch (\Throwable $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return $this->json([
            'ok' => true,
            'message' => 'Device trusted successfully. You can now approve QR website logins.',
            'trusted_device' => true,
            'devices' => $mobileAuthService->listTrustedDevices((int) ($user['idUser'] ?? 0)),
        ]);
    }

    #[Route('/qr-login/approve', name: 'mobile_api_qr_login_approve', methods: ['POST'])]
    public function approveQrWebLogin(
        Request $request,
        AuthService $authService,
        MobileAuthService $mobileAuthService,
        QrSessionService $qrSessionService
    ): JsonResponse {
        $context = $this->requireActiveMobileContext($request, $authService, $mobileAuthService);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        if (!(bool) ($context['trusted_device'] ?? false)) {
            return $this->json([
                'ok' => false,
                'message' => 'This mobile is not trusted yet. Trust it from your portal profile QR first.',
            ], 403);
        }

        $payload = $this->readPayload($request);
        $qrToken = trim((string) ($payload['qr_token'] ?? ''));
        if ($qrToken === '') {
            return $this->json(['ok' => false, 'message' => 'qr_token is required.'], 422);
        }

        $userId = (int) ($context['user']['idUser'] ?? 0);
        $deviceId = (string) ($context['token']['device_id'] ?? '');
        $trustedDeviceId = $mobileAuthService->findTrustedDeviceId($userId, $deviceId);
        if ($trustedDeviceId === null) {
            return $this->json(['ok' => false, 'message' => 'Trusted device could not be resolved.'], 422);
        }

        try {
            $qrSessionService->approveWebLoginSession($qrToken, $userId, $trustedDeviceId);
        } catch (\Throwable $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return $this->json([
            'ok' => true,
            'message' => 'Web login approved. The browser session will connect shortly.',
        ]);
    }

    /**
     * @return array<string,mixed>|JsonResponse
     */
    private function requireActiveMobileContext(
        Request $request,
        AuthService $authService,
        MobileAuthService $mobileAuthService
    ): array|JsonResponse {
        try {
            $context = $mobileAuthService->authenticateRequest($request);
        } catch (\Throwable $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], 503);
        }

        if ($context === null) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized mobile token.'], 401);
        }

        $user = (array) ($context['user'] ?? []);
        $role = strtoupper((string) ($user['role'] ?? ''));
        if ($role !== 'ROLE_USER') {
            return $this->json(['ok' => false, 'message' => 'Mobile API is for user accounts only.'], 403);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $mobileAuthService->revokeAccessTokenById((int) ($context['token']['idMobileAccessToken'] ?? 0));

            return $this->json(['ok' => false, 'message' => $blockedReason], 403);
        }

        return $context;
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeUser(array $user): array
    {
        $imagePath = trim((string) ($user['profile_image_path'] ?? ''));

        return [
            'idUser' => (int) ($user['idUser'] ?? 0),
            'nom' => (string) ($user['nom'] ?? ''),
            'prenom' => (string) ($user['prenom'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'telephone' => (string) ($user['telephone'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'status' => (string) ($user['status'] ?? ''),
            'biometric_enabled' => (int) ($user['biometric_enabled'] ?? 0),
            'profile_image_path' => $imagePath,
            'profile_image_url' => $this->normalizeAssetUrl($imagePath),
            'last_online_at' => (string) ($user['last_online_at'] ?? ''),
            'last_online_from' => (string) ($user['last_online_from'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $message
     * @return array<string,mixed>
     */
    private function normalizeSupportMessage(array $message): array
    {
        return [
            'idSupportMessage' => (int) ($message['idSupportMessage'] ?? 0),
            'sender_role' => (string) ($message['sender_role'] ?? ''),
            'recipient_role' => (string) ($message['recipient_role'] ?? ''),
            'sender_name' => (string) ($message['sender_name'] ?? ''),
            'recipient_name' => (string) ($message['recipient_name'] ?? ''),
            'message_text' => (string) ($message['message_text'] ?? ''),
            'is_read' => (int) ($message['is_read'] ?? 0),
            'created_at' => (string) ($message['created_at'] ?? ''),
            'read_at' => (string) ($message['read_at'] ?? ''),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function readPayload(Request $request): array
    {
        $content = trim((string) $request->getContent());
        if ($content !== '') {
            try {
                $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable) {
                // Continue with form payload fallback.
            }
        }

        $payload = $request->request->all();

        return is_array($payload) ? $payload : [];
    }

    private function normalizeAssetUrl(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        if (str_contains($trimmed, '://') || str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        return '/'.ltrim($trimmed, '/');
    }
}

