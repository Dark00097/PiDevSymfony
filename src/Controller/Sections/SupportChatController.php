<?php

namespace App\Controller\Sections;

use App\Service\SupportChatService;
use Symfony\Component\HttpFoundation\Request;

final class SupportChatController
{
    public function buildPortalData(SupportChatService $supportChatService, int $userId): array
    {
        $supportChatService->markUserConversationAsRead($userId);
        $messages = $supportChatService->listUserConversation($userId);

        return [
            'items' => $messages,
            'support' => [
                'support_messages' => $messages,
                'support_schema_ready' => $supportChatService->isSchemaReady(),
                'support_schema_message' => $supportChatService->getSchemaMissingMessage(),
            ],
        ];
    }

    public function buildAdminData(SupportChatService $supportChatService, Request $request): array
    {
        $searchQuery = trim((string) $request->query->get('q', ''));
        $conversations = $supportChatService->listAdminConversations($searchQuery);

        $selectedUserId = $this->positiveQueryInt($request, 'chat_user');
        if ($selectedUserId === null && $conversations !== []) {
            $selectedUserId = (int) ($conversations[0]['idUser'] ?? 0);
            if ($selectedUserId <= 0) {
                $selectedUserId = null;
            }
        }

        $selectedUser = null;
        $messages = [];
        if ($selectedUserId !== null) {
            $selectedUser = $supportChatService->getConversationUser($selectedUserId);
            if ($selectedUser !== null) {
                $supportChatService->markAdminConversationAsRead($selectedUserId);
                $messages = $supportChatService->listAdminConversationWithUser($selectedUserId);
                $conversations = $supportChatService->listAdminConversations($searchQuery);
            } else {
                $selectedUserId = null;
            }
        }

        return [
            'items' => $messages,
            'support' => [
                'support_messages' => $messages,
                'support_conversations' => $conversations,
                'support_selected_user_id' => $selectedUserId,
                'support_selected_user' => $selectedUser,
                'support_search_query' => $searchQuery,
                'support_schema_ready' => $supportChatService->isSchemaReady(),
                'support_schema_message' => $supportChatService->getSchemaMissingMessage(),
            ],
        ];
    }

    public function handlePortalAction(string $action, Request $request, SupportChatService $supportChatService, array $user): ?array
    {
        if ($action !== 'support_send') {
            return null;
        }

        $supportChatService->sendFromUserToAdmin((int) ($user['idUser'] ?? 0), (string) $request->request->get('message', ''));

        return ['type' => 'success', 'message' => 'Your message was sent to support.'];
    }

    public function handleAdminAction(string $action, Request $request, SupportChatService $supportChatService, array $adminUser): ?array
    {
        if ($action !== 'support_admin_send') {
            return null;
        }

        $targetUserId = $this->positiveRequestInt($request, 'user_id');
        if ($targetUserId === null) {
            return ['type' => 'error', 'message' => 'A valid target user is required.'];
        }

        $supportChatService->sendFromAdminToUser(
            (int) ($adminUser['idUser'] ?? 0),
            $targetUserId,
            (string) $request->request->get('message', '')
        );

        return ['type' => 'success', 'message' => 'Reply sent to user.'];
    }

    private function positiveRequestInt(Request $request, string $key): ?int
    {
        $value = $request->request->get($key);
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || preg_match('/^\d+$/', $normalized) !== 1) {
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
        if ($normalized === '' || preg_match('/^\d+$/', $normalized) !== 1) {
            return null;
        }

        $intValue = (int) $normalized;

        return $intValue > 0 ? $intValue : null;
    }
}
