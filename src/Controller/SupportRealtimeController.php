<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\SupportChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SupportRealtimeController extends AbstractController
{
    #[Route('/portal/support/realtime/messages', name: 'portal_support_realtime_messages', methods: ['GET'])]
    public function portalMessages(Request $request, AuthService $authService, SupportChatService $supportChatService): JsonResponse
    {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->json(['ok' => false, 'message' => 'Utilisateur non authentifie.'], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            return $this->json(['ok' => false, 'message' => $blockedReason], 403);
        }

        if (strtoupper((string) ($user['role'] ?? '')) === 'ROLE_ADMIN') {
            return $this->json(['ok' => false, 'message' => 'Admin account cannot use portal support endpoint.'], 403);
        }

        if (!$supportChatService->isSchemaReady()) {
            return $this->json(['ok' => false, 'message' => $supportChatService->getSchemaMissingMessage()], 503);
        }

        $afterId = max(0, (int) $request->query->get('after_id', 0));
        $userId = (int) ($user['idUser'] ?? 0);
        $messages = $supportChatService->listUserConversationSince($userId, $afterId);
        $supportChatService->markUserConversationAsRead($userId);

        return $this->json([
            'ok' => true,
            'messages' => $this->normalizeMessages($messages),
            'latest_id' => $this->extractLatestId($messages, $afterId),
        ]);
    }

    #[Route('/portal/support/realtime/send', name: 'portal_support_realtime_send', methods: ['POST'])]
    public function portalSend(Request $request, AuthService $authService, SupportChatService $supportChatService): JsonResponse
    {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->json(['ok' => false, 'message' => 'Utilisateur non authentifie.'], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            return $this->json(['ok' => false, 'message' => $blockedReason], 403);
        }

        if (strtoupper((string) ($user['role'] ?? '')) === 'ROLE_ADMIN') {
            return $this->json(['ok' => false, 'message' => 'Admin account cannot use portal support endpoint.'], 403);
        }

        if (!$supportChatService->isSchemaReady()) {
            return $this->json(['ok' => false, 'message' => $supportChatService->getSchemaMissingMessage()], 503);
        }

        try {
            $messageId = $supportChatService->sendFromUserToAdmin((int) ($user['idUser'] ?? 0), (string) $request->request->get('message', ''));
            $message = $supportChatService->findMessageById($messageId);
        } catch (\Throwable $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return $this->json([
            'ok' => true,
            'message' => $message !== null ? $this->normalizeMessage($message) : null,
        ]);
    }

    #[Route('/admin/support/realtime/messages/{userId}', name: 'admin_support_realtime_messages', methods: ['GET'])]
    public function adminMessages(int $userId, Request $request, AuthService $authService, SupportChatService $supportChatService): JsonResponse
    {
        $session = $request->getSession();
        $adminUser = $authService->getAuthenticatedUser($session);
        if ($adminUser === null) {
            return $this->json(['ok' => false, 'message' => 'Utilisateur non authentifie.'], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($adminUser);
        if ($blockedReason !== null) {
            return $this->json(['ok' => false, 'message' => $blockedReason], 403);
        }

        if (strtoupper((string) ($adminUser['role'] ?? '')) !== 'ROLE_ADMIN') {
            return $this->json(['ok' => false, 'message' => 'Admin access required.'], 403);
        }

        if (!$supportChatService->isSchemaReady()) {
            return $this->json(['ok' => false, 'message' => $supportChatService->getSchemaMissingMessage()], 503);
        }

        $targetUser = $supportChatService->getConversationUser($userId);
        if ($targetUser === null) {
            return $this->json(['ok' => false, 'message' => 'Target user not found.'], 404);
        }

        $afterId = max(0, (int) $request->query->get('after_id', 0));
        $messages = $supportChatService->listAdminConversationWithUserSince($userId, $afterId);
        $supportChatService->markAdminConversationAsRead($userId);

        return $this->json([
            'ok' => true,
            'messages' => $this->normalizeMessages($messages),
            'latest_id' => $this->extractLatestId($messages, $afterId),
        ]);
    }

    #[Route('/admin/support/realtime/conversations', name: 'admin_support_realtime_conversations', methods: ['GET'])]
    public function adminConversations(Request $request, AuthService $authService, SupportChatService $supportChatService): JsonResponse
    {
        $session = $request->getSession();
        $adminUser = $authService->getAuthenticatedUser($session);
        if ($adminUser === null) {
            return $this->json(['ok' => false, 'message' => 'Utilisateur non authentifie.'], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($adminUser);
        if ($blockedReason !== null) {
            return $this->json(['ok' => false, 'message' => $blockedReason], 403);
        }

        if (strtoupper((string) ($adminUser['role'] ?? '')) !== 'ROLE_ADMIN') {
            return $this->json(['ok' => false, 'message' => 'Admin access required.'], 403);
        }

        if (!$supportChatService->isSchemaReady()) {
            return $this->json(['ok' => false, 'message' => $supportChatService->getSchemaMissingMessage()], 503);
        }

        $search = trim((string) $request->query->get('q', ''));
        $rows = $supportChatService->listAdminConversations($search);

        return $this->json([
            'ok' => true,
            'conversations' => array_map(fn (array $row): array => [
                'idUser' => (int) ($row['idUser'] ?? 0),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'profile_image_path' => (string) ($row['profile_image_path'] ?? ''),
                'profile_image_url' => $this->normalizeAvatarUrl((string) ($row['profile_image_path'] ?? '')),
                'unread_count' => (int) ($row['unread_count'] ?? 0),
                'last_message_preview' => (string) ($row['last_message_preview'] ?? ''),
                'last_message_at' => (string) ($row['last_message_at'] ?? ''),
            ], $rows),
        ]);
    }

    #[Route('/admin/support/realtime/send', name: 'admin_support_realtime_send', methods: ['POST'])]
    public function adminSend(Request $request, AuthService $authService, SupportChatService $supportChatService): JsonResponse
    {
        $session = $request->getSession();
        $adminUser = $authService->getAuthenticatedUser($session);
        if ($adminUser === null) {
            return $this->json(['ok' => false, 'message' => 'Utilisateur non authentifie.'], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($adminUser);
        if ($blockedReason !== null) {
            return $this->json(['ok' => false, 'message' => $blockedReason], 403);
        }

        if (strtoupper((string) ($adminUser['role'] ?? '')) !== 'ROLE_ADMIN') {
            return $this->json(['ok' => false, 'message' => 'Admin access required.'], 403);
        }

        if (!$supportChatService->isSchemaReady()) {
            return $this->json(['ok' => false, 'message' => $supportChatService->getSchemaMissingMessage()], 503);
        }

        $userId = max(0, (int) $request->request->get('user_id', 0));
        if ($userId <= 0) {
            return $this->json(['ok' => false, 'message' => 'A valid target user is required.'], 422);
        }

        try {
            $messageId = $supportChatService->sendFromAdminToUser((int) ($adminUser['idUser'] ?? 0), $userId, (string) $request->request->get('message', ''));
            $message = $supportChatService->findMessageById($messageId);
        } catch (\Throwable $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return $this->json([
            'ok' => true,
            'message' => $message !== null ? $this->normalizeMessage($message) : null,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return list<array<string, mixed>>
     */
    private function normalizeMessages(array $messages): array
    {
        return array_map(fn (array $message): array => $this->normalizeMessage($message), $messages);
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function normalizeMessage(array $message): array
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
     * @param list<array<string, mixed>> $messages
     */
    private function extractLatestId(array $messages, int $fallback): int
    {
        $latestId = $fallback;
        foreach ($messages as $message) {
            $id = (int) ($message['idSupportMessage'] ?? 0);
            if ($id > $latestId) {
                $latestId = $id;
            }
        }

        return $latestId;
    }

    private function normalizeAvatarUrl(string $profilePath): string
    {
        $path = trim($profilePath);
        if ($path === '') {
            return '';
        }

        if (str_contains($path, '://') || str_starts_with($path, '/')) {
            return $path;
        }

        return '/'.ltrim($path, '/');
    }
}
